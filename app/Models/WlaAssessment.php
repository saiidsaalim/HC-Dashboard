<?php

namespace App\Models;

use App\Enums\WlaAssessmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'assessment_code', 'period', 'department_id', 'unit_id', 'position_id',
    'work_schedule_id', 'work_calendar_id', 'efficiency_factor', 'status',
    'created_by', 'updated_by', 'working_days', 'working_hours_year', 'effective_working_hours',
])]
class WlaAssessment extends Model
{
    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'efficiency_factor' => 'decimal:4',
            'status' => WlaAssessmentStatus::class,
            'working_days' => 'integer',
            'working_hours_year' => 'decimal:2',
            'effective_working_hours' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function workCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WlaActivity::class)->orderBy('sort_order')->orderBy('id');
    }
}
