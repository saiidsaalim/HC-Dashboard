<?php

namespace App\Models;

use App\Enums\WlaParticipantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'wla_assessment_id', 'employee_id', 'assigned_by', 'status', 'submitted_at',
    'reviewed_at', 'reviewed_by', 'review_note', 'applied_at', 'applied_by',
])]
class WlaParticipant extends Model
{
    protected function casts(): array
    {
        return [
            'status' => WlaParticipantStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(WlaAssessment::class, 'wla_assessment_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WlaParticipantActivity::class)->orderBy('sort_order')->orderBy('id');
    }
}
