<?php

namespace App\Services\Personnel;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Support\Collection;

class PersonnelMovementFormDataService
{
    public function __construct(
        private readonly LevelTransitionService $levelTransitionService,
    ) {}

    /** @return Collection<int, string> */
    public function levels(): Collection
    {
        return collect($this->levelTransitionService->levels());
    }

    /** @return Collection<int, array<string, int|string|null>> */
    public function employees(): Collection
    {
        return Employee::query()
            ->with([
                'user:id,employee_id,name',
                'organizationDirectorate:id,name',
                'organizationDepartment:id,name',
                'unit:id,name',
                'organizationSection:id,name',
                'organizationPosition:id,name',
            ])
            ->orderBy('sap')
            ->get()
            ->map(function (Employee $employee): array {
                $name = trim((string) ($employee->name ?: $employee->user?->name));
                $personalNumber = trim((string) $employee->personal_number);
                $position = $employee->organizationPosition?->name ?: $employee->getRawOriginal('position');
                $labelParts = [
                    $employee->sap,
                    $personalNumber !== '' ? $personalNumber : 'Personal Number belum tersedia',
                ];

                if ($name !== '' && $name !== $personalNumber) {
                    $labelParts[] = $name;
                }

                if ($position !== null && $position !== '') {
                    $labelParts[] = $position;
                }

                return [
                    'id' => (int) $employee->id,
                    'sap' => $employee->sap,
                    'personal_number' => $personalNumber,
                    'name' => $name,
                    'directorate_id' => $employee->directorate_id,
                    'department_id' => $employee->department_id,
                    'unit_id' => $employee->unit_id,
                    'section_id' => $employee->section_id,
                    'position_id' => $employee->position_id,
                    'level' => $employee->employee_subgroup ?? '',
                    'directorate' => $employee->organizationDirectorate?->name
                        ?: $employee->getRawOriginal('txt_dir')
                        ?: $employee->getRawOriginal('directorate')
                        ?: '',
                    'department' => $employee->organizationDepartment?->name
                        ?: $employee->getRawOriginal('txt_dept')
                        ?: $employee->getRawOriginal('department')
                        ?: '',
                    'unit' => $employee->unit?->name
                        ?: $employee->getRawOriginal('txt_biro')
                        ?: $employee->getRawOriginal('organizational_unit')
                        ?: '',
                    'section' => $employee->organizationSection?->name
                        ?: $employee->getRawOriginal('txt_sect')
                        ?: $employee->getRawOriginal('section')
                        ?: '',
                    'position' => $position ?? '',
                    'label' => implode(' — ', $labelParts),
                ];
            });
    }

    /** @return array<string, Collection<int, Directorate|Department|Unit|Section|Position|string>> */
    public function organization(): array
    {
        return [
            'movementLevels' => $this->levels(),
            'movementDirectorates' => Directorate::query()->where('active', true)->orderBy('name')->get(),
            'movementDepartments' => Department::query()->where('active', true)->orderBy('name')->get(),
            'movementUnits' => Unit::query()->where('active', true)->orderBy('name')->get(),
            'movementSections' => Section::query()->where('active', true)->orderBy('name')->get(),
            'movementPositions' => Position::query()->where('active', true)->orderBy('name')->get(),
        ];
    }
}
