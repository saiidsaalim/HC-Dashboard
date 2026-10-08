<?php

namespace App\Services\Employees;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeOrganizationMappingAudit;
use App\Models\Position;
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
                foreach (['department_id', 'unit_id', 'position_id'] as $foreignKey) {
                    if ((int) $employee->{$foreignKey} !== (int) $row['target'][$foreignKey]) {
                        $updates[$foreignKey] = $row['target'][$foreignKey];
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $oldDepartmentId = $employee->department_id;
                $oldUnitId = $employee->unit_id;
                $oldPositionId = $employee->position_id;
                $employee->fill($updates);
                $employee->save();

                EmployeeOrganizationMappingAudit::query()->create([
                    'employee_id' => $employee->id,
                    'old_department_id' => $oldDepartmentId,
                    'new_department_id' => $employee->department_id,
                    'old_unit_id' => $oldUnitId,
                    'new_unit_id' => $employee->unit_id,
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
                'departments_created' => $created['departments'],
                'units_created' => $created['units'],
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
    ): array {
        $employee = new Employee([
            'txt_dept' => $departmentName,
            'txt_biro' => $unitName,
            'position' => $positionName,
            'department_id' => $departmentId,
            'unit_id' => $unitId,
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
        $uniqueDepartments = [];
        $uniqueUnits = [];
        $uniquePositions = [];
        $newDepartments = [];
        $newUnits = [];
        $newPositions = [];
        $summary = [
            'employees' => $employees->count(),
            'exact_matches' => 0,
            'normalized_matches' => 0,
            'empty_department' => 0,
            'empty_unit' => 0,
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
            $summary['empty_department'] += $row['source']['department'] === null ? 1 : 0;
            $summary['empty_unit'] += $row['source']['unit'] === null ? 1 : 0;
            $summary['empty_position'] += $row['source']['position'] === null ? 1 : 0;
            $summary['conflicts'] += $row['status'] === 'conflict' ? 1 : 0;
            $summary['mappable_employees'] += in_array($row['status'], ['already_mapped', 'ready_existing_master', 'ready_create_master'], true) ? 1 : 0;
            $summary['review_employees'] += in_array($row['status'], ['needs_review', 'conflict', 'invalid'], true) ? 1 : 0;
            $summary['foreign_keys_to_update'] += $row['foreign_keys_to_update'];

            if ($row['source']['department'] !== null) {
                $uniqueDepartments[$row['keys']['department']] = true;
            }
            if ($row['source']['department'] !== null && $row['source']['unit'] !== null) {
                $uniqueUnits[$row['keys']['unit']] = true;
            }
            if ($row['source']['department'] !== null && $row['source']['unit'] !== null && $row['source']['position'] !== null) {
                $uniquePositions[$row['keys']['position']] = true;
            }

            if ($row['status'] === 'ready_create_master') {
                if ($row['matches']['department'] === 'new') {
                    $newDepartments[$row['keys']['department']] = true;
                }
                if ($row['matches']['unit'] === 'new') {
                    $newUnits[$row['keys']['unit']] = true;
                }
                if ($row['matches']['position'] === 'new') {
                    $newPositions[$row['keys']['position']] = true;
                }
            }
        }

        $summary['unique_departments'] = count($uniqueDepartments);
        $summary['unique_units'] = count($uniqueUnits);
        $summary['unique_positions'] = count($uniquePositions);
        $summary['departments_to_create'] = count($newDepartments);
        $summary['units_to_create'] = count($newUnits);
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
            'department' => $this->clean($employee->txt_dept),
            'unit' => $this->clean($employee->txt_biro),
            'position' => $this->clean($employee->position),
        ];
        $keys = ['department' => $this->normalize($source['department']), 'unit' => '', 'position' => ''];
        $keys['unit'] = $keys['department'].'|'.$this->normalize($source['unit']);
        $keys['position'] = $keys['unit'].'|'.$this->normalize($source['position']);
        $matches = ['department' => 'empty', 'unit' => 'empty', 'position' => 'empty'];
        $target = ['department_id' => null, 'unit_id' => null, 'position_id' => null];
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

        $departmentMatch = $this->match($source['department'], $state['departments']);
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
            $positionMatch = $this->match($source['position'], array_values(array_filter(
                $state['positions'],
                fn (array $record): bool => $record['unit_id'] === $unit['id'],
            )));
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
            $matches['position'] = $allowNew ? 'new' : 'unmapped';
        } else {
            $matches['position'] = 'unmapped';
        }

        if ($conflictFields !== []) {
            return $this->row($employee, 'conflict', $source, $keys, $matches, $target, $reasons, $conflictFields);
        }

        $oldIds = [
            'department_id' => $employee->department_id === null ? null : (int) $employee->department_id,
            'unit_id' => $employee->unit_id === null ? null : (int) $employee->unit_id,
            'position_id' => $employee->position_id === null ? null : (int) $employee->position_id,
        ];
        foreach ($oldIds as $foreignKey => $oldId) {
            if ($oldId !== null && ($target[$foreignKey] === null || $target[$foreignKey] !== $oldId)) {
                $field = str_replace('_id', '', $foreignKey);
                $conflictFields[] = $field;
                $reasons[] = "{$foreignKey} lama bertentangan dengan teks legacy atau hierarki.";
            }
        }
        if ($conflictFields !== []) {
            return $this->row($employee, 'conflict', $source, $keys, $matches, $target, $reasons, $conflictFields);
        }

        $allMapped = $target['department_id'] !== null && $target['unit_id'] !== null && $target['position_id'] !== null && $oldIds === $target;
        $status = $allMapped ? 'already_mapped' : (in_array('new', $matches, true) ? 'ready_create_master' : 'ready_existing_master');

        return $this->row($employee, $status, $source, $keys, $matches, $target, $reasons, $conflictFields);
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
            if ($targetId === null || (int) $employee->{$foreignKey} !== $targetId) {
                $changes++;
            }
        }

        return [
            'employee_id' => (int) $employee->id,
            'status' => $status,
            'reason' => implode(' ', $reasons),
            'current' => [
                'department_id' => $employee->department_id,
                'unit_id' => $employee->unit_id,
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
     * @return array{departments: int, units: int, positions: int}
     */
    private function createMissingMasters(array $rows): array
    {
        $created = ['departments' => 0, 'units' => 0, 'positions' => 0];
        foreach (collect($rows)->pluck('source', 'keys.department')->sortKeys() as $source) {
            if ($this->findDepartment((string) $source['department']) !== null) {
                continue;
            }
            Department::query()->create([
                'code' => $this->automaticCode('AUTO-D-', $this->normalize($source['department']), fn (string $code): bool => Department::query()->where('code', $code)->exists()),
                'name' => $source['department'],
                'active' => true,
            ]);
            $created['departments']++;
        }

        foreach (collect($rows)->pluck('source', 'keys.unit')->sortKeys() as $source) {
            $department = $this->findDepartment((string) $source['department']);
            if ($department === null) {
                throw new RuntimeException('Department hasil sinkronisasi tidak dapat ditemukan.');
            }
            if ($this->findUnit($department->id, (string) $source['unit']) !== null) {
                continue;
            }
            Unit::query()->create([
                'department_id' => $department->id,
                'code' => $this->automaticCode('AUTO-U-', $this->normalize($source['department']).'|'.$this->normalize($source['unit']), fn (string $code): bool => Unit::query()->where('department_id', $department->id)->where('code', $code)->exists()),
                'name' => $source['unit'],
                'active' => true,
            ]);
            $created['units']++;
        }

        foreach (collect($rows)->pluck('source', 'keys.position')->sortKeys() as $source) {
            $department = $this->findDepartment((string) $source['department']);
            $unit = $department === null ? null : $this->findUnit($department->id, (string) $source['unit']);
            if ($unit === null) {
                throw new RuntimeException('Unit hasil sinkronisasi tidak dapat ditemukan.');
            }
            if ($this->findPosition($unit->id, (string) $source['position']) !== null) {
                continue;
            }
            Position::query()->create([
                'unit_id' => $unit->id,
                'code' => $this->automaticCode('AUTO-P-', $this->normalize($source['department']).'|'.$this->normalize($source['unit']).'|'.$this->normalize($source['position']), fn (string $code): bool => Position::query()->where('unit_id', $unit->id)->where('code', $code)->exists()),
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
            ->select(['id', 'txt_dept', 'txt_biro', 'position', 'department_id', 'unit_id', 'position_id'])
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
        Department::query()->orderBy('id')->lockForUpdate()->get(['id']);
        Unit::query()->orderBy('id')->lockForUpdate()->get(['id']);
        Position::query()->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function masterState(): array
    {
        return [
            'departments' => Department::query()->orderBy('id')->get(['id', 'name'])->map(fn (Department $department): array => ['id' => $department->id, 'name' => $department->name])->all(),
            'units' => Unit::query()->orderBy('id')->get(['id', 'department_id', 'name'])->map(fn (Unit $unit): array => ['id' => $unit->id, 'department_id' => $unit->department_id, 'name' => $unit->name])->all(),
            'positions' => Position::query()->orderBy('id')->get(['id', 'unit_id', 'name'])->map(fn (Position $position): array => ['id' => $position->id, 'unit_id' => $position->unit_id, 'name' => $position->name])->all(),
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

    private function findDepartment(string $name): ?Department
    {
        $matches = Department::query()->get()->filter(fn (Department $department): bool => $this->normalize($department->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findUnit(int $departmentId, string $name): ?Unit
    {
        $matches = Unit::query()->where('department_id', $departmentId)->get()->filter(fn (Unit $unit): bool => $this->normalize($unit->name) === $this->normalize($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findPosition(int $unitId, string $name): ?Position
    {
        $matches = Position::query()->where('unit_id', $unitId)->get()->filter(fn (Position $position): bool => $this->normalize($position->name) === $this->normalize($name));

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
