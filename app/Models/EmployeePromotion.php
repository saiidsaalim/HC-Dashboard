<?php

namespace App\Models;

use App\Enums\PromotionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sap',
    'nama',
    'departemen_lama',
    'departemen_baru',
    'jabatan_lama',
    'jabatan_baru',
    'tmt',
    'pg',
    'band_lama',
    'jg_lama',
    'band_baru',
    'jg_baru',
    'verification_status',
    'approval_round',
    'current_approval_step',
    'submitted_by',
    'submitted_at',
    'finalized_at',
])]
class EmployeePromotion extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sap', 'sap');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PromotionApproval::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function statusEnum(): PromotionStatus
    {
        return PromotionStatus::tryFrom($this->verification_status) ?? PromotionStatus::LegacyPending;
    }

    public function isEditable(): bool
    {
        return $this->statusEnum()->canBeEdited();
    }

    protected function casts(): array
    {
        return [
            'tmt' => 'date',
            'approval_round' => 'integer',
            'current_approval_step' => 'integer',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }
}
