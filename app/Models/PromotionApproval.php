<?php

namespace App\Models;

use App\Enums\PromotionApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_promotion_id',
    'approval_round',
    'step_order',
    'manager_id',
    'status',
    'note',
    'approved_at',
    'returned_at',
    'approver_name_snapshot',
    'approver_role_snapshot',
    'approver_number_snapshot',
])]
class PromotionApproval extends Model
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'approval_round' => 'integer',
            'step_order' => 'integer',
            'status' => PromotionApprovalStatus::class,
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(EmployeePromotion::class, 'employee_promotion_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
