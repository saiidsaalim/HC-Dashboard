<?php

namespace App\Models;

use App\Enums\WlaFrequencyUnit;
use App\Enums\WlaTimeUnit;
use App\Enums\WlaVolumeUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'activity_name', 'frequency', 'frequency_unit', 'volume', 'volume_unit',
    'time_allocated', 'time_unit', 'sort_order', 'notes',
])]
class WlaActivity extends Model
{
    protected function casts(): array
    {
        return [
            'frequency' => 'decimal:2',
            'frequency_unit' => WlaFrequencyUnit::class,
            'volume' => 'decimal:2',
            'volume_unit' => WlaVolumeUnit::class,
            'time_allocated' => 'decimal:2',
            'time_unit' => WlaTimeUnit::class,
            'sort_order' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(WlaAssessment::class, 'wla_assessment_id');
    }
}
