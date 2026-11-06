<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    /** @var list<string> */
    public const CRITICALITY_OPTIONS = ['high', 'medium', 'low'];

    /** @var list<string> */
    public const FORMATION_STATUS_OPTIONS = ['covered', 'vacant'];

    /** @var list<int> */
    public const TALENT_MAPPING_OPTIONS = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    /** @var list<string> */
    public const SUCCESSION_READINESS_OPTIONS = [
        'ready now',
        'ready in one year',
        'ready in two - three years',
        'ready in three - five years',
    ];

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
        'criticality',
        'formation_status',
        'talent_mapping',
        'succession_readiness',
        'idp_percentage',
        'department_id',
        'directorate_id',
        'unit_id',
        'section_id',
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
            'talent_mapping' => 'integer',
            'idp_percentage' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function organizationDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function organizationSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
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
        return $this->hasMany(EmployeePromotion::class);
    }

    public function demotions(): HasMany
    {
        return $this->hasMany(EmployeeDemotion::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(EmployeeMutation::class);
    }

    public function latestMutation(): HasOne
    {
        return $this->hasOne(EmployeeMutation::class)->latestOfMany('created_at');
    }

    public function latestPromotion(): HasOne
    {
        return $this->hasOne(EmployeePromotion::class)->latestOfMany('created_at');
    }

    public function latestDemotion(): HasOne
    {
        return $this->hasOne(EmployeeDemotion::class)->latestOfMany('created_at');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function wlaParticipations(): HasMany
    {
        return $this->hasMany(WlaParticipant::class);
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

    protected function usia(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->birth_date === null || $this->birth_date->isFuture()) {
                return null;
            }

            return $this->birth_date->age;
        });
    }
}
