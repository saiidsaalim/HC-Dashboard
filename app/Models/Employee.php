<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'sap',
        'id_number',
        'agkn',
        'position_id_source',
        'personal_number',
        'position',
        'employee_subgroup',
        'cost_ctr',
        'txt_dir',
        'txt_dept',
        'txt_biro',
        'txt_sect',
        'birth_date',
        'gender_key',
        'personnel_area',
        'abrevation_position',
        'abrevation_organization',
        'organizational_unit',
        'cost_center',
        'masa_kontrak',
        'email',
        'religious',
        'usia',
        'tempat_lahir',
        'pendidikan',
        'hiring',
        'organilk',
        'alamat',
        'department_id',
        'unit_id',
        'position_id',
        'directorate',
        'department',
        'bureau',
        'section',
        'birth_place',
        'gender',
        'religion',
        'age',
        'education',
        'hiring_date',
        'organic_status',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hiring' => 'date',
            'masa_kontrak' => 'date',
            'organilk' => 'date',
            'hiring_date' => 'date',
            'usia' => 'integer',
            'age' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function organizationDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function organizationPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(EmployeePromotion::class, 'sap', 'sap');
    }

    public function demotions(): HasMany
    {
        return $this->hasMany(EmployeeDemotion::class, 'sap', 'sap');
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(EmployeeMutation::class, 'sap', 'sap');
    }

    public function organizationDepartmentName(): ?string
    {
        return $this->txt_dept ?? $this->organizationDepartment?->name;
    }

    public function organizationUnitName(): ?string
    {
        return $this->organizational_unit ?? $this->unit?->name;
    }

    public function organizationPositionName(): ?string
    {
        return $this->position ?? $this->organizationPosition?->name;
    }
}
