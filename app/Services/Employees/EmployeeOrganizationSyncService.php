<?php

namespace App\Services\Employees;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeOrganizationMappingAudit;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeOrganizationSyncService
{
    public const Confirmation = 'APPLY-EMPLOYEE-ORGANIZATION';

    /** @return array<string, mixed> */
    public function audit(int $chunkSize = 500): array
    {
        return $this->auditEmployees($this->loadEmployees($chunkSize), $this->masterState());
    }

    /** @return array<string, mixed> */
    public function apply(int $chunkSize = 500): array
    {
        return DB::transaction(function () use ($chunkSize): array {
            $this->lockMasterRows();
            $employees = $this->loadEmployees($chunkSize, true);
            $before = $this->auditEmployees($employees, $this->masterState());
            $safeRows = array_values(array_filter(
                $before['rows'],
                fn (array $row): bool => in_array($row['status'], ['ready_existing_master', 'ready_create_master'], true),
            ));

            $created = $this->createMissingMasters($safeRows);
            $state = $this->masterState();
            $updated = 0;
            $foreignKeysUpdated = 0;

            foreach ($employees as $employee) {
                $row = $this->classify($employee, $state);
                if (! in_array($row['status'], ['ready_existing_master', 'already_mapped'], true)) {
                    continue;
                }

                $updates = [];
                foreach (['directorate_id', 'department_id', 'unit_id', 'section_id', 'position_id'] as $foreignKey) {
                    if ((int) $employee->{$foreignKey} !== (int) $row['target'][$foreignKey]) {
                        $updates[$foreignKey] = $row['target'][$foreignKey];
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $oldDirectorateId = $employee->directorate_id;
                $oldDepartmentId = $employee->department_id;
                $oldUnitId = $employee->unit_id;
                $oldSectionId = $employee->section_id;
                $oldPositionId = $employee->position_id;
                $employee->fill($updates);
                $employee->save();

                EmployeeOrganizationMappingAudit::query()->create([
                    'employee_id' => $employee->id,
                    'old_directorate_id' => $oldDirectorateId,
                    'new_directorate_id' => $employee->directorate_id,
                    'old_department_id' => $oldDepartmentId,
                    'new_department_id' => $employee->department_id,
                    'old_unit_id' => $oldUnitId,
                    'new_unit_id' => $employee->unit_id,
                    'old_section_id' => $oldSectionId,
                    'new_section_id' => $employee->section_id,
                    'old_position_id' => $oldPositionId,
                    'new_position_id' => $employee->position_id,
                    'executed_by' => Auth::id(),
                    'executed_at' => now(),
                ]);

                $updated++;
                $foreignKeysUpdated += count($updates);
            }

            $after = $this->auditEmployees($employees->each->refresh(), $this->masterState());
            $after['applied'] = [
                'employees_updated' => $updated,
                'foreign_keys_updated' => $foreignKeysUpdated,
                'directorates_created' => $created['directorates'],
                'departments_created' => $created['departments'],
                'units_created' => $created['units'],
                'sections_created' => $created['sections'],
                'positions_created' => $created['positions'],
            ];

            return $after;
        });
    }

    /**
     * @return array{
     *     ids: array{department_id: ?int, unit_id: ?int, position_id: ?int},
     *     statuses: array{department: string, unit: string, position: string},
     *     conflicts: array<int, string>
     * }
     */
    public function resolveExisting(
        ?string $departmentName,
        ?string $unitName,
        ?string $positionName,
        ?int $departmentId = null,
        ?int $unitId = null,
        ?int $positionId = null,
        ?string $directorateName = null,
        ?string $sectionName = null,
        ?int $directorateId = null,
        ?int $sectionId = null,
    ): array {
        $employee = new Employee([
            'txt_dept' => $departmentName,
            'txt_biro' => $unitName,
            'position' => $positionName,
            'txt_dir' => $directorateName,
            'txt_sect' => $sectionName,
            'directorate_id' => $directorateId,
            'department_id' => $departmentId,
            'unit_id' => $unitId,
            'section_id' => $sectionId,
            'position_id' => $positionId,
        ]);
        $row = $this->classify($employee, $this->masterState(), false);

        return [
            'ids' => $row['target'],
            'statuses' => $row['matches'],
            'conflicts' => $row['conflict_fields'],
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array<string, array<int, array<string, mixed>>>  $state
     * @return array<string, mixed>
     */
    private function auditEmployees(Collection $employees, array $state): array
    {
        $rows = [];
        $uniqueDirectorates = [];
        $uniqueDepartments = [];
        $uniqueUnits = [];
        $uniqueSections = [];
        $uniquePositions = [];
        $newDirectorates = [];
        $newDepartments = [];
        $newUnits = [];
        $newSections = [];
        $newPositions = [];
        $summary = [
            'employees' => $employees->count(),
            'exact_matches' => 0,
            'normalized_matches' => 0,
            'empty_directorate' => 0,
            'empty_department' => 0,
            'empty_unit' => 0,
            'empty_section' => 0,
            'empty_position' => 0,
            'conflicts' => 0,
            'mappable_employees' => 0,
            'review_employees' => 0,
            'foreign_keys_to_update' => 0,
            'statuses' => array_fill_keys([
                'already_mapped', 'ready_existing_master', 'ready_create_master',
                'needs_review', 'conflict', 'invalid',
            ], 0),
        ];

        foreach ($employees as $employee) {
            $row = $this->classify($employee, $state);
            $rows[] = $row;
            $summary['statuses'][$row['status']]++;
            $summary['exact_matches'] += count(array_filter($row['matches'], fn (string $status): bool => $status === 'exact'));
            $summary['normalized_matches'] += count(array_filter($row['matches'], fn (string $status): bool => $status === 'normalized'));
            $summary['empty_directorate'] += $row['source']['directorate'] === null ? 1 : 0;
            $summary['empty_department'] += $row['source']['department'] === null ? 1 : 0;
            $summary['empty_unit'] += $row['source']['unit'] === null ? 1 : 0;
            $summary['empty_section'] += $row['source']['section'] === null ? 1 : 0;
            $summary['empty_position'] += $row['source']['position'] === null ? 1 : 0;
            $summary['conflicts'] += $row['status'] === 'conflict' ? 1 : 0;
            $summary['mappable_employees'] += in_array($row['status'], ['already_mapped', 'ready_existing_master', 'ready_create_master'], true) ? 1 : 0;
            $summary['review_employees'] += in_array($row['status'], ['needs_review', 'conflict', 'invalid'], true) ? 1 : 0;
            $summary['foreign_keys_to_update'] += $row['foreign_keys_to_update'];

            if ($row['source']['directorate'] !== null) {
                $uniqueDirectorates[$row['keys']['directorate']] = true;
            }
            if ($row['source']['department'] !== null) {
                $uniqueDepartments[$row['keys']['department']] = true;
            }
            if ($row['source']['department'] !== null && $row['source']['unit'] !== null) {
                $uniqueUnits[$row['keys']['unit']] = true;
            }
            if ($row['source']['department'] !== null && $row['source']['unit'] !== null && $row['source']['section'] !== null) {
                $uniqueSections[$row['keys']['section']] = true;
            }
            if ($row['source']['department'] !== null && $row['source']['unit'] !== null && $row['source']['position'] !== null) {
                $uniquePositions[$row['keys']['position']] = true;
            }

            if ($row['status'] === 'ready_create_master') {
                if ($row['matches']['directorate'] === 'new') {
                    $newDirectorates[$row['keys']['directorate']] = true;
                }
                if ($row['matches']['department'] === 'new') {
                    $newDepartments[$row['keys']['department']] = true;
                }
                if ($row['matches']['unit'] === 'new') {
                    $newUnits[$row['keys']['unit']] = true;
                }
                if ($row['matches']['section'] === 'new') {
                    $newSections[$row['keys']['section']] = true;
                }
                if ($row['matches']['position'] === 'new') {
                    $newPositions[$row['keys']['position']] = true;
                }
            }
        }

        $summary['unique_directorates'] = count($uniqueDirectorates);
        $summary['unique_departments'] = count($uniqueDepartments);
        $summary['unique_units'] = count($uniqueUnits);
        $summary['unique_sections'] = count($uniqueSections);
        $summary['unique_positions'] = count($uniquePositions);
        $summary['directorates_to_create'] = count($newDirectorates);
        $summary['departments_to_create'] = count($newDepartments);
        $summary['units_to_create'] = count($newUnits);
        $summary['sections_to_create'] = count($newSections);
        $summary['positions_to_create'] = count($newPositions);

        return ['summary' => $summary, 'rows' => $rows];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $state
     * @return array<string, mixed>
     */
    private function classify(Employee $employee, array $state, bool $allowNew = true): array
    {
        $source = [
            'directorate' => $this->clean($employee->txt_dir),
            'department' => $this->clean($employee->txt_dept),
            'unit' => $this->clean($employee->txt_biro),
            'section' => $this->clean($employee->txt_sect),
            'position' => $this->clean($employee->position),
        ];
        $keys = [
            'directorate' => $this->normalize($source['directorate']),
            'department' => '',
            'unit' => '',
            'section' => '',
            'position' => '',
        ];
        $keys['department'] = ($keys['directorate'] !== '' ? $keys['directorate'].'|' : '').$this->normalize($source['department']);
        $keys['unit'] = $keys['department'].'|'.$this->normalize($source['unit']);
        $keys['section'] = $keys['unit'].'|'.$this->normalize($source['section']);
        $keys['position'] = ($keys['section'] !== '' ? $keys['section'] : $keys['unit']).'|'.$this->normalize($source['position']);
        $matches = ['directorate' => 'empty', 'department' => 'empty', 'unit' => 'empty', 'section' => 'empty', 'position' => 'empty'];
        $target = [
            'directorate_id' => $source['directorate'] === null ? $employee->directorate_id : null,
            'department_id' => null,
            'unit_id' => null,
            'section_id' => $source['section'] === null ? $employee->section_id : null,
            'position_id' => null,
        ];
        $reasons = [];
        $conflictFields = [];

        if ($source['department'] === null || $source['unit'] === null || $source['position'] === null) {
            foreach (['department' => 'txt_dept', 'unit' => 'txt_biro', 'position' => 'position'] as $field => $column) {
                if ($source[$field] === null) {
                    $reasons[] = "{$column} kosong.";
                }
            }

            return $this->row($employee, 'needs_review', $source, $keys, $matches, $target, $reasons, $conflictFields);
        }

        foreach ($source as $field => $name) {
            if (mb_strlen((string) $name) > 255) {
                $reasons[] = "{$field} melebihi 255 karakter.";
            }
        }
        if ($reasons !== []) {
            return $this->row($employee, 'invalid', $source, $keys, $matches, $target, $reasons, $conflictFields);
        }

        $directorate = null;
        if ($source['directorate'] !== null) {
            $directorateMatch = $this->match($source['directorate'], $state['directorates']);
            $matches['directorate'] = $directorateMatch['status'];
            $directorate = $directorateMatch['record'];
            if ($directorateMatch['status'] === 'conflict') {
                $conflictFields[] = 'directorate';
                $reasons[] = 'Lebih dari satu Directorate cocok setelah normalisasi.';
            } elseif ($directorate === null && $allowNew) {
                $matches['directorate'] = 'new';
            } elseif ($directorate !== null) {
                $target['directorate_id'] = $directorate['id'];
            }
        }

        $departmentRecords = $state['departments'];
        if ($directorate !== null) {
            $departmentRecords = array_values(array_filter(
                $departmentRecords,
                fn (array $record): bool => $record['directorate_id'] === $directorate['id'],
            ));
        }
        $departmentMatch = $matches['directorate'] === 'new'
            ? ['status' => 'unmapped', 'record' => null]
            : $this->match($source['department'], $departmentRecords);
        $matches['department'] = $departmentMatch['status'];
        $department = $departmentMatch['record'];
        if ($departmentMatch['status'] === 'conflict') {
            $conflictFields[] = 'department';
            $reasons[] = 'Lebih dari satu department cocok setelah normalisasi.';
        } elseif ($department === null && $allowNew) {
            $matches['department'] = 'new';
        } elseif ($department !== null) {
            $target['department_id'] = $department['id'];
        }

        $unit = null;
        if ($department !== null) {
            $unitMatch = $this->match($source['unit'], array_values(array_filter(
                $state['units'],
                fn (array $record): bool => $record['department_id'] === $department['id'],
            )));
            $matches['unit'] = $unitMatch['status'];
            $unit = $unitMatch['record'];
            if ($unitMatch['status'] === 'conflict') {
                $conflictFields[] = 'unit';
                $reasons[] = 'Lebih dari satu unit cocok pada department yang sama.';
            } elseif ($unit === null && $allowNew) {
                $matches['unit'] = 'new';
            } elseif ($unit !== null) {
                $target['unit_id'] = $unit['id'];
            }
        } elseif ($matches['department'] === 'new') {
            $matches['unit'] = $allowNew ? 'new' : 'unmapped';
        } else {
            $matches['unit'] = 'unmapped';
        }

        if ($unit !== null) {
            if ($source['section'] !== null) {
                $sectionMatch = $this->match($source['section'], array_values(array_filter(
                    $state['sections'],
                    fn (array $record): bool => $record['unit_id'] === $unit['id'],
                )));
                $matches['section'] = $sectionMatch['status'];
                $section = $sectionMatch['record'];
                if ($sectionMatch['status'] === 'conflict') {
                    $conflictFields[] = 'section';
                    $reasons[] = 'Lebih dari satu Section cocok pada Unit yang sama.';
                } elseif ($section === null && $allowNew) {
                    $matches['section'] = 'new';
                } elseif ($section !== null) {
                    $target['section_id'] = $section['id'];
                }
            } else {
                $section = null;
            }

            $positionRecords = array_values(array_filter(
                $state['positions'],
                fn (array $record): bool => $record['unit_id'] === $unit['id']
                    && ($section === null || $record['section_id'] === $section['id']),
            ));
            $positionMatch = $matches['section'] === 'new'
                ? ['status' => 'unmapped', 'record' => null]
                : $this->match($source['position'], $positionRecords);
            $matches['position'] = $positionMatch['status'];
            $position = $positionMatch['record'];
            if ($positionMatch['status'] === 'conflict') {
                $conflictFields[] = 'position';
                $reasons[] = 'Lebih dari satu position cocok pada unit yang sama.';
            } elseif ($position === null && $allowNew) {
                $matches['position'] = 'new';
            } elseif ($position !== null) {
                $target['position_id'] = $position['id'];
            }
        } elseif ($matches['unit'] === 'new') {
            $matches['section'] = $source['section'] !== null && $allowNew ? 'new' : 'empty';
            $matches['position'] = $allowNew ? 'new' : 'unmapped';
        } else {
            $matches['section'] = $source['section'] !== null ? 'unmapped' : 'empty';
            $matches['position'] = 'unmapped';
        }

        if ($conflictFields !== []) {
            return $this->row($employee, 'conflict', $source, $keys, $matches, $target, $reasons, $conflictFields);
        }

        $oldIds = [
            'directorate_id' => $employee->directorate_id === null ? null : (int) $employee->directorate_id,
            'department_id' => $employee->department_id === null ? null : (int) $employee->department_id,
            'unit_id' => $employee->unit_id === null ? null : (int) $employee->unit_id,
            'section_id' => $employee->section_id === null ? null : (int) $employee->section_id,
            'position_id' => $employee->position_id === null ? null : (int) $employee->position_id,
        ];
        $legacyHierarchyMatchesSource = $this->legacyHierarchyMatchesSource($employee, $source, $state);
        foreach ($oldIds as $foreignKey => $oldId) {
            if ($oldId !== null && ($target[$foreignKey] === null || $target[$foreignKey] !== $oldId)) {
                if ($legacyHierarchyMatchesSource && in_array($foreignKey, ['department_id', 'unit_id', 'position_id'], true)) {
                    continue;
                }
                $field = str_replace('_id', '', $foreignKey);
                $conflictFields[] = $field;
                $reasons[] = "{$foreignKey} lama bertentangan dengan teks legacy atau hierarki.";
            }
        }
        if ($conflictFields !== []) {
            return $this->row($employee, 'conflict', $source, $keys, $matches, $target, $reasons, $conflictFields);
        }

        $allMapped = $target['department_id'] !== null
            && $target['unit_id'] !== null
            && $target['position_id'] !== null
            && ($source['directorate'] === null || $target['directorate_id'] !== null)
            && ($source['section'] === null || $target['section_id'] !== null)
            && $oldIds === $target;
        $status = $allMapped ? 'already_mapped' : (in_array('new', $matches, true) ? 'ready_create_master' : 'ready_existing_master');

        return $this->row($employee, $status, $source, $keys, $matches, $target, $reasons, $conflictFields);
    }

    /**
     * @param  array<string, ?string>  $source
     * @param  array<string, array<int, array<string, mixed>>>  $state
     */
    private function legacyHierarchyMatchesSource(Employee $employee, array $source, array $state): bool
    {
        if ($employee->department_id === null || $employee->unit_id === null || $employee->position_id === null) {
            return false;
        }

        $department = $this->recordById($state['departments'], (int) $employee->department_id);
        $unit = $this->recordById($state['units'], (int) $employee->unit_id);
        $position = $this->recordById($state['positions'], (int) $employee->position_id);

        if ($department === null || $unit === null || $position === null) {
            return false;
        }

        return $this->legacyNameMatchesSource((string) $department['name'], $source['department'])
            && $this->legacyNameMatchesSource((string) $unit['name'], $source['unit'])
            && $unit['department_id'] === $department['id']
            && $this->legacyNameMatchesSource((string) $position['name'], $source['position'])
            && $position['unit_id'] === $unit['id'];
    }

    private function legacyNameMatchesSource(string $legacyName, ?string $sourceName): bool
    {
        $legacy = $this->normalize($legacyName);
        $source = $this->normalize($sourceName);

        return $legacy === $source || ($legacy === '#ref!' && $source === '0');
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>|null
     */
    private function recordById(array $records, int $id): ?array
    {
        foreach ($records as $record) {
            if ($record['id'] === $id) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  array<string, ?string>  $source
     * @param  array<string, string>  $keys
     * @param  array<string, string>  $matches
     * @param  array<string, ?int>  $target
     * @param  array<int, string>  $reasons
     * @param  array<int, string>  $conflictFields
     * @return array<string, mixed>
     */
    private function row(Employee $employee, string $status, array $source, array $keys, array $matches, array $target, array $reasons, array $conflictFields): array
    {
        $changes = 0;
        foreach ($target as $foreignKey => $targetId) {
            if (($foreignKey === 'directorate_id' && $source['directorate'] === null)
                || ($foreignKey === 'section_id' && $source['section'] === null)) {
                continue;
            }
            if ($targetId === null || (int) $employee->{$foreignKey} !== $targetId) {
                $changes++;
            }
        }

        return [
            'employee_id' => (int) $employee->id,
            'status' => $status,
            'reason' => implode(' ', $reasons),
            'current' => [
                'directorate_id' => $employee->directorate_id,
                'department_id' => $employee->department_id,
                'unit_id' => $employee->unit_id,
                'section_id' => $employee->section_id,
                'position_id' => $employee->position_id,
            ],
            'source' => $source,
            'keys' => $keys,
            'matches' => $matches,
            'target' => $target,
            'conflict_fields' => array_values(array_unique($conflictFields)),
            'foreign_keys_to_update' => in_array($status, ['ready_existing_master', 'ready_create_master'], true) ? $changes : 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{directorates: int, departments: int, units: int, sections: int, positions: int}
     */
    private function createMissingMasters(array $rows): array
    {
        $created = ['directorates' => 0, 'departments' => 0, 'units' => 0, 'sections' => 0, 'positions' => 0];
        foreach (collect($rows)->filter(fn (array $row): bool => $row['source']['directorate'] !== null)->pluck('source', 'keys.directorate')->sortKeys() as $source) {
            if ($this->findDirectorate((string) $source['directorate']) !== null) {
                continue;
            }
            Directorate::query()->create([
                'code' => $this->automaticCode('AUTO-DIR-', $this->normalize($source['directorate']), fn (string $code): bool => Directorate::query()->where('code', $code)->exists()),
                'name' => $source['directorate'],
                'active' => true,
            ]);
            $created['directorates']++;
        }

        foreach (collect($rows)->pluck('source', 'keys.department')->sortKeys() as $source) {
            $directorate = $source['directorate'] === null ? null : $this->findDirectorate((string) $source['directorate']);
            if ($source['directorate'] !== null && $directorate === null) {
                throw new RuntimeException('Directorate hasil sinkronisasi tidak dapat ditemukan.');
            }
            if ($this->findDepartment($directorate?->id, (string) $source['department']) !== null) {
                continue;
            }
            Department::query()->create([
                'directorate_id' => $directorate?->id,
                'code' => $this->automaticCode('AUTO-D-', $this->normalize($source['directorate']).'|'.$this->normalize($source['department']), fn (string $code): bool => Department::query()->where('code', $code)->exists()),
                'name' => $source['department'],
                'active' => true,
            ]);
            $created['departments']++;
        }

        foreach (collect($rows)->pluck('source', 'keys.unit')->sortKeys() as $source) {
            $directorate = $source['directorate'] === null ? null : $this->findDirectorate((string) $source['directorate']);
            $department = $this->findDepartment($directorate?->id, (string) $source['department']);
            if ($department === null) {
                throw new RuntimeException('Department hasil sinkronisasi tidak dapat ditemukan.');
            }
            if ($this->findUnit($department->id, (string) $source['unit']) !== null) {
                continue;
            }
            Unit::query()->create([
                'department_id' => $department->id,
                'code' => $this->automaticCode('AUTO-U-', $this->normalize($source['directorate']).'|'.$this->normalize($source['department']).'|'.$this->normalize($source['unit']), fn (string $code): bool => Unit::query()->where('department_id', $department->id)->where('code', $code)->exists()),
                'name' => $source['unit'],
                'active' => true,
            ]);
            $created['units']++;
        }

        foreach (collect($rows)->filter(fn (array $row): bool => $row['source']['section'] !== null)->pluck('source', 'keys.section')->sortKeys() as $source) {
            $directorate = $source['directorate'] === null ? null : $this->findDirectorate((string) $source['directorate']);
            $department = $this->findDepartment($directorate?->id, (string) $source['department']);
            $unit = $department === null ? null : $this->findUnit($department->id, (string) $source['unit']);
            if ($unit === null) {
                throw new RuntimeException('Unit induk Section hasil sinkronisasi tidak dapat ditemukan.');
            }
            if ($this->findSection($unit->id, (string) $source['section']) !== null) {
                continue;
            }
            Section::query()->create([
                'unit_id' => $unit->id,
                'code' => $this->automaticCode('AUTO-S-', $this->normalize($source['directorate']).'|'.$this->normalize($source['department']).'|'.$this->normalize($source['unit']).'|'.$this->normalize($source['section']), fn (string $code): bool => Section::query()->where('unit_id', $unit->id)->where('code', $code)->exists()),
                'name' => $source['section'],
                'active' => true,
            ]);
            $created['sections']++;
        }

        foreach (collect($rows)->pluck('source', 'keys.position')->sortKeys() as $source) {
            $directorate = $source['directorate'] === null ? null : $this->findDirectorate((string) $source['directorate']);
            $department = $this->findDepartment($directorate?->id, (string) $source['department']);
            $unit = $department === null ? null : $this->findUnit($department->id, (string) $source['unit']);
            if ($unit === null) {
                throw new RuntimeException('Unit hasil sinkronisasi tidak dapat ditemukan.');
            }
            $section = $source['section'] === null ? null : $this->findSection($unit->id, (string) $source['section']);
            if ($source['section'] !== null && $section === null) {
                throw new RuntimeException('Section hasil sinkronisasi tidak dapat ditemukan.');
            }
            if ($this->findPosition($unit->id, $section?->id, (string) $source['position']) !== null) {
                continue;
            }
            Position::query()->create([
                'unit_id' => $unit->id,
                'section_id' => $section?->id,
                'code' => $this->automaticCode('AUTO-P-', $this->normalize($source['directorate']).'|'.$this->normalize($source['department']).'|'.$this->normalize($source['unit']).'|'.$this->normalize($source['section']).'|'.$this->normalize($source['position']), fn (string $code): bool => Position::query()->where('unit_id', $unit->id)->where('code', $code)->exists()),
                'name' => $source['position'],
                'active' => true,
            ]);
            $created['positions']++;
        }

        return $created;
    }

    /** @return Collection<int, Employee> */
    private function loadEmployees(int $chunkSize, bool $lock = false): Collection
    {
        $employees = new Collection;
        $query = Employee::query()
            ->select([
                'id', 'txt_dir', 'txt_dept', 'txt_biro', 'txt_sect', 'position',
                'directorate_id', 'department_id', 'unit_id', 'section_id', 'position_id',
            ])
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $query->chunkById($chunkSize, function (Collection $chunk) use ($employees): void {
            $employees->push(...$chunk);
        });

        return $employees;
    }

    private function lockMasterRows(): void
    {
        Directorate::query()->orderBy('id')->lockForUpdate()->get(['id']);
        Department::query()->orderBy('id')->lockForUpdate()->get(['id']);
        Unit::query()->orderBy('id')->lockForUpdate()->get(['id']);
        Section::query()->orderBy('id')->lockForUpdate()->get(['id']);
        Position::query()->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function masterState(): array
    {
        return [
            'directorates' => Directorate::query()->orderBy('id')->get(['id', 'name'])->map(fn (Directorate $directorate): array => ['id' => $directorate->id, 'name' => $directorate->name])->all(),
            'departments' => Department::query()->orderBy('id')->get(['id', 'directorate_id', 'name'])->map(fn (Department $department): array => ['id' => $department->id, 'directorate_id' => $department->directorate_id, 'name' => $department->name])->all(),
            'units' => Unit::query()->orderBy('id')->get(['id', 'department_id', 'name'])->map(fn (Unit $unit): array => ['id' => $unit->id, 'department_id' => $unit->department_id, 'name' => $unit->name])->all(),
            'sections' => Section::query()->orderBy('id')->get(['id', 'unit_id', 'name'])->map(fn (Section $section): array => ['id' => $section->id, 'unit_id' => $section->unit_id, 'name' => $section->name])->all(),
            'positions' => Position::query()->orderBy('id')->get(['id', 'unit_id', 'section_id', 'name'])->map(fn (Position $position): array => ['id' => $position->id, 'unit_id' => $position->unit_id, 'section_id' => $position->section_id, 'name' => $position->name])->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{record: ?array, status: string}
     */
    private function match(string $name, array $records): array
    {
        $cleanName = $this->clean($name);
        $normalizedName = $this->normalize($name);
        $normalized = array_values(array_filter($records, fn (array $record): bool => $this->normalize((string) $record['name']) === $normalizedName));
        if (count($normalized) > 1) {
            return ['record' => null, 'status' => 'conflict'];
        }
        if (count($normalized) === 1) {
            $status = $this->clean((string) $normalized[0]['name']) === $cleanName ? 'exact' : 'normalized';

            return ['record' => $normalized[0], 'status' => $status];
        }

        return ['record' => null, 'status' => 'unmapped'];
    }

    private function findDirectorate(string $name): ?Directorate
    {
        $matches = Directorate::query()->get()->filter(fn (Directorate $directorate): bool => $this->normalize($directorate->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findDepartment(?int $directorateId, string $name): ?Department
    {
        $query = Department::query();
        if ($directorateId !== null) {
            $query->where('directorate_id', $directorateId);
        }
        $matches = $query->get()->filter(fn (Department $department): bool => $this->normalize($department->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findUnit(int $departmentId, string $name): ?Unit
    {
        $matches = Unit::query()->where('department_id', $departmentId)->get()->filter(fn (Unit $unit): bool => $this->normalize($unit->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findSection(int $unitId, string $name): ?Section
    {
        $matches = Section::query()->where('unit_id', $unitId)->get()->filter(fn (Section $section): bool => $this->normalize($section->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findPosition(int $unitId, ?int $sectionId, string $name): ?Position
    {
        $query = Position::query()->where('unit_id', $unitId);
        if ($sectionId !== null) {
            $query->where('section_id', $sectionId);
        }
        $matches = $query->get()->filter(fn (Position $position): bool => $this->normalize($position->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function automaticCode(string $prefix, string $key, callable $exists): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = $prefix.strtoupper(sha1($key.($attempt === 0 ? '' : '|'.$attempt)));
            if (! $exists($code)) {
                return $code;
            }
        }

        throw new RuntimeException('Kode AUTO unik tidak dapat dibuat.');
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $cleaned = preg_replace('/\s+/u', ' ', trim($value));

        return $cleaned === null || $cleaned === '' ? null : $cleaned;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower($this->clean($value) ?? '', 'UTF-8');
    }
}
