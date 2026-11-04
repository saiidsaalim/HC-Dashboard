<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_demotion_id', 'approval_round', 'step_order', 'manager_id', 'approver_name_snapshot', 'approver_role_snapshot', 'approver_number_snapshot', 'status', 'note', 'approved_at'])]
class DemotionApproval extends Model
{
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'approval_round' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function demotion(): BelongsTo
    {
        return $this->belongsTo(EmployeeDemotion::class, 'employee_demotion_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
