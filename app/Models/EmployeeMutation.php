<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sap',
    'nama',
    'departemen_lama',
    'jabatan_lama',
    'departemen_baru',
    'jabatan_baru',
    'tmt',
    'pg',
    'level_lama',
    'jg_lama',
    'level_baru',
    'jg_baru',
    'verification_status',
    'finalized_at',
    'old_directorate_id',
    'new_directorate_id',
    'old_department_id',
    'new_department_id',
    'old_unit_id',
    'new_unit_id',
    'old_section_id',
    'new_section_id',
    'old_position_id',
    'new_position_id',
    'direktorat_lama',
    'direktorat_baru',
    'unit_lama',
    'unit_baru',
    'section_lama',
    'section_baru',
    'employee_applied_at',
])]
class EmployeeMutation extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sap', 'sap');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(MutationApproval::class);
    }

    public function oldDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'old_directorate_id');
    }

    public function newDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'new_directorate_id');
    }

    public function oldDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'old_department_id');
    }

    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'new_department_id');
    }

    public function oldUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'old_unit_id');
    }

    public function newUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'new_unit_id');
    }

    public function oldSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'old_section_id');
    }

    public function newSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'new_section_id');
    }

    public function oldPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'old_position_id');
    }

    public function newPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'new_position_id');
    }

    protected function casts(): array
    {
        return [
            'tmt' => 'date',
            'finalized_at' => 'datetime',
            'employee_applied_at' => 'datetime',
        ];
    }
}
