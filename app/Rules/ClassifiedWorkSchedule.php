<?php

namespace App\Rules;

use App\Models\WorkSchedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ClassifiedWorkSchedule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $schedule = WorkSchedule::query()->find($value);

        if ($schedule === null || $schedule->calculationTypeForWla() === null) {
            $fail('Pilih jadwal kerja yang valid dan telah diklasifikasikan untuk WLA.');
        }
    }
}
