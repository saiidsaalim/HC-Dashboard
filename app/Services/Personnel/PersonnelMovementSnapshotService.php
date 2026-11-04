<?php

namespace App\Services\Personnel;

use App\Models\Employee;

class PersonnelMovementSnapshotService
{
    public function __construct(private readonly OrganizationHierarchyService $organizationHierarchy) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function fromEmployee(Employee $employee, array $validated): array
    {
        $employee->loadMissing([
            'user:id,employee_id,name',
            'organizationDirectorate:id,name',
            'organizationDepartment:id,name',
            'unit:id,name',
            'organizationSection:id,name',
            'organizationPosition:id,name',
        ]);
        $target = $this->organizationHierarchy->validatedTarget($validated);

        return [
            'employee_id' => $employee->getKey(),
            'sap' => $employee->sap,
            'nama' => $employee->name ?: $employee->user?->name ?: $employee->personal_number ?: $employee->sap,
            'old_directorate_id' => $employee->directorate_id,
            'new_directorate_id' => $target['directorate']->id,
            'old_department_id' => $employee->department_id,
            'new_department_id' => $target['department']->id,
            'old_unit_id' => $employee->unit_id,
            'new_unit_id' => $target['unit']->id,
            'old_section_id' => $employee->section_id,
            'new_section_id' => $target['section']?->id,
            'old_position_id' => $employee->position_id,
            'new_position_id' => $target['position']->id,
            'direktorat_lama' => $employee->organizationDirectorate?->name
                ?: $employee->getRawOriginal('txt_dir')
                ?: $employee->getRawOriginal('directorate'),
            'direktorat_baru' => $target['directorate']->name,
            'departemen_lama' => $employee->organizationDepartment?->name
                ?: $employee->getRawOriginal('txt_dept')
                ?: $employee->getRawOriginal('department')
                ?: '-',
            'departemen_baru' => $target['department']->name,
            'unit_lama' => $employee->unit?->name
                ?: $employee->getRawOriginal('txt_biro')
                ?: $employee->getRawOriginal('bureau')
                ?: $employee->getRawOriginal('organizational_unit'),
            'unit_baru' => $target['unit']->name,
            'section_lama' => $employee->organizationSection?->name
                ?: $employee->getRawOriginal('txt_sect')
                ?: $employee->getRawOriginal('section'),
            'section_baru' => $target['section']?->name,
            'jabatan_lama' => $employee->organizationPosition?->name
                ?: $employee->getRawOriginal('position')
                ?: '-',
            'jabatan_baru' => $target['position']->name,
            'tmt' => $validated['tmt'],
            'pg' => $validated['pg'] ?? null,
            'level_lama' => $employee->employee_subgroup,
            'jg_lama' => $validated['jg_lama'] ?? null,
            'level_baru' => $validated['level_baru'] ?? null,
            'jg_baru' => $validated['jg_baru'] ?? null,
        ];
    }
}
