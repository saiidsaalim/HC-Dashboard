<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_mutation_id', 'approval_round', 'step_order', 'manager_id', 'approver_name_snapshot', 'approver_role_snapshot', 'approver_number_snapshot', 'submission_snapshot', 'status', 'note', 'approved_at', 'returned_at', 'cancelled_at'])]
class MutationApproval extends Model
{
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'approval_round' => 'integer',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'submission_snapshot' => 'array',
        ];
    }

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(EmployeeMutation::class, 'employee_mutation_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
