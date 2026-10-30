<?php

namespace App\Models;

use Database\Factories\SuccessorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }
}
