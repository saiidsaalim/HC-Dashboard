<?php

namespace App\Models;

use Database\Factories\MovementApprovalDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'movement_type', 'movement_id', 'approval_round', 'step_order', 'recipient_id',
    'status', 'queued_at', 'sent_at', 'skipped_at', 'failed_at', 'last_attempted_at',
    'attempts', 'last_error', 'skip_reason',
])]
class MovementApprovalDelivery extends Model
{
    /** @use HasFactory<MovementApprovalDeliveryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'movement_id' => 'integer',
            'approval_round' => 'integer',
            'step_order' => 'integer',
            'recipient_id' => 'integer',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'failed_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }
}
