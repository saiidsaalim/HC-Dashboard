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
    'sap',
    'current_position',
    'position_history',
    'birth_date',
    'last_education',
    'talent_box',
    'recommendation',
    'targeted_band',
    'performance_year',
    'performance_score',
    'performance_category',
    'mbti_type',
    'mbti_description',
    'top_job_family_1',
    'top_job_family_2',
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
        'position_history' => 'array',
        'birth_date' => 'date',
        'talent_box' => 'integer',
        'performance_year' => 'integer',
        'performance_score' => 'decimal:2',
    ];

    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }
}
