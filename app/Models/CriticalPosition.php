<?php

namespace App\Models;

use App\Enums\CriticalPositionCriticality;
use Database\Factories\CriticalPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'position_id',
    'criticality',
    'assessment_reason',
    'assessed_at',
    'review_due_at',
    'assessed_by',
    'notes',
])]
class CriticalPosition extends Model
{
    /** @use HasFactory<CriticalPositionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'criticality' => CriticalPositionCriticality::class,
            'assessed_at' => 'date',
            'review_due_at' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
