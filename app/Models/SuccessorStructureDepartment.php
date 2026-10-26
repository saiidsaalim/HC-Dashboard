<?php

namespace App\Models;

use Database\Factories\SuccessorStructureDepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'name', 'slug', 'sort_order'])]
class SuccessorStructureDepartment extends Model
{
    /** @use HasFactory<SuccessorStructureDepartmentFactory> */
    use HasFactory;

    public function positions(): HasMany
    {
        return $this->hasMany(SuccessorStructurePosition::class, 'department_id');
    }
}
