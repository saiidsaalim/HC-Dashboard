<?php

namespace App\Services\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Models\User;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WlaFinalizationService
{
    public function __construct(
        private WlaCalculationService $wlaCalculationService,
        private WorkCalendarService $workCalendarService,
    ) {}

    public function finalize(WlaAssessment $assessment, User $user): WlaAssessment
    {
        try {
            return DB::transaction(function () use ($assessment, $user): WlaAssessment {
                $lockedAssessment = WlaAssessment::query()
                    ->lockForUpdate()
                    ->findOrFail($assessment->getKey());
                Gate::forUser($user)->authorize('finalize', $lockedAssessment);

                if ($lockedAssessment->status !== WlaAssessmentStatus::Draft) {
                    throw ValidationException::withMessages([
                        'finalization' => 'Hanya WLA Draft yang dapat difinalisasi.',
                    ]);
                }

                $activities = $lockedAssessment->activities()
                    ->reorder('id')
                    ->lockForUpdate()
                    ->get();
                $lockedAssessment->setRelation('activities', $activities);

                $this->validateActivities($activities);
                $this->validateMasters($lockedAssessment);
                $this->ensureNoExistingFinal($lockedAssessment);

                $recalculatedAssessment = $this->wlaCalculationService->recalculate($lockedAssessment);
                $recalculatedAssessment->load([
                    'department',
                    'unit',
                    'position',
                    'workSchedule',
                    'workCalendar',
                    'activities',
                ]);
                $calculationState = $this->wlaCalculationService->calculationState($recalculatedAssessment);

                if (! $calculationState['calculation_available']) {
                    throw ValidationException::withMessages([
                        'finalization' => $calculationState['unavailable_reason']
                            ?? 'Kalkulasi WLA belum tersedia.',
                    ]);
                }

                if (! $calculationState['calculation_complete']) {
                    throw ValidationException::withMessages([
                        'finalization' => 'Seluruh aktivitas harus direview sebelum WLA difinalisasi.',
                    ]);
                }

                if (! $this->isPositiveDecimal($recalculatedAssessment->effective_working_hours)) {
                    throw ValidationException::withMessages([
                        'finalization' => 'Jam kerja efektif tahunan harus lebih dari nol.',
                    ]);
                }

                $finalizedAt = now();
                $recalculatedAssessment->forceFill([
                    'finalized_at' => $finalizedAt,
                    'finalized_by' => $user->getKey(),
                    'finalization_key' => $this->finalizationKey($recalculatedAssessment),
                    'final_snapshot' => $this->snapshot($recalculatedAssessment, $user, $finalizedAt),
                    'status' => WlaAssessmentStatus::Final,
                ])->save();

                return $recalculatedAssessment->refresh()->load('finalizer');
            });
        } catch (QueryException $exception) {
            if ($this->isFinalizationKeyViolation($exception)) {
                throw ValidationException::withMessages([
                    'finalization' => 'Posisi ini sudah memiliki WLA Final pada periode tersebut.',
                ]);
            }

            throw $exception;
        }
    }

    /** @param Collection<int, WlaActivity> $activities */
    private function validateActivities(Collection $activities): void
    {
        if ($activities->isEmpty()) {
            throw ValidationException::withMessages([
                'finalization' => 'WLA harus mempunyai minimal satu aktivitas sebelum difinalisasi.',
            ]);
        }

        if ($activities->contains(
            fn (WlaActivity $activity): bool => $activity->period_unit === null
                || $activity->time_allocated_hours === null,
        )) {
            throw ValidationException::withMessages([
                'finalization' => 'Seluruh aktivitas harus direview sebelum WLA difinalisasi.',
            ]);
        }
    }

