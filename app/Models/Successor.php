<?php

namespace App\Models;

use Database\Factories\SuccessorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'target_position_id',
    'name',
    'position',
    'talent_mapping',
    'succession_readiness',
    'idp',
    'education',
    'tenure',
    'directorate',
    'photo_path',
])]
class Successor extends Model
{
    /** @use HasFactory<SuccessorFactory> */
    use HasFactory;

    protected $casts = [
        'idp' => 'integer',
    ];

    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }
}
