<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unit_id',
    'section_id',
    'code',
    'name',
    'job_level',
    'formation_count',
    'sort_order',
    'active',
])]
class Position extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function successors(): HasMany
    {
        return $this->hasMany(Successor::class, 'target_position_id');
    }

    public function wlaAssessments(): HasMany
    {
        return $this->hasMany(WlaAssessment::class);
    }
}
