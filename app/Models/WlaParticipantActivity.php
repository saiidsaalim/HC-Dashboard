<?php

namespace App\Models;

use App\Enums\WlaPeriodUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'activity_name', 'frequency', 'period_unit', 'time_allocated_hours',
    'annual_workload_hours', 'sort_order', 'notes',
])]
class WlaParticipantActivity extends Model
{
    protected function casts(): array
    {
        return [
            'frequency' => 'decimal:2',
            'period_unit' => WlaPeriodUnit::class,
            'time_allocated_hours' => 'decimal:2',
            'annual_workload_hours' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(WlaParticipant::class, 'wla_participant_id');
    }
}
