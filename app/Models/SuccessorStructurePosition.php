<?php

namespace App\Models;

use Database\Factories\SuccessorStructurePositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'department_id',
    'parent_id',
    'key',
    'level',
    'name',
    'formation_count',
    'sort_order',
    'align_with_grandchildren',
])]
class SuccessorStructurePosition extends Model
{
    /** @use HasFactory<SuccessorStructurePositionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'align_with_grandchildren' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SuccessorStructureDepartment::class, 'department_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
