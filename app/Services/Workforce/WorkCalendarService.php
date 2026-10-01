<?php

namespace App\Services\Workforce;

use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Validation\ValidationException;

class WorkCalendarService
{
    public function calculateWorkingDays(?WorkCalendar $calendar, ?WorkSchedule $schedule): int
    {
        $calendar = $this->requireCalendar($calendar);
        $schedule = $this->requireSchedule($schedule);
        $isDayshift = $schedule->code === config('workforce.dayshift_schedule_code');
        $totalDays = $calendar->total_days;
        $annualLeave = $calendar->annual_leave;
        $commonLeave = $calendar->common_leave;
        $nationalHoliday = $isDayshift ? $calendar->national_holiday : 0;
        $saturdayDays = $isDayshift ? $calendar->saturday_days : 0;
        $sundayDays = $isDayshift ? $calendar->sunday_days : 0;

        $workingDays = $totalDays - $annualLeave - $nationalHoliday - $commonLeave - $saturdayDays - $sundayDays;

        return max(0, $workingDays);
    }

    public function calculateWorkingHoursPerYear(?WorkCalendar $calendar, ?WorkSchedule $schedule): string
    {
        $workingDays = $this->calculateWorkingDays($calendar, $schedule);
        $schedule = $this->requireSchedule($schedule);
        $dailyHoursHundredths = $this->decimalToScaledInteger($schedule->working_hours_per_day, 2, 'working_hours_per_day');
        $annualHoursHundredths = $workingDays * $dailyHoursHundredths;

        return $this->formatHundredths($annualHoursHundredths);
    }

    public function calculateEffectiveWorkingHours(string|int|float $workingHours, string|int|float $efficiencyFactor): string
    {
        $workingHoursHundredths = $this->decimalToScaledInteger($workingHours, 2, 'working_hours');
        $efficiencyTenThousandths = $this->efficiencyFactorToTenThousandths($efficiencyFactor);

        $effectiveHundredths = intdiv(($workingHoursHundredths * $efficiencyTenThousandths) + 5000, 10000);

        return $this->formatHundredths($effectiveHundredths);
    }

    public function calculateEffectiveHoursPerMonth(string|int|float $effectiveWorkingHours): string
    {
        return $this->divideHundredths($this->decimalToScaledInteger($effectiveWorkingHours, 2, 'effective_working_hours'), 12);
    }

    public function calculateEffectiveHoursPerWeek(string|int|float $effectiveWorkingHours, ?WorkCalendar $calendar): string
    {
        $calendar = $this->requireCalendar($calendar);

        if ($calendar->total_weeks < 1) {
            throw ValidationException::withMessages([
                'total_weeks' => 'Work Calendar must contain at least one week.',
            ]);
        }

        return $this->divideHundredths($this->decimalToScaledInteger($effectiveWorkingHours, 2, 'effective_working_hours'), $calendar->total_weeks);
    }

    public function calculateEffectiveHoursPerDay(string|int|float $effectiveWorkingHours, ?WorkCalendar $calendar, ?WorkSchedule $schedule): string
    {
        $workingDays = $this->calculateWorkingDays($calendar, $schedule);

        if ($workingDays < 1) {
            throw ValidationException::withMessages([
                'working_days' => 'Effective hours per day cannot be calculated when working days are zero.',
            ]);
        }

        return $this->divideHundredths($this->decimalToScaledInteger($effectiveWorkingHours, 2, 'effective_working_hours'), $workingDays);
    }

    private function requireCalendar(?WorkCalendar $calendar): WorkCalendar
    {
        if ($calendar === null) {
            throw ValidationException::withMessages([
                'work_calendar_id' => 'Select a valid Work Calendar.',
            ]);
        }

        return $calendar;
    }

    private function requireSchedule(?WorkSchedule $schedule): WorkSchedule
    {
        if ($schedule === null) {
            throw ValidationException::withMessages([
                'work_schedule_id' => 'Select a valid Work Schedule.',
            ]);
        }

        return $schedule;
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

        return $this->formatTenThousandths($quotient);
    }

    private function formatHundredths(int $value): string
    {
        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    private function formatTenThousandths(int $value): string
    {
        return intdiv($value, 10000).'.'.str_pad((string) ($value % 10000), 4, '0', STR_PAD_LEFT);
    }
}