    private function validateMasters(WlaAssessment $assessment): void
    {
        $assessment->load(['department', 'unit', 'position', 'workSchedule', 'workCalendar']);

        foreach ([
            'department' => 'Department',
            'unit' => 'Unit',
            'position' => 'Position',
            'workSchedule' => 'Jadwal kerja',
            'workCalendar' => 'Kalender kerja',
        ] as $relation => $label) {
            $master = $assessment->getRelation($relation);

            if ($master === null) {
                throw ValidationException::withMessages([
                    'finalization' => "{$label} tidak tersedia.",
                ]);
            }

            if (! $master->active) {
                throw ValidationException::withMessages([
                    'finalization' => "{$label} harus aktif sebelum WLA difinalisasi.",
                ]);
            }
        }

        if ($assessment->unit->department_id !== $assessment->department_id
            || $assessment->position->unit_id !== $assessment->unit_id) {
            throw ValidationException::withMessages([
                'finalization' => 'Struktur department, unit, dan position WLA tidak valid.',
            ]);
        }

        if ($assessment->workSchedule->calculationTypeForWla() === null) {
            throw ValidationException::withMessages([
                'finalization' => 'Jadwal kerja belum diklasifikasikan untuk kalkulasi WLA.',
            ]);
        }

        if ($assessment->workCalendar->year !== $assessment->period) {
            throw ValidationException::withMessages([
                'finalization' => 'Tahun kalender kerja harus sama dengan periode WLA.',
            ]);
        }
    }

    private function ensureNoExistingFinal(WlaAssessment $assessment): void
    {
        if (WlaAssessment::query()
            ->where('status', WlaAssessmentStatus::Final->value)
            ->where('period', $assessment->period)
            ->where('position_id', $assessment->position_id)
            ->whereKeyNot($assessment->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'finalization' => 'Posisi ini sudah memiliki WLA Final pada periode tersebut.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(WlaAssessment $assessment, User $user, Carbon $finalizedAt): array
    {
        $calculationType = $assessment->workSchedule->calculationTypeForWla();

        return [
            'assessment_code' => $assessment->assessment_code,
            'period' => $assessment->period,
            'department' => [
                'id' => $assessment->department->id,
                'code' => $assessment->department->code,
                'name' => $assessment->department->name,
            ],
            'unit' => [
                'id' => $assessment->unit->id,
                'code' => $assessment->unit->code,
                'name' => $assessment->unit->name,
            ],
            'position' => [
                'id' => $assessment->position->id,
                'code' => $assessment->position->code,
                'name' => $assessment->position->name,
            ],
            'schedule' => [
                'id' => $assessment->workSchedule->id,
                'code' => $assessment->workSchedule->code,
                'name' => $assessment->workSchedule->name,
                'calculation_type' => $calculationType?->value,
                'calculation_type_label' => $calculationType?->label(),
                'wla_hours_per_day' => $calculationType?->workingHoursPerDay(),
            ],
            'calendar' => [
                'id' => $assessment->workCalendar->id,
                'year' => $assessment->workCalendar->year,
                'total_days' => $this->workCalendarService->calculateTotalDays($assessment->period),
                'annual_leave' => $assessment->workCalendar->annual_leave,
                'national_holiday' => $assessment->workCalendar->national_holiday,
                'common_leave' => $assessment->workCalendar->common_leave,
                'saturday_days' => $assessment->workCalendar->saturday_days,
                'sunday_days' => $assessment->workCalendar->sunday_days,
            ],
            'efficiency_factor' => (string) $assessment->efficiency_factor,
            'working_days' => $assessment->working_days,
            'annual_working_hours' => (string) $assessment->working_hours_year,
            'effective_annual_working_hours' => (string) $assessment->effective_working_hours,
            'activities' => $assessment->activities
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn (WlaActivity $activity): array => [
                    'activity_name' => $activity->activity_name,
                    'frequency' => (string) $activity->frequency,
                    'period_unit' => $activity->period_unit?->value,
                    'time_allocated_hours' => (string) $activity->time_allocated_hours,
                    'annual_workload_hours' => (string) $activity->annual_workload_hours,
                    'sort_order' => $activity->sort_order,
                    'notes' => $activity->notes,
                ])->all(),
            'total_annual_workload' => (string) $assessment->total_annual_workload_hours,
            'fte' => (string) $assessment->fte,
            'recommended_employees' => $assessment->recommended_employees,
            'finalizer' => [
                'id' => $user->getKey(),
                'name' => $user->name,
            ],
            'finalized_at' => $finalizedAt->toIso8601String(),
        ];
    }

    private function finalizationKey(WlaAssessment $assessment): string
    {
        return "{$assessment->period}:{$assessment->position_id}";
    }

    private function isPositiveDecimal(string|int|float|null $value): bool
    {
        return $value !== null
            && preg_match('/[1-9]/', (string) $value) === 1;
    }

    private function isFinalizationKeyViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());
        $uniqueViolation = in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, [19, 1062], true);

        return $uniqueViolation && str_contains($message, 'finalization_key');
    }
}
