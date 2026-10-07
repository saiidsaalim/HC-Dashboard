<?php

namespace App\Services\Workforce;

use App\Enums\WorkScheduleCalculationType;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Validation\ValidationException;

class WorkCalendarService
{
    public function calculateTotalDays(int $assessmentYear): int
    {
        return ($assessmentYear % 400 === 0 || ($assessmentYear % 4 === 0 && $assessmentYear % 100 !== 0))
            ? 366
            : 365;
    }

    public function calculateWorkingDays(
        int $assessmentYear,
        ?WorkCalendar $calendar,
        ?WorkSchedule $schedule,
    ): int {
        $calendar = $this->requireCalendarForYear($assessmentYear, $calendar);
        $calculationType = $this->requireCalculationType($schedule);
        $totalDays = $this->calculateTotalDays($assessmentYear);
        $nationalHoliday = $calculationType === WorkScheduleCalculationType::Dayshift
            ? $calendar->national_holiday
            : 0;
        $saturdayDays = $calculationType === WorkScheduleCalculationType::Dayshift
            ? $calendar->saturday_days
            : 0;
        $sundayDays = $calculationType === WorkScheduleCalculationType::Dayshift
            ? $calendar->sunday_days
            : 0;

        $workingDays = $totalDays
            - $calendar->annual_leave
            - $nationalHoliday
            - $calendar->common_leave
            - $saturdayDays
            - $sundayDays;

        return max(0, $workingDays);
    }

    public function calculateWorkingHoursPerYear(
        int $assessmentYear,
        ?WorkCalendar $calendar,
        ?WorkSchedule $schedule,
    ): string {
        $workingDays = $this->calculateWorkingDays($assessmentYear, $calendar, $schedule);
        $calculationType = $this->requireCalculationType($schedule);
        $dailyHoursHundredths = $this->decimalToScaledInteger(
            $calculationType->workingHoursPerDay(),
            2,
            'working_hours_per_day',
        );

        return $this->formatScaledInteger($workingDays * $dailyHoursHundredths, 2);
    }

    public function calculateEffectiveWorkingHours(
        string|int|float $workingHours,
        string|int|float $efficiencyFactor,
    ): string {
        $workingHoursHundredths = $this->decimalToScaledInteger($workingHours, 2, 'working_hours');
        $efficiencyTenThousandths = $this->efficiencyFactorToTenThousandths($efficiencyFactor);
        $effectiveHundredths = intdiv(
            ($workingHoursHundredths * $efficiencyTenThousandths) + 5000,
            10000,
        );

        return $this->formatScaledInteger($effectiveHundredths, 2);
    }

    public function calculateEffectiveHoursPerMonth(string|int|float $effectiveWorkingHours): string
    {
        return $this->divideHundredths(
            $this->decimalToScaledInteger($effectiveWorkingHours, 2, 'effective_working_hours'),
            12,
        );
    }

    public function calculateEffectiveHoursPerWeek(string|int|float $effectiveWorkingHours): string
    {
        return $this->divideHundredths(
            $this->decimalToScaledInteger($effectiveWorkingHours, 2, 'effective_working_hours'),
            52,
        );
    }

    public function calculateEffectiveHoursPerDay(
        string|int|float $effectiveWorkingHours,
        int $assessmentYear,
        ?WorkCalendar $calendar,
        ?WorkSchedule $schedule,
    ): string {
        $workingDays = $this->calculateWorkingDays($assessmentYear, $calendar, $schedule);

        if ($workingDays < 1) {
            throw ValidationException::withMessages([
                'working_days' => 'Effective hours per day cannot be calculated when working days are zero.',
            ]);
        }

        return $this->divideHundredths(
            $this->decimalToScaledInteger($effectiveWorkingHours, 2, 'effective_working_hours'),
            $workingDays,
        );
    }

    private function requireCalendarForYear(int $assessmentYear, ?WorkCalendar $calendar): WorkCalendar
    {
        if ($calendar === null) {
            throw ValidationException::withMessages([
                'work_calendar_id' => 'Select a valid Work Calendar.',
            ]);
        }

        if ($calendar->year !== $assessmentYear) {
            throw ValidationException::withMessages([
                'work_calendar_id' => 'Work Calendar year must match the assessment period.',
            ]);
        }

        return $calendar;
    }

    private function requireCalculationType(?WorkSchedule $schedule): WorkScheduleCalculationType
    {
        if ($schedule === null) {
            throw ValidationException::withMessages([
                'work_schedule_id' => 'Select a valid Work Schedule.',
            ]);
        }

        $calculationType = $schedule->calculationTypeForWla();

        if ($calculationType === null) {
            throw ValidationException::withMessages([
                'work_schedule_id' => 'Jadwal kerja belum diklasifikasikan untuk kalkulasi WLA.',
            ]);
        }

        return $calculationType;
    }

    private function decimalToScaledInteger(string|int|float $value, int $decimalPlaces, string $field): int
    {
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

    private function efficiencyFactorToTenThousandths(string|int|float $value): int
    {
        $value = trim((string) $value);

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw ValidationException::withMessages([
                'efficiency_factor' => 'Efficiency factor must be between 0 and 1.',
            ]);
        }

        $whole = (int) $matches[1];
        $fraction = $matches[2] ?? '';

        if ($whole > 1 || ($whole === 1 && trim($fraction, '0') !== '')) {
            throw ValidationException::withMessages([
                'efficiency_factor' => 'Efficiency factor must be between 0 and 1.',
            ]);
        }

        return $this->decimalToScaledInteger($value, 4, 'efficiency_factor');
    }

    private function divideHundredths(int $hundredths, int $divisor): string
    {
        $numerator = $hundredths * 100;
        $quotient = intdiv($numerator, $divisor);
        $remainder = $numerator % $divisor;

        if ($remainder * 2 >= $divisor) {
            $quotient++;
        }

        return $this->formatScaledInteger($quotient, 4);
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
