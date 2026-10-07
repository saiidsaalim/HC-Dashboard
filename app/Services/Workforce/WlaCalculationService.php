<?php

namespace App\Services\Workforce;

use App\Enums\WlaPeriodUnit;
use App\Models\WlaAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WlaCalculationService
{
    private const int MAX_STORED_FTE_SCALED = 9999999999999999;

    private const int MAX_RECOMMENDED_EMPLOYEES = 4294967295;

    public function __construct(private WorkCalendarService $workCalendarService) {}

    public function periodFactor(WlaPeriodUnit $periodUnit, int $workingDays): int
    {
        return match ($periodUnit) {
            WlaPeriodUnit::Day => $workingDays,
            WlaPeriodUnit::Week => 52,
            WlaPeriodUnit::Month => 12,
            WlaPeriodUnit::Year => 1,
        };
    }

    public function calculateAnnualWorkload(
        string|int|float $frequency,
        string|int|float $timeAllocatedHours,
        WlaPeriodUnit $periodUnit,
        int $workingDays,
    ): string {
        $frequencyHundredths = $this->positiveHundredths($frequency, 'frequency');
        $timeHundredths = $this->positiveHundredths($timeAllocatedHours, 'time_allocated_hours');
        $factor = $this->periodFactor($periodUnit, $workingDays);
        $workloadTenThousandths = $this->multiplyChecked(
            $this->multiplyChecked($frequencyHundredths, $timeHundredths),
            $factor,
        );

        return $this->formatScaledInteger($workloadTenThousandths, 4);
    }

    public function calculateFte(
        string|int|float $totalAnnualWorkload,
        string|int|float $effectiveAnnualWorkingHours,
    ): string {
        $workloadTenThousandths = $this->decimalToScaledInteger(
            $totalAnnualWorkload,
            4,
            'total_annual_workload_hours',
        );
        $effectiveHundredths = $this->positiveHundredths(
            $effectiveAnnualWorkingHours,
            'effective_working_hours',
        );
        $denominator = $this->multiplyChecked($effectiveHundredths, 100);

        $fteScaled = $this->divideAndRound($workloadTenThousandths, $denominator, 6);

        if ($fteScaled > self::MAX_STORED_FTE_SCALED) {
            throw ValidationException::withMessages([
                'calculation' => 'Hasil FTE melebihi kapasitas penyimpanan.',
            ]);
        }

        return $this->formatScaledInteger($fteScaled, 6);
    }

    public function calculateRecommendedEmployees(
        string|int|float $totalAnnualWorkload,
        string|int|float $effectiveAnnualWorkingHours,
    ): int {
        $workloadTenThousandths = $this->decimalToScaledInteger(
            $totalAnnualWorkload,
            4,
            'total_annual_workload_hours',
        );
        $effectiveHundredths = $this->positiveHundredths(
            $effectiveAnnualWorkingHours,
            'effective_working_hours',
        );
        $denominator = $this->multiplyChecked($effectiveHundredths, 100);
        $wholeEmployees = intdiv($workloadTenThousandths, $denominator);

        $recommendedEmployees = $wholeEmployees + ($workloadTenThousandths % $denominator === 0 ? 0 : 1);

        if ($recommendedEmployees > self::MAX_RECOMMENDED_EMPLOYEES) {
            throw ValidationException::withMessages([
                'calculation' => 'Rekomendasi pegawai melebihi kapasitas penyimpanan.',
            ]);
        }

        return $recommendedEmployees;
    }

    public function recalculate(WlaAssessment $assessment): WlaAssessment
    {
        return DB::transaction(function () use ($assessment): WlaAssessment {
            $lockedAssessment = WlaAssessment::query()
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());
            $lockedAssessment->load(['workCalendar', 'workSchedule']);
            $activities = $lockedAssessment->activities()->lockForUpdate()->get();

            $workingDays = $this->workCalendarService->calculateWorkingDays(
                $lockedAssessment->period,
                $lockedAssessment->workCalendar,
                $lockedAssessment->workSchedule,
            );
            $workingHoursPerYear = $this->workCalendarService->calculateWorkingHoursPerYear(
                $lockedAssessment->period,
                $lockedAssessment->workCalendar,
                $lockedAssessment->workSchedule,
            );
            $effectiveWorkingHours = $this->workCalendarService->calculateEffectiveWorkingHours(
                $workingHoursPerYear,
                $lockedAssessment->efficiency_factor,
            );
            $totalWorkloadTenThousandths = 0;

            foreach ($activities as $activity) {
                if ($activity->period_unit === null || $activity->time_allocated_hours === null) {
                    continue;
                }

                $annualWorkload = $this->calculateAnnualWorkload(
                    $activity->frequency,
                    $activity->time_allocated_hours,
                    $activity->period_unit,
                    $workingDays,
                );
                $annualWorkloadTenThousandths = $this->decimalToScaledInteger(
                    $annualWorkload,
                    4,
                    'annual_workload_hours',
                );
                $totalWorkloadTenThousandths = $this->addChecked(
                    $totalWorkloadTenThousandths,
                    $annualWorkloadTenThousandths,
                );

                $activity->update(['annual_workload_hours' => $annualWorkload]);
            }

            $totalAnnualWorkload = $this->formatScaledInteger($totalWorkloadTenThousandths, 4);
            $lockedAssessment->update([
                'working_days' => $workingDays,
                'working_hours_year' => $workingHoursPerYear,
                'effective_working_hours' => $effectiveWorkingHours,
                'total_annual_workload_hours' => $totalAnnualWorkload,
                'fte' => $this->calculateFte($totalAnnualWorkload, $effectiveWorkingHours),
                'recommended_employees' => $this->calculateRecommendedEmployees(
                    $totalAnnualWorkload,
                    $effectiveWorkingHours,
                ),
            ]);

            return $lockedAssessment->refresh()->load('activities');
        });
    }

    public function hasActivitiesNeedingReview(WlaAssessment $assessment): bool
    {
        return $assessment->activities()
            ->where(function (Builder $query): void {
                $query->whereNull('period_unit')
                    ->orWhereNull('time_allocated_hours');
            })
            ->exists();
    }

    /**
     * @return array{
     *     calculation_available: bool,
     *     calculation_complete: bool,
     *     has_activities_needing_review: bool,
     *     unavailable_reason: string|null,
     *     total_days: int,
     *     working_days: int|null,
     *     working_hours_year: string|null,
     *     effective_working_hours: string|null,
     *     preview: bool
     * }
     */
    public function calculationState(WlaAssessment $assessment): array
    {
        $hasActivitiesNeedingReview = $this->hasActivitiesNeedingReview($assessment);

        try {
            $calendar = $assessment->workCalendar;
            $schedule = $assessment->workSchedule;
            $workingDays = $this->workCalendarService->calculateWorkingDays(
                $assessment->period,
                $calendar,
                $schedule,
            );
            $workingHoursYear = $this->workCalendarService->calculateWorkingHoursPerYear(
                $assessment->period,
                $calendar,
                $schedule,
            );
            $effectiveWorkingHours = $this->workCalendarService->calculateEffectiveWorkingHours(
                $workingHoursYear,
                $assessment->efficiency_factor,
            );
            $this->calculateFte('0.0000', $effectiveWorkingHours);
            $hasSnapshot = $assessment->working_days !== null
                && $assessment->working_hours_year !== null
                && $assessment->effective_working_hours !== null;

            return [
                'calculation_available' => true,
                'calculation_complete' => ! $hasActivitiesNeedingReview,
                'has_activities_needing_review' => $hasActivitiesNeedingReview,
                'unavailable_reason' => null,
                'total_days' => $this->workCalendarService->calculateTotalDays($assessment->period),
                'working_days' => $hasSnapshot ? $assessment->working_days : $workingDays,
                'working_hours_year' => $hasSnapshot ? $assessment->working_hours_year : $workingHoursYear,
                'effective_working_hours' => $hasSnapshot
                    ? $assessment->effective_working_hours
                    : $effectiveWorkingHours,
                'preview' => ! $hasSnapshot,
            ];
        } catch (ValidationException $exception) {
            $firstError = collect($exception->errors())->flatten()->first();

            return [
                'calculation_available' => false,
                'calculation_complete' => false,
                'has_activities_needing_review' => $hasActivitiesNeedingReview,
                'unavailable_reason' => is_string($firstError)
                    ? $firstError
                    : 'Parameter kalender atau jadwal kerja belum valid.',
                'total_days' => $this->workCalendarService->calculateTotalDays($assessment->period),
                'working_days' => null,
                'working_hours_year' => null,
                'effective_working_hours' => null,
                'preview' => true,
            ];
        }
    }

    private function positiveHundredths(string|int|float $value, string $field): int
    {
        $hundredths = $this->decimalToScaledInteger($value, 2, $field);

        if ($hundredths < 1) {
            throw ValidationException::withMessages([
                $field => 'Enter a value greater than zero.',
            ]);
        }

        return $hundredths;
    }

    private function decimalToScaledInteger(
        string|int|float $value,
        int $decimalPlaces,
        string $field,
    ): int {
        $value = trim((string) $value);

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw ValidationException::withMessages([
                $field => 'Enter a non-negative decimal value.',
            ]);
        }

        $scale = 10 ** $decimalPlaces;
        $fraction = str_pad(substr($matches[2] ?? '', 0, $decimalPlaces), $decimalPlaces, '0');
        $scaledValue = ((int) $matches[1] * $scale) + (int) $fraction;
        $nextDigit = (int) ($matches[2][$decimalPlaces] ?? 0);

        return $scaledValue + ($nextDigit >= 5 ? 1 : 0);
    }

    private function multiplyChecked(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw ValidationException::withMessages([
                'calculation' => 'WLA calculation exceeds the supported numeric range.',
            ]);
        }

        return $left * $right;
    }

    private function addChecked(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw ValidationException::withMessages([
                'calculation' => 'WLA calculation exceeds the supported numeric range.',
            ]);
        }

        return $left + $right;
    }

    private function divideAndRound(int $numerator, int $denominator, int $decimalPlaces): int
    {
        if ($denominator < 1) {
            throw ValidationException::withMessages([
                'effective_working_hours' => 'Effective annual working hours must be greater than zero.',
            ]);
        }

        $scale = 10 ** $decimalPlaces;
        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;
        $scaledRemainder = $this->multiplyChecked($remainder, $scale);
        $fraction = intdiv($scaledRemainder, $denominator);

        if (($scaledRemainder % $denominator) * 2 >= $denominator) {
            $fraction++;
        }

        return $this->addChecked($this->multiplyChecked($whole, $scale), $fraction);
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
}
