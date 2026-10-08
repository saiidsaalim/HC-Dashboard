<?php

namespace App\Models;

use App\Enums\WlaFrequencyUnit;
use App\Enums\WlaPeriodUnit;
use App\Enums\WlaTimeUnit;
use App\Enums\WlaVolumeUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'activity_name', 'frequency', 'period_unit', 'frequency_unit', 'volume', 'volume_unit',
    'time_allocated', 'time_unit', 'time_allocated_hours', 'annual_workload_hours', 'sort_order', 'notes',
])]
class WlaActivity extends Model
{
    public const string MAX_INPUT_VALUE = '999999.99';

    public const int MAX_SORT_ORDER = 4294967295;

    protected function casts(): array
    {
        return [
            'frequency' => 'decimal:2',
            'period_unit' => WlaPeriodUnit::class,
            'frequency_unit' => WlaFrequencyUnit::class,
            'volume' => 'decimal:2',
            'volume_unit' => WlaVolumeUnit::class,
            'time_allocated' => 'decimal:2',
            'time_unit' => WlaTimeUnit::class,
            'time_allocated_hours' => 'decimal:2',
            'annual_workload_hours' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(WlaAssessment::class, 'wla_assessment_id');
    }
}
