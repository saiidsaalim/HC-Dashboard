<?php

namespace App\Services\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaLegacyBackfillCategory;
use App\Enums\WlaPeriodUnit;
use App\Enums\WorkScheduleCalculationType;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use App\Models\WorkSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class WlaLegacyBackfillService
{
    public function __construct(
        private WlaCalculationService $wlaCalculationService,
        private WorkCalendarService $workCalendarService,
    ) {}

    public function stageOneSchemaIsAvailable(): bool
    {
        return Schema::hasColumns('work_schedules', ['calculation_type'])
            && Schema::hasColumns('wla_activities', [
                'period_unit',
                'time_allocated_hours',
                'annual_workload_hours',
            ])
            && Schema::hasColumns('wla_assessments', [
                'total_annual_workload_hours',
                'fte',
                'recommended_employees',
            ]);
    }

    /** @return array<string, mixed> */
    public function audit(?int $assessmentId = null): array
    {
        $assessmentIds = $this->assessmentIds($assessmentId);
        $scheduleIds = $this->scheduleIds($assessmentId);
        $scheduleResults = $this->classifySchedules($scheduleIds);
        $assessmentResults = $this->classifyAssessments($assessmentIds, $scheduleResults);

        return $this->report($scheduleResults, $assessmentResults, 0, 0);
    }

    /** @return array<string, mixed> */
    public function apply(?int $assessmentId = null): array
    {
        $assessmentIds = $this->assessmentIds($assessmentId);
        $scheduleIds = $this->scheduleIds($assessmentId);
        [$scheduleResults, $schedulesApplied] = $this->applySchedules($scheduleIds);
        $assessmentResults = [];
        $assessmentsBackfilled = 0;

        foreach ($assessmentIds as $id) {
            try {
                $result = DB::transaction(function () use ($id): array {
                    $assessment = WlaAssessment::query()->lockForUpdate()->findOrFail($id);
                    $activities = $assessment->activities()
                        ->reorder('id')
                        ->lockForUpdate()
                        ->get();
                    $assessment->setRelation('activities', $activities);
                    $assessment->load(['workCalendar', 'workSchedule']);
                    $scheduleResult = $this->classifySchedule($assessment->workSchedule);
                    $classification = $this->classifyAssessment($assessment, $scheduleResult);

                    if ($classification['category'] !== WlaLegacyBackfillCategory::Ready) {
                        return ['classification' => $classification, 'backfilled' => false];
                    }

                    foreach ($classification['activities'] as $activityResult) {
                        if ($activityResult['category'] !== WlaLegacyBackfillCategory::Ready) {
                            continue;
                        }

                        $activities->firstWhere('id', $activityResult['id'])?->update($activityResult['changes']);
                    }

                    $this->wlaCalculationService->recalculate($assessment);

                    return ['classification' => $classification, 'backfilled' => true];
                });
            } catch (ValidationException $exception) {
                $assessment = WlaAssessment::query()->with(['activities', 'workCalendar', 'workSchedule'])->findOrFail($id);
                $classification = $this->classifyAssessment(
                    $assessment,
                    $this->classifySchedule($assessment->workSchedule),
                );
                $classification['category'] = WlaLegacyBackfillCategory::Invalid;
                $classification['reason'] = 'Kalkulasi gagal: '.$this->firstValidationError($exception);
                $result = ['classification' => $classification, 'backfilled' => false];
            }

            $assessmentResults[] = $result['classification'];
            $assessmentsBackfilled += $result['backfilled'] ? 1 : 0;
        }

        return $this->report(
            $scheduleResults,
            $assessmentResults,
            $schedulesApplied,
            $assessmentsBackfilled,
        );
    }

    /** @return Collection<int, int> */
    private function assessmentIds(?int $assessmentId): Collection
    {
        $query = WlaAssessment::query()->orderBy('id');

        if ($assessmentId !== null) {
            $query->whereKey($assessmentId);
        }

        $ids = $query->pluck('id');

        if ($assessmentId !== null && $ids->isEmpty()) {
            throw new RuntimeException("Assessment WLA dengan ID {$assessmentId} tidak ditemukan.");
        }

        return $ids;
    }

    /** @return Collection<int, int> */
    private function scheduleIds(?int $assessmentId): Collection
    {
        if ($assessmentId === null) {
            return WorkSchedule::query()->orderBy('id')->pluck('id');
        }

        return WlaAssessment::query()
            ->whereKey($assessmentId)
            ->pluck('work_schedule_id')
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $scheduleIds
     * @return array<int, array<string, mixed>>
     */
    private function classifySchedules(Collection $scheduleIds): array
    {
        return WorkSchedule::query()
            ->whereKey($scheduleIds)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkSchedule $schedule): array => $this->classifySchedule($schedule))
            ->all();
    }

    /**
     * @param  Collection<int, int>  $scheduleIds
     * @return array{array<int, array<string, mixed>>, int}
     */
    private function applySchedules(Collection $scheduleIds): array
    {
        return DB::transaction(function () use ($scheduleIds): array {
            $results = [];
            $applied = 0;
            $schedules = WorkSchedule::query()
                ->whereKey($scheduleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($schedules as $schedule) {
                $result = $this->classifySchedule($schedule);
                $results[] = $result;

                if ($result['category'] !== WlaLegacyBackfillCategory::Ready) {
                    continue;
                }

                $schedule->update(['calculation_type' => $result['target']->value]);
                $applied++;
            }

            return [$results, $applied];
        });
    }

    /** @return array<string, mixed> */
    private function classifySchedule(?WorkSchedule $schedule): array
    {
        if ($schedule === null) {
            return $this->classification(
                'schedule',
                0,
                null,
                WlaLegacyBackfillCategory::Invalid,
                'Schedule tidak ditemukan.',
            );
        }

        $rawCalculationType = $schedule->getRawOriginal('calculation_type');
        $expectedType = WorkScheduleCalculationType::fromLegacyCode((string) $schedule->code);

        if ($rawCalculationType === null) {
            if ($expectedType === null) {
                return $this->classification(
                    'schedule',
                    $schedule->id,
                    null,
                    WlaLegacyBackfillCategory::NeedsReview,
                    'Schedule custom belum memiliki calculation_type.',
                );
            }

            return [
                ...$this->classification(
                    'schedule',
                    $schedule->id,
                    null,
                    WlaLegacyBackfillCategory::Ready,
                    'Kode schedule bawaan dapat dipetakan secara eksak.',
                ),
                'target' => $expectedType,
            ];
        }

        $currentType = WorkScheduleCalculationType::tryFrom((string) $rawCalculationType);

        if ($currentType === null) {
            return $this->classification(
                'schedule',
                $schedule->id,
                null,
                WlaLegacyBackfillCategory::Invalid,
                'Nilai calculation_type tidak dikenal.',
            );
        }

        if ($expectedType !== null && $currentType !== $expectedType) {
            return $this->classification(
                'schedule',
                $schedule->id,
                null,
                WlaLegacyBackfillCategory::Conflict,
                'Klasifikasi tersimpan bertentangan dengan kode schedule bawaan.',
            );
        }

        return [
            ...$this->classification(
                'schedule',
                $schedule->id,
                null,
                WlaLegacyBackfillCategory::AlreadyMigrated,
                'Schedule sudah memiliki klasifikasi yang valid.',
            ),
            'target' => $currentType,
        ];
    }

    /**
     * @param  Collection<int, int>  $assessmentIds
     * @param  array<int, array<string, mixed>>  $scheduleResults
     * @return array<int, array<string, mixed>>
     */
    private function classifyAssessments(Collection $assessmentIds, array $scheduleResults): array
    {
        $scheduleResultsById = collect($scheduleResults)->keyBy('id');

        return WlaAssessment::query()
            ->whereKey($assessmentIds)
            ->with(['activities', 'workCalendar', 'workSchedule'])
            ->orderBy('id')
            ->get()
            ->map(function (WlaAssessment $assessment) use ($scheduleResultsById): array {
                $scheduleResult = $scheduleResultsById->get($assessment->work_schedule_id)
                    ?? $this->classifySchedule($assessment->workSchedule);

                return $this->classifyAssessment($assessment, $scheduleResult);
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $scheduleResult
     * @return array<string, mixed>
     */
    private function classifyAssessment(WlaAssessment $assessment, array $scheduleResult): array
    {
        if ($assessment->status === WlaAssessmentStatus::Final) {
            return $this->classifyFinalAssessment($assessment);
        }

        $environment = $this->assessmentEnvironment($assessment, $scheduleResult);
        $activityResults = $assessment->activities
            ->sortBy('id')
            ->values()
            ->map(fn (WlaActivity $activity): array => $this->classifyActivity($activity, $environment))
            ->all();
        $blockedCategory = $this->highestBlockingCategory($activityResults);

        if ($environment['category']->requiresReview()) {
            $blockedCategory = $this->higherCategory($blockedCategory, $environment['category']);
        }

        if ($blockedCategory !== null) {
            return [
                ...$this->classification(
                    'assessment',
                    $assessment->id,
                    $assessment->id,
                    $blockedCategory,
                    $this->assessmentBlockedReason($environment, $activityResults),
                ),
                'activities' => $activityResults,
            ];
        }

        try {
            $this->validateAggregate($environment, $activityResults);
        } catch (ValidationException|InvalidArgumentException $exception) {
            $reason = $exception instanceof ValidationException
                ? $this->firstValidationError($exception)
                : $exception->getMessage();

            foreach ($activityResults as &$activityResult) {
                if ($activityResult['category'] === WlaLegacyBackfillCategory::Ready) {
                    $activityResult['category'] = WlaLegacyBackfillCategory::Invalid;
                    $activityResult['reason'] = 'Agregat assessment tidak valid: '.$reason;
                }
            }
            unset($activityResult);

            return [
                ...$this->classification(
                    'assessment',
                    $assessment->id,
                    $assessment->id,
                    WlaLegacyBackfillCategory::Invalid,
                    'Agregat assessment tidak valid: '.$reason,
                ),
                'activities' => $activityResults,
            ];
        }

        $hasReadyActivity = collect($activityResults)->contains(
            fn (array $result): bool => $result['category'] === WlaLegacyBackfillCategory::Ready,
        );

        return [
            ...$this->classification(
                'assessment',
                $assessment->id,
                $assessment->id,
                $hasReadyActivity
                    ? WlaLegacyBackfillCategory::Ready
                    : WlaLegacyBackfillCategory::AlreadyMigrated,
                $hasReadyActivity
                    ? 'Seluruh aktivitas legacy dalam assessment aman untuk dipetakan.'
                    : 'Assessment tidak memiliki aktivitas legacy yang perlu diubah.',
            ),
            'activities' => $activityResults,
        ];
    }

    /** @return array<string, mixed> */
    private function classifyFinalAssessment(WlaAssessment $assessment): array
    {
        $snapshot = $assessment->final_snapshot;
        $requiredSnapshotPaths = [
            'assessment_code',
            'period',
            'department.id',
            'department.code',
            'department.name',
            'unit.id',
            'unit.code',
            'unit.name',
            'position.id',
            'position.code',
            'position.name',
            'schedule.id',
            'schedule.code',
            'schedule.name',
            'schedule.calculation_type',
            'schedule.wla_hours_per_day',
            'calendar.id',
            'calendar.year',
            'calendar.total_days',
            'efficiency_factor',
            'working_days',
            'annual_working_hours',
            'effective_annual_working_hours',
            'activities',
            'total_annual_workload',
            'fte',
            'recommended_employees',
            'finalizer.id',
            'finalizer.name',
            'finalized_at',
        ];
        $snapshotComplete = is_array($snapshot)
            && collect($requiredSnapshotPaths)->every(
                fn (string $path): bool => data_get($snapshot, $path) !== null,
            )
            && is_array(data_get($snapshot, 'activities'))
            && data_get($snapshot, 'activities') !== []
            && $assessment->finalized_at !== null
            && $assessment->finalized_by !== null
            && $assessment->finalization_key !== null;
        $category = $snapshotComplete
            ? WlaLegacyBackfillCategory::AlreadyMigrated
            : WlaLegacyBackfillCategory::Conflict;
        $reason = $snapshotComplete
            ? 'WLA Final sudah memiliki snapshot lengkap dan tidak diubah.'
            : 'WLA Final tidak memiliki metadata atau snapshot lengkap dan memerlukan review manual.';
        $activities = $assessment->activities
            ->sortBy('id')
            ->values()
            ->map(fn (WlaActivity $activity): array => $this->activityClassification(
                $activity,
                $category,
                $reason,
            ))
            ->all();

        return [
            ...$this->classification(
                'assessment',
                $assessment->id,
                $assessment->id,
                $category,
                $reason,
            ),
            'activities' => $activities,
        ];
    }

    /**
     * @param  array<string, mixed>  $scheduleResult
     * @return array<string, mixed>
     */
    private function assessmentEnvironment(WlaAssessment $assessment, array $scheduleResult): array
    {
        if ($assessment->workCalendar === null) {
            return [
                'category' => WlaLegacyBackfillCategory::Invalid,
                'reason' => 'Kalender assessment tidak ditemukan.',
            ];
        }

        if ($assessment->workCalendar->year !== $assessment->period) {
            return [
                'category' => WlaLegacyBackfillCategory::Invalid,
                'reason' => 'Tahun kalender berbeda dari periode assessment.',
            ];
        }

        if ($scheduleResult['category']->requiresReview()) {
            return [
                'category' => $scheduleResult['category'],
                'reason' => $scheduleResult['reason'],
            ];
        }

        try {
            $workingDays = $this->workCalendarService->calculateWorkingDays(
                $assessment->period,
                $assessment->workCalendar,
                $assessment->workSchedule,
            );
            $workingHours = $this->workCalendarService->calculateWorkingHoursPerYear(
                $assessment->period,
                $assessment->workCalendar,
                $assessment->workSchedule,
            );
            $effectiveHours = $this->workCalendarService->calculateEffectiveWorkingHours(
                $workingHours,
                $assessment->efficiency_factor,
            );
        } catch (ValidationException $exception) {
            return [
                'category' => WlaLegacyBackfillCategory::Invalid,
                'reason' => $this->firstValidationError($exception),
            ];
        }

        return [
            'category' => WlaLegacyBackfillCategory::Ready,
            'reason' => 'Kalender dan schedule dapat dihitung.',
            'working_days' => $workingDays,
            'effective_hours' => $effectiveHours,
        ];
    }

    /**
     * @param  array<string, mixed>  $environment
     * @return array<string, mixed>
     */
    private function classifyActivity(WlaActivity $activity, array $environment): array
    {
        $newValues = [
            'period_unit' => $activity->getRawOriginal('period_unit'),
            'time_allocated_hours' => $activity->getRawOriginal('time_allocated_hours'),
            'annual_workload_hours' => $activity->getRawOriginal('annual_workload_hours'),
        ];
        $filledNewValues = collect($newValues)->filter(fn (mixed $value): bool => $value !== null)->count();

        if ($filledNewValues > 0 && $filledNewValues < count($newValues)) {
            return $this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::Conflict,
                'Field WLA baru hanya terisi sebagian.',
            );
        }

        if ($filledNewValues === count($newValues)) {
            return $this->classifyMigratedActivity($activity, $environment);
        }

        if ($environment['category']->requiresReview()) {
            return $this->activityClassification(
                $activity,
                $environment['category'],
                $environment['reason'],
            );
        }

        $frequencyUnit = (string) $activity->getRawOriginal('frequency_unit');
        $volumeUnit = (string) $activity->getRawOriginal('volume_unit');

        if ($frequencyUnit !== $volumeUnit) {
            return $this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::NeedsReview,
                'frequency_unit dan volume_unit berbeda.',
            );
        }

        $periodUnit = WlaPeriodUnit::tryFrom($frequencyUnit);

        if ($periodUnit === null) {
            return $this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::Invalid,
                'Unit legacy tidak dapat dipetakan ke Day, Week, Month, atau Year.',
            );
        }

        if ($activity->getRawOriginal('time_unit') !== 'Hour') {
            return $this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::NeedsReview,
                'time_unit legacy bukan Hour.',
            );
        }

        try {
            $legacyFrequency = $this->positiveScaledDecimal(
                $activity->getRawOriginal('frequency'),
                2,
                '99999999.99',
                'frequency legacy',
            );
            $legacyVolume = $this->positiveScaledDecimal(
                $activity->getRawOriginal('volume'),
                2,
                '9999999999.99',
                'volume legacy',
            );
            $timeAllocated = $this->positiveScaledDecimal(
                $activity->getRawOriginal('time_allocated'),
                2,
                WlaActivity::MAX_INPUT_VALUE,
                'time_allocated legacy',
            );
            $frequencyProduct = $this->multiplyChecked($legacyFrequency, $legacyVolume);

            if ($frequencyProduct % 100 !== 0) {
                throw new InvalidArgumentException('Hasil frequency × volume memiliki presisi lebih dari dua desimal.');
            }

            $newFrequency = intdiv($frequencyProduct, 100);
            $maximumFrequency = $this->positiveScaledDecimal(
                WlaActivity::MAX_INPUT_VALUE,
                2,
                WlaActivity::MAX_INPUT_VALUE,
                'batas frequency',
            );

            if ($newFrequency > $maximumFrequency) {
                throw new InvalidArgumentException('Hasil frequency × volume melampaui batas frequency baru.');
            }

            $frequency = $this->formatScaledInteger($newFrequency, 2);
            $time = $this->formatScaledInteger($timeAllocated, 2);
            $annualWorkload = $this->wlaCalculationService->calculateAnnualWorkload(
                $frequency,
                $time,
                $periodUnit,
                $environment['working_days'],
            );
        } catch (ValidationException|InvalidArgumentException $exception) {
            return $this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::Invalid,
                $exception instanceof ValidationException
                    ? $this->firstValidationError($exception)
                    : $exception->getMessage(),
            );
        }

        return [
            ...$this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::Ready,
                'Data legacy dapat dipetakan secara deterministik.',
            ),
            'changes' => [
                'frequency' => $frequency,
                'period_unit' => $periodUnit->value,
                'time_allocated_hours' => $time,
            ],
            'annual_workload' => $annualWorkload,
        ];
    }

    /**
     * @param  array<string, mixed>  $environment
     * @return array<string, mixed>
     */
    private function classifyMigratedActivity(WlaActivity $activity, array $environment): array
    {
        if ($environment['category']->requiresReview()) {
            return $this->activityClassification(
                $activity,
                $environment['category'],
                $environment['reason'],
            );
        }

        try {
            $frequency = $this->formatScaledInteger($this->positiveScaledDecimal(
                $activity->getRawOriginal('frequency'),
                2,
                WlaActivity::MAX_INPUT_VALUE,
                'frequency baru',
            ), 2);
            $time = $this->formatScaledInteger($this->positiveScaledDecimal(
                $activity->getRawOriginal('time_allocated_hours'),
                2,
                WlaActivity::MAX_INPUT_VALUE,
                'time_allocated_hours',
            ), 2);
            $periodUnit = WlaPeriodUnit::tryFrom((string) $activity->getRawOriginal('period_unit'));

            if ($periodUnit === null) {
                throw new InvalidArgumentException('period_unit baru tidak dikenal.');
            }

            $expectedAnnualWorkload = $this->wlaCalculationService->calculateAnnualWorkload(
                $frequency,
                $time,
                $periodUnit,
                $environment['working_days'],
            );
            $storedAnnualWorkload = $this->normalizedDecimal(
                $activity->getRawOriginal('annual_workload_hours'),
                4,
                20,
                'annual_workload_hours',
            );

            if ($storedAnnualWorkload !== $expectedAnnualWorkload) {
                throw new InvalidArgumentException('annual_workload_hours bertentangan dengan field WLA baru.');
            }

            $legacyFrequencyUnit = (string) $activity->getRawOriginal('frequency_unit');
            $legacyVolumeUnit = (string) $activity->getRawOriginal('volume_unit');

            if ($legacyFrequencyUnit === $legacyVolumeUnit
                && WlaPeriodUnit::tryFrom($legacyFrequencyUnit) !== null
                && $periodUnit->value !== $legacyFrequencyUnit) {
                throw new InvalidArgumentException('period_unit baru bertentangan dengan unit legacy yang konsisten.');
            }

            if ($activity->getRawOriginal('time_unit') === 'Hour') {
                $legacyTimeScaled = $this->tryPositiveScaledDecimal(
                    $activity->getRawOriginal('time_allocated'),
                    2,
                    WlaActivity::MAX_INPUT_VALUE,
                    'time_allocated legacy',
                );
                $legacyTime = $legacyTimeScaled === null
                    ? null
                    : $this->formatScaledInteger($legacyTimeScaled, 2);

                if ($legacyTime !== null && $legacyTime !== $time) {
                    throw new InvalidArgumentException('time_allocated_hours bertentangan dengan time_allocated legacy.');
                }
            }
        } catch (ValidationException|InvalidArgumentException $exception) {
            return $this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::Conflict,
                $exception instanceof ValidationException
                    ? $this->firstValidationError($exception)
                    : $exception->getMessage(),
            );
        }

        return [
            ...$this->activityClassification(
                $activity,
                WlaLegacyBackfillCategory::AlreadyMigrated,
                'Seluruh field WLA baru sudah terisi dan konsisten.',
            ),
            'annual_workload' => $expectedAnnualWorkload,
        ];
    }

    /**
     * @param  array<string, mixed>  $environment
     * @param  array<int, array<string, mixed>>  $activityResults
     */
    private function validateAggregate(array $environment, array $activityResults): void
    {
        $totalScaled = 0;

        foreach ($activityResults as $activityResult) {
            if (! isset($activityResult['annual_workload'])) {
                continue;
            }

            $annualScaled = $this->positiveScaledDecimal(
                $activityResult['annual_workload'],
                4,
                '922337203685477.5807',
                'annual workload',
            );
            $totalScaled = $this->addChecked($totalScaled, $annualScaled);
        }

        $total = $this->formatScaledInteger($totalScaled, 4);
        $this->wlaCalculationService->calculateFte($total, $environment['effective_hours']);
        $this->wlaCalculationService->calculateRecommendedEmployees($total, $environment['effective_hours']);
    }

    /** @param array<int, array<string, mixed>> $activityResults */
    private function highestBlockingCategory(array $activityResults): ?WlaLegacyBackfillCategory
    {
        $category = null;

        foreach ($activityResults as $result) {
            if ($result['category']->requiresReview()) {
                $category = $this->higherCategory($category, $result['category']);
            }
        }

        return $category;
    }

    private function higherCategory(
        ?WlaLegacyBackfillCategory $current,
        WlaLegacyBackfillCategory $candidate,
    ): WlaLegacyBackfillCategory {
        $priority = [
            WlaLegacyBackfillCategory::NeedsReview->value => 1,
            WlaLegacyBackfillCategory::Invalid->value => 2,
            WlaLegacyBackfillCategory::Conflict->value => 3,
        ];

        if ($current === null || $priority[$candidate->value] > $priority[$current->value]) {
            return $candidate;
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $environment
     * @param  array<int, array<string, mixed>>  $activityResults
     */
    private function assessmentBlockedReason(array $environment, array $activityResults): string
    {
        if ($environment['category']->requiresReview()) {
            return $environment['reason'];
        }

        $blocked = collect($activityResults)
            ->filter(fn (array $result): bool => $result['category']->requiresReview())
            ->map(fn (array $result): string => "Aktivitas {$result['id']}: {$result['reason']}")
            ->implode('; ');

        return $blocked !== '' ? $blocked : 'Assessment memerlukan review.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $scheduleResults
     * @param  array<int, array<string, mixed>>  $assessmentResults
     * @return array<string, mixed>
     */
    private function report(
        array $scheduleResults,
        array $assessmentResults,
        int $schedulesApplied,
        int $assessmentsBackfilled,
    ): array {
        $activityResults = collect($assessmentResults)
            ->flatMap(fn (array $assessment): array => $assessment['activities'])
            ->values()
            ->all();
        $unresolvedCategories = [
            WlaLegacyBackfillCategory::NeedsReview,
            WlaLegacyBackfillCategory::Invalid,
            WlaLegacyBackfillCategory::Conflict,
        ];
        $allResults = collect([...$scheduleResults, ...$assessmentResults, ...$activityResults]);
        $reviewDetails = $allResults
            ->filter(fn (array $result): bool => in_array($result['category'], $unresolvedCategories, true))
            ->map(fn (array $result): array => [
                'type' => $result['type'],
                'id' => $result['id'],
                'assessment_id' => $result['assessment_id'] ?? null,
                'category' => $result['category']->value,
                'reason' => $result['reason'],
            ])
            ->values()
            ->all();
        $details = $allResults
            ->reject(fn (array $result): bool => $result['category'] === WlaLegacyBackfillCategory::Ready)
            ->map(fn (array $result): array => [
                'type' => $result['type'],
                'id' => $result['id'],
                'assessment_id' => $result['assessment_id'] ?? null,
                'category' => $result['category']->value,
                'reason' => $result['reason'],
            ])
            ->values()
            ->all();

        return [
            'schedules' => $scheduleResults,
            'assessments' => $assessmentResults,
            'activities' => $activityResults,
            'details' => $details,
            'has_review' => $reviewDetails !== [],
            'summary' => [
                'schedule_ready' => $this->countCategory($scheduleResults, WlaLegacyBackfillCategory::Ready),
                'schedule_already_migrated' => $this->countCategory($scheduleResults, WlaLegacyBackfillCategory::AlreadyMigrated),
                'schedule_review_or_conflict' => collect($scheduleResults)
                    ->filter(fn (array $result): bool => $result['category']->requiresReview())
                    ->count(),
                'schedule_applied' => $schedulesApplied,
                'assessment_ready' => $this->countCategory($assessmentResults, WlaLegacyBackfillCategory::Ready),
                'assessment_backfilled' => $assessmentsBackfilled,
                'assessment_skipped' => collect($assessmentResults)
                    ->reject(fn (array $result): bool => $result['category'] === WlaLegacyBackfillCategory::Ready)
                    ->count(),
                'activity_ready' => $this->countCategory($activityResults, WlaLegacyBackfillCategory::Ready),
                'activity_already_migrated' => $this->countCategory($activityResults, WlaLegacyBackfillCategory::AlreadyMigrated),
                'activity_needs_review' => $this->countCategory($activityResults, WlaLegacyBackfillCategory::NeedsReview),
                'activity_invalid_or_conflict' => collect($activityResults)
                    ->filter(fn (array $result): bool => in_array(
                        $result['category'],
                        [WlaLegacyBackfillCategory::Invalid, WlaLegacyBackfillCategory::Conflict],
                        true,
                    ))
                    ->count(),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $results */
    private function countCategory(array $results, WlaLegacyBackfillCategory $category): int
    {
        return collect($results)
            ->where('category', $category)
            ->count();
    }

    /** @return array<string, mixed> */
    private function classification(
        string $type,
        int $id,
        ?int $assessmentId,
        WlaLegacyBackfillCategory $category,
        string $reason,
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'assessment_id' => $assessmentId,
            'category' => $category,
            'reason' => $reason,
        ];
    }

    /** @return array<string, mixed> */
    private function activityClassification(
        WlaActivity $activity,
        WlaLegacyBackfillCategory $category,
        string $reason,
    ): array {
        return $this->classification(
            'activity',
            $activity->id,
            $activity->wla_assessment_id,
            $category,
            $reason,
        );
    }

    private function positiveScaledDecimal(
        mixed $value,
        int $decimalPlaces,
        string $maximum,
        string $label,
    ): int {
        $normalizedMaximum = $this->normalizedDecimal(
            $maximum,
            $decimalPlaces,
            18,
            "batas {$label}",
        );
        [$maximumWhole, $maximumFraction] = explode('.', $normalizedMaximum);
        $normalized = $this->normalizedDecimal(
            $value,
            $decimalPlaces,
            strlen($maximumWhole),
            $label,
        );
        [$whole, $fraction] = explode('.', $normalized);
        $scale = 10 ** $decimalPlaces;

        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && $whole > $maximumWhole)
            || ($whole === $maximumWhole && $fraction > $maximumFraction)) {
            throw new InvalidArgumentException("{$label} melampaui batas yang didukung.");
        }

        $scaled = ((int) $whole * $scale) + (int) $fraction;
        $maximumScaled = ((int) $maximumWhole * $scale) + (int) $maximumFraction;

        if ($scaled < 1) {
            throw new InvalidArgumentException("{$label} harus lebih besar dari nol.");
        }

        if ($scaled > $maximumScaled) {
            throw new InvalidArgumentException("{$label} melampaui batas yang didukung.");
        }

        return $scaled;
    }

    private function tryPositiveScaledDecimal(
        mixed $value,
        int $decimalPlaces,
        string $maximum,
        string $label,
    ): ?int {
        try {
            return $this->positiveScaledDecimal($value, $decimalPlaces, $maximum, $label);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function normalizedDecimal(
        mixed $value,
        int $decimalPlaces,
        int $maximumWholeDigits,
        string $label,
    ): string {
        $value = trim((string) $value);

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException("{$label} bukan angka desimal non-negatif yang valid.");
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > $decimalPlaces) {
            throw new InvalidArgumentException("{$label} memiliki presisi lebih dari {$decimalPlaces} desimal.");
        }

        if (strlen($whole) > $maximumWholeDigits) {
            throw new InvalidArgumentException("{$label} melampaui kapasitas schema.");
        }

        return $whole.'.'.str_pad($fraction, $decimalPlaces, '0');
    }

    private function multiplyChecked(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new InvalidArgumentException('Perkalian data legacy melampaui rentang fixed-point.');
        }

        return $left * $right;
    }

    private function addChecked(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('Total beban assessment melampaui rentang fixed-point.');
        }

        return $left + $right;
    }

    private function formatScaledInteger(int $value, int $decimalPlaces): string
    {
        $scale = 10 ** $decimalPlaces;

        return intdiv($value, $scale).'.'.str_pad(
            (string) ($value % $scale),
            $decimalPlaces,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function firstValidationError(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : 'Validasi kalkulasi WLA gagal.';
    }
}
