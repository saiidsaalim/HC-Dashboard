<?php

namespace App\Models;

use App\Enums\WorkScheduleCalculationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'schedule_type', 'calculation_type', 'working_hours_per_day', 'working_days_per_week', 'active', 'description'])]
class WorkSchedule extends Model
{
    protected $casts = [
        'active' => 'boolean',
        'calculation_type' => WorkScheduleCalculationType::class,
        'working_hours_per_day' => 'decimal:2',
        'working_days_per_week' => 'integer',
    ];

    public function calculationTypeForWla(): ?WorkScheduleCalculationType
    {
        return $this->calculation_type
            ?? WorkScheduleCalculationType::fromLegacyCode($this->code);
    }

    public function wlaAssessments(): HasMany
    {
        return $this->hasMany(WlaAssessment::class);
    }

    protected function workingHoursPerWeek(): Attribute
    {
        return Attribute::get(function (): string {
            $hours = (string) $this->working_hours_per_day;
            [$whole, $fraction] = array_pad(explode('.', $hours, 2), 2, '');
            $dailyHundredths = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
            $weeklyHundredths = $dailyHundredths * (int) $this->working_days_per_week;

            return intdiv($weeklyHundredths, 100).'.'.str_pad((string) ($weeklyHundredths % 100), 2, '0', STR_PAD_LEFT);
        });
    }
}
