<?php

namespace App\Services\Employees;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeOrganizationMappingAudit;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeOrganizationMapper
{
    /** @var array<int, array{id: int, name: string}> */
    private array $departments;

    /** @var array<int, array{id: int, department_id: int, department_name: string, name: string}> */
    private array $units;

    /** @var array<int, array{id: int, unit_id: int, unit_name: string, department_id: int, department_name: string, name: string}> */
    private array $positions;

    public function __construct()
    {
        $this->departments = Department::query()->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])->all();

        $departmentNames = collect($this->departments)->pluck('name', 'id');
        $this->units = Unit::query()->get(['id', 'department_id', 'name'])
            ->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'department_id' => $unit->department_id,
                'department_name' => (string) $departmentNames->get($unit->department_id, ''),
                'name' => $unit->name,
            ])->all();

        $unitRecords = collect($this->units)->keyBy('id');
        $this->positions = Position::query()->get(['id', 'unit_id', 'name'])
            ->map(function (Position $position) use ($unitRecords): array {
                $unit = $unitRecords->get($position->unit_id);

                return [
                    'id' => $position->id,
                    'unit_id' => $position->unit_id,
                    'unit_name' => (string) ($unit['name'] ?? ''),
                    'department_id' => (int) ($unit['department_id'] ?? 0),
                    'department_name' => (string) ($unit['department_name'] ?? ''),
                    'name' => $position->name,
                ];
            })->all();
    }

    /**
     * @return array{
     *     ids: array{department_id: ?int, unit_id: ?int, position_id: ?int},
     *     statuses: array{department: string, unit: string, position: string},
     *     conflicts: array<int, string>
     * }
     */
    public function resolve(
        ?string $departmentName,
        ?string $unitName,
        ?string $positionName,
        ?int $departmentId = null,
        ?int $unitId = null,
        ?int $positionId = null,
    ): array {
        $departmentName = $this->clean($departmentName);
        $unitName = $this->clean($unitName);
        $positionName = $this->clean($positionName);
        $ids = compact('departmentId', 'unitId', 'positionId');
        $resolvedIds = [
            'department_id' => $departmentId,
            'unit_id' => $unitId,
            'position_id' => $positionId,
        ];
        $statuses = [
            'department' => $departmentName === null ? ($departmentId === null ? 'empty' : 'existing') : 'unmapped',
            'unit' => $unitName === null ? ($unitId === null ? 'empty' : 'existing') : 'unmapped',
            'position' => $positionName === null ? ($positionId === null ? 'empty' : 'existing') : 'unmapped',
        ];
        $conflicts = [];

        $department = $departmentId === null
            ? null
            : $this->findById($this->departments, $departmentId);

        if ($departmentId !== null && $department === null) {
            $conflicts[] = 'department';
            $statuses['department'] = 'conflict';
        } elseif ($department !== null && $departmentName !== null) {
            if ($this->normalize($departmentName) !== $this->normalize($department['name'])) {
                $conflicts[] = 'department';
                $statuses['department'] = 'conflict';
            } else {
                $statuses['department'] = 'existing';
            }
        } elseif ($department === null && $departmentName !== null) {
            $match = $this->match($departmentName, $this->departments);
            $statuses['department'] = $match['status'];
            $department = $match['record'];
            $resolvedIds['department_id'] = $department['id'] ?? null;
            if ($match['status'] === 'conflict') {
                $conflicts[] = 'department';
            }
        }

        $unit = $unitId === null ? null : $this->findById($this->units, $unitId);
        if ($unitId !== null && $unit === null) {
            $conflicts[] = 'unit';
            $statuses['unit'] = 'conflict';
        } elseif ($unit !== null) {
            if ($department !== null && $unit['department_id'] !== $department['id']) {
                $conflicts[] = 'unit';
                $statuses['unit'] = 'conflict';
            } elseif ($department === null && $departmentName !== null) {
                $conflicts[] = 'department';
                $statuses['department'] = 'conflict';
            } elseif ($department === null && $departmentName === null) {
                $department = $this->findById($this->departments, $unit['department_id']);
                $resolvedIds['department_id'] = $department['id'] ?? null;
                if ($statuses['department'] === 'empty') {
                    $statuses['department'] = 'inferred_existing';
                }
            }

            if ($unitName !== null && $this->normalize($unitName) !== $this->normalize($unit['name'])) {
                $conflicts[] = 'unit';
                $statuses['unit'] = 'conflict';
            } elseif ($unitName !== null) {
                $statuses['unit'] = 'existing';
            }
        } elseif ($unitName !== null) {
            $unitCandidates = $department === null
                ? $this->units
                : array_values(array_filter($this->units, fn (array $record): bool => $record['department_id'] === $department['id']));
            $match = $this->match($unitName, $unitCandidates);

            if ($match['record'] === null) {
                $globalMatch = $this->match($unitName, $this->units);
                if ($globalMatch['record'] !== null) {
                    $match = ['record' => null, 'status' => 'conflict'];
                }
            }

            $statuses['unit'] = $match['status'];
            $unit = $match['record'];
            $resolvedIds['unit_id'] = $unit['id'] ?? null;

            if ($match['status'] === 'conflict') {
                $conflicts[] = 'unit';
            }

            if ($unit !== null && $department === null) {
                if ($departmentName !== null) {
                    $conflicts[] = 'department';
                    $statuses['department'] = 'conflict';
                } else {
                    $department = $this->findById($this->departments, $unit['department_id']);
                    $resolvedIds['department_id'] = $department['id'] ?? null;
                    $statuses['department'] = 'inferred_'.$match['status'];
                }
            }
        }

        $position = $positionId === null ? null : $this->findById($this->positions, $positionId);
        if ($positionId !== null && $position === null) {
            $conflicts[] = 'position';
            $statuses['position'] = 'conflict';
        } elseif ($position !== null) {
            if ($unit !== null && $position['unit_id'] !== $unit['id']) {
                $conflicts[] = 'position';
                $statuses['position'] = 'conflict';
            } elseif ($unit === null && $unitName !== null) {
                $unit = $this->findById($this->units, $position['unit_id']);
                $resolvedIds['unit_id'] = $unit['id'] ?? null;
                if ($unit === null || $this->normalize($unitName) !== $this->normalize($unit['name'])) {
                    $conflicts[] = 'unit';
                    $statuses['unit'] = 'conflict';
                } else {
                    $statuses['unit'] = 'existing';

                    if ($department !== null && $unit['department_id'] !== $department['id']) {
                        $conflicts[] = 'unit';
                        $statuses['unit'] = 'conflict';
                    } elseif ($department === null && $departmentName !== null) {
                        $conflicts[] = 'department';
                        $statuses['department'] = 'conflict';
                    } elseif ($department === null) {
                        $department = $this->findById($this->departments, $unit['department_id']);
                        $resolvedIds['department_id'] = $department['id'] ?? null;
                        if ($statuses['department'] === 'empty') {
                            $statuses['department'] = 'inferred_existing';
                        }
                    }
                }
            } elseif ($unit === null) {
                $unit = $this->findById($this->units, $position['unit_id']);
                $resolvedIds['unit_id'] = $unit['id'] ?? null;
                if ($statuses['unit'] === 'empty') {
                    $statuses['unit'] = 'inferred_existing';
                }

                if ($department !== null && $unit !== null && $unit['department_id'] !== $department['id']) {
                    $conflicts[] = 'position';
                    $statuses['position'] = 'conflict';
                } elseif ($department === null && $departmentName !== null) {
                    $conflicts[] = 'department';
                    $statuses['department'] = 'conflict';
                } elseif ($department === null && $departmentName === null && $unit !== null) {
                    $department = $this->findById($this->departments, $unit['department_id']);
                    $resolvedIds['department_id'] = $department['id'] ?? null;
                    if ($statuses['department'] === 'empty') {
                        $statuses['department'] = 'inferred_existing';
                    }
                }
            }

            if ($positionName !== null && $this->normalize($positionName) !== $this->normalize($position['name'])) {
                $conflicts[] = 'position';
                $statuses['position'] = 'conflict';
            } elseif ($positionName !== null) {
                $statuses['position'] = 'existing';
            }
        } elseif ($positionName !== null) {
            if ($unit !== null) {
                $positionCandidates = array_values(array_filter($this->positions, fn (array $record): bool => $record['unit_id'] === $unit['id']));
            } elseif ($department !== null) {
                $positionCandidates = array_values(array_filter($this->positions, fn (array $record): bool => $record['department_id'] === $department['id']));
            } else {
                $positionCandidates = $this->positions;
            }

            $match = $this->match($positionName, $positionCandidates);

            if ($match['record'] === null) {
                $globalMatch = $this->match($positionName, $this->positions);
                if ($globalMatch['record'] !== null) {
                    $match = ['record' => null, 'status' => 'conflict'];
                }
            }

            $statuses['position'] = $match['status'];
            $position = $match['record'];
            $resolvedIds['position_id'] = $position['id'] ?? null;

            if ($match['status'] === 'conflict') {
                $conflicts[] = 'position';
            }

            if ($position !== null && $unit === null) {
                if ($unitName !== null) {
                    $conflicts[] = 'unit';
                    $statuses['unit'] = 'conflict';
                } else {
                    $unit = $this->findById($this->units, $position['unit_id']);
                    $resolvedIds['unit_id'] = $unit['id'] ?? null;
                    $statuses['unit'] = 'inferred_'.$match['status'];
                }
            }

            if ($position !== null && $unit !== null && $department === null) {
                if ($departmentName !== null) {
                    $conflicts[] = 'department';
                    $statuses['department'] = 'conflict';
                } else {
                    $department = $this->findById($this->departments, $unit['department_id']);
                    $resolvedIds['department_id'] = $department['id'] ?? null;
                    if ($statuses['department'] === 'empty') {
                        $statuses['department'] = 'inferred_'.$match['status'];
                    }
                }
            }
        }

        return [
            'ids' => $resolvedIds,
            'statuses' => $statuses,
            'conflicts' => array_values(array_unique($conflicts)),
        ];
    }

    /** @param array<int, array<string, int|string>> $records */
    private function findById(array $records, int $id): ?array
    {
        foreach ($records as $record) {
            if ($record['id'] === $id) {
                return $record;
            }
        }

        return null;
    }

    /** @param array<int, array<string, int|string>> $records
     * @return array{record: array<string, int|string>|null, status: string}
     */
    private function match(string $name, array $records): array
    {
        $exact = array_values(array_filter($records, fn (array $record): bool => $record['name'] === $name));

        if (count($exact) === 1) {
            return ['record' => $exact[0], 'status' => 'exact'];
        }

        if (count($exact) > 1) {
            return ['record' => null, 'status' => 'conflict'];
        }

        $normalizedName = $this->normalize($name);
        $normalized = array_values(array_filter(
            $records,
            fn (array $record): bool => $this->normalize((string) $record['name']) === $normalizedName,
        ));

        if (count($normalized) === 1) {
            return ['record' => $normalized[0], 'status' => 'normalized'];
        }

        return ['record' => null, 'status' => count($normalized) > 1 ? 'conflict' : 'unmapped'];
    }

    public function exportTemplate(?string $path = null): string
    {
        $exportPath = $path ?? storage_path('app/imports/employee-organization-mapping-template.csv');
        $directory = dirname($exportPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Gagal membuat direktori export mapping: '.$directory);
        }

        $handle = fopen($exportPath, 'wb');
        fputcsv($handle, [
            'employee_id',
            'employee_name',
            'legacy_department',
            'legacy_unit',
            'legacy_position',
            'department_id',
            'department_name',
            'unit_id',
            'unit_name',
            'position_id',
            'position_name',
            'mapping_status',
            'mapping_note',
        ]);

        foreach (Employee::query()->select(['id', 'txt_dept', 'organizational_unit', 'position'])->orderBy('id')->get() as $employee) {
            $resolved = $this->resolve(
                $employee->txt_dept,
                $employee->organizational_unit,
                $employee->position,
                $employee->department_id,
                $employee->unit_id,
                $employee->position_id,
            );

            [$status, $note] = $this->templateRowStatus($resolved);

            fputcsv($handle, [
                $employee->id,
                '',
                $employee->txt_dept,
                $employee->organizational_unit,
                $employee->position,
                $resolved['ids']['department_id'] ?? '',
                $this->getDepartmentName((int) ($resolved['ids']['department_id'] ?? 0)),
                $resolved['ids']['unit_id'] ?? '',
                $this->getUnitName((int) ($resolved['ids']['unit_id'] ?? 0)),
                $resolved['ids']['position_id'] ?? '',
                $this->getPositionName((int) ($resolved['ids']['position_id'] ?? 0)),
                $status,
                $note,
            ]);
        }

        fclose($handle);

        return $exportPath;
    }

    /**
     * @return array{valid: int, invalid: int, review_required: int, rows: array<int, array<string, string|int|null>>, errors: array<int, string>}
     */
    public function validateCsv(string $path): array
    {
        $rows = $this->readCsvRows($path);
        $seenEmployees = [];
        $errors = [];
        $valid = 0;
        $invalid = 0;
        $reviewRequired = 0;

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $employeeId = $this->parseNullableInteger($row['employee_id'] ?? null);
            $departmentId = $this->parseNullableInteger($row['department_id'] ?? null);
            $unitId = $this->parseNullableInteger($row['unit_id'] ?? null);
            $positionId = $this->parseNullableInteger($row['position_id'] ?? null);
            $status = strtoupper(trim((string) ($row['mapping_status'] ?? '')));

            if ($employeeId === null || ! Employee::query()->whereKey($employeeId)->exists()) {
                $errors[] = sprintf('Baris %d: employee_id tidak valid.', $lineNumber);
                $invalid++;

                continue;
            }

            if (isset($seenEmployees[$employeeId])) {
                $errors[] = sprintf('Baris %d: employee_id duplikat.', $lineNumber);
                $invalid++;

                continue;
            }
            $seenEmployees[$employeeId] = true;

            if (! in_array($status, ['UNREVIEWED', 'AUTO_CANDIDATE', 'REVIEW_REQUIRED', 'APPROVED', 'UNMATCHED'], true)) {
                $errors[] = sprintf('Baris %d: mapping_status tidak valid.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($status === 'APPROVED' && $departmentId === null) {
                $errors[] = sprintf('Baris %d: department_id wajib saat status APPROVED.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($status === 'APPROVED' && $unitId === null) {
                $errors[] = sprintf('Baris %d: unit_id wajib saat status APPROVED.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($status === 'APPROVED' && $positionId === null) {
                $errors[] = sprintf('Baris %d: position_id wajib saat status APPROVED.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($departmentId !== null && ! Department::query()->whereKey($departmentId)->exists()) {
                $errors[] = sprintf('Baris %d: department_id tidak ditemukan.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($unitId !== null && ! Unit::query()->whereKey($unitId)->exists()) {
                $errors[] = sprintf('Baris %d: unit_id tidak ditemukan.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($positionId !== null && ! Position::query()->whereKey($positionId)->exists()) {
                $errors[] = sprintf('Baris %d: position_id tidak ditemukan.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($departmentId !== null && $unitId !== null && ! Unit::query()->whereKey($unitId)->where('department_id', $departmentId)->exists()) {
                $errors[] = sprintf('Baris %d: unit tidak termasuk department yang ditargetkan.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($unitId !== null && $positionId !== null && ! Position::query()->whereKey($positionId)->where('unit_id', $unitId)->exists()) {
                $errors[] = sprintf('Baris %d: position tidak termasuk unit yang ditargetkan.', $lineNumber);
                $invalid++;

                continue;
            }

            if ($status === 'REVIEW_REQUIRED' || $status === 'UNREVIEWED' || $status === 'UNMATCHED' || $status === 'AUTO_CANDIDATE') {
                $reviewRequired++;
            }

            $valid++;
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'review_required' => $reviewRequired,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{employees_to_update: int, department_changes: int, unit_changes: int, position_changes: int, unmapped: int, invalid: int, details: array<int, array<string, mixed>>}
     */
    public function previewCsv(string $path): array
    {
        $validation = $this->validateCsv($path);

        if ($validation['invalid'] > 0) {
            throw new RuntimeException('Preview dibatalkan karena CSV tidak valid.');
        }

        $details = [];
        $employeesToUpdate = 0;
        $departmentChanges = 0;
        $unitChanges = 0;
        $positionChanges = 0;
        $unmapped = 0;

        foreach ($validation['rows'] as $row) {
            $status = strtoupper(trim((string) ($row['mapping_status'] ?? '')));
            $employeeId = $this->parseNullableInteger($row['employee_id'] ?? null);

            if ($employeeId === null) {
                continue;
            }

            if ($status === 'UNMATCHED') {
                $unmapped++;

                continue;
            }

            if ($status !== 'APPROVED') {
                continue;
            }

            $employee = Employee::query()->find($employeeId);
            if ($employee === null) {
                continue;
            }

            $departmentId = $this->parseNullableInteger($row['department_id'] ?? null);
            $unitId = $this->parseNullableInteger($row['unit_id'] ?? null);
            $positionId = $this->parseNullableInteger($row['position_id'] ?? null);
            $changes = [];

            if ($departmentId !== null && (int) $employee->department_id !== (int) $departmentId) {
                $changes['department_id'] = [(int) $employee->department_id, (int) $departmentId];
            }

            if ($unitId !== null && (int) $employee->unit_id !== (int) $unitId) {
                $changes['unit_id'] = [(int) $employee->unit_id, (int) $unitId];
            }

            if ($positionId !== null && (int) $employee->position_id !== (int) $positionId) {
                $changes['position_id'] = [(int) $employee->position_id, (int) $positionId];
            }

            if ($changes === []) {
                continue;
            }

            $employeesToUpdate++;
            $departmentChanges += array_key_exists('department_id', $changes) ? 1 : 0;
            $unitChanges += array_key_exists('unit_id', $changes) ? 1 : 0;
            $positionChanges += array_key_exists('position_id', $changes) ? 1 : 0;

            $details[] = [
                'employee_id' => $employee->id,
                'employee_name' => '',
                'current' => [
                    'department' => $employee->txt_dept,
                    'organizational_unit' => $employee->organizational_unit,
                    'position' => $employee->position,
                ],
                'target' => [
                    'department_id' => $departmentId,
                    'unit_id' => $unitId,
                    'position_id' => $positionId,
                ],
                'change_count' => count($changes),
            ];
        }

        return [
            'employees_to_update' => $employeesToUpdate,
            'department_changes' => $departmentChanges,
            'unit_changes' => $unitChanges,
            'position_changes' => $positionChanges,
            'unmapped' => $unmapped,
            'invalid' => $validation['invalid'],
            'details' => $details,
        ];
    }

    /**
     * @return array{total_approved: int, successfully_updated: int, skipped: int, failed: int, department_mapped: int, unit_mapped: int, position_mapped: int, audit_records: int}
     */
    public function applyCsv(string $path): array
    {
        $validation = $this->validateCsv($path);

        if ($validation['invalid'] > 0) {
            throw new RuntimeException('Apply dibatalkan karena CSV tidak valid.');
        }

        $rows = $validation['rows'];
        $approvedRows = array_values(array_filter($rows, fn (array $row): bool => strtoupper((string) ($row['mapping_status'] ?? '')) === 'APPROVED'));

        if ($approvedRows === []) {
            throw new RuntimeException('Tidak ada baris mapping dengan status APPROVED.');
        }

        $results = [
            'total_approved' => count($approvedRows),
            'successfully_updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'department_mapped' => 0,
            'unit_mapped' => 0,
            'position_mapped' => 0,
            'audit_records' => 0,
        ];

        DB::transaction(function () use ($approvedRows, &$results): void {
            foreach ($approvedRows as $row) {
                $employeeId = $this->parseNullableInteger($row['employee_id'] ?? null);
                if ($employeeId === null) {
                    $results['failed']++;

                    continue;
                }

                $employee = Employee::query()->find($employeeId);
                if ($employee === null) {
                    $results['failed']++;

                    continue;
                }

                $departmentId = $this->parseNullableInteger($row['department_id'] ?? null);
                $unitId = $this->parseNullableInteger($row['unit_id'] ?? null);
                $positionId = $this->parseNullableInteger($row['position_id'] ?? null);

                $updates = [];
                if ($departmentId !== null && (int) $employee->department_id !== (int) $departmentId) {
                    $updates['department_id'] = (int) $departmentId;
                }

                if ($unitId !== null && (int) $employee->unit_id !== (int) $unitId) {
                    $updates['unit_id'] = (int) $unitId;
                }

                if ($positionId !== null && (int) $employee->position_id !== (int) $positionId) {
                    $updates['position_id'] = (int) $positionId;
                }

                if ($updates === []) {
                    $results['skipped']++;

                    continue;
                }

                $oldDepartmentId = $employee->department_id;
                $oldUnitId = $employee->unit_id;
                $oldPositionId = $employee->position_id;
                $employee->fill($updates);
                $employee->save();

                $results['successfully_updated']++;
                $results['department_mapped'] += array_key_exists('department_id', $updates) ? 1 : 0;
                $results['unit_mapped'] += array_key_exists('unit_id', $updates) ? 1 : 0;
                $results['position_mapped'] += array_key_exists('position_id', $updates) ? 1 : 0;

                EmployeeOrganizationMappingAudit::query()->create([
                    'employee_id' => $employee->id,
                    'old_department_id' => $oldDepartmentId,
                    'new_department_id' => $updates['department_id'] ?? $oldDepartmentId,
                    'old_unit_id' => $oldUnitId,
                    'new_unit_id' => $updates['unit_id'] ?? $oldUnitId,
                    'old_position_id' => $oldPositionId,
                    'new_position_id' => $updates['position_id'] ?? $oldPositionId,
                    'executed_by' => Auth::id(),
                    'executed_at' => now(),
                ]);
                $results['audit_records']++;
            }
        });

        return $results;
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array{0: string, 1: string}
     */
    private function templateRowStatus(array $resolved): array
    {
        $statuses = array_values($resolved['statuses'] ?? []);
        $hasUnmapped = in_array('unmapped', $statuses, true);
        $hasConflict = in_array('conflict', $statuses, true);

        if ($hasUnmapped) {
            return ['UNMATCHED', 'Legacy value tidak memiliki kandidat organisasi yang jelas.'];
        }

        if ($hasConflict) {
            return ['REVIEW_REQUIRED', 'Candidate organisasi tidak unik atau mengalami konflik konteks.'];
        }

        if ($statuses !== [] && count(array_filter($statuses, fn (string $status): bool => in_array($status, ['exact', 'normalized', 'existing'], true) || str_starts_with($status, 'inferred_'))) > 0) {
            return ['AUTO_CANDIDATE', 'Candidate otomatis terdeteksi; review manusia tetap diperlukan sebelum apply.'];
        }

        return ['UNREVIEWED', 'Belum ditinjau.'];
    }

    /** @param array<int, array<string, string|int|null>> $rows */
    private function readCsvRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('File mapping tidak ditemukan: '.$path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File mapping tidak bisa dibaca: '.$path);
        }

        $header = fgetcsv($handle);
        if ($header === false || $header === []) {
            fclose($handle);
            throw new RuntimeException('File mapping kosong atau tidak memiliki header yang valid.');
        }

        $expected = [
            'employee_id',
            'employee_name',
            'legacy_department',
            'legacy_unit',
            'legacy_position',
            'department_id',
            'department_name',
            'unit_id',
            'unit_name',
            'position_id',
            'position_name',
            'mapping_status',
            'mapping_note',
        ];

        $normalizedHeader = array_map('strtolower', array_map('trim', $header));
        $missing = array_diff($expected, $normalizedHeader);
        if ($missing !== []) {
            fclose($handle);
            throw new RuntimeException('Header file CSV tidak valid. Kolom wajib tidak lengkap: '.implode(', ', $missing));
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || array_filter($row, fn ($value): bool => trim((string) $value) !== '') === []) {
                continue;
            }

            $mappedRow = [];
            foreach ($header as $index => $column) {
                $mappedRow[strtolower(trim((string) $column))] = $row[$index] ?? null;
            }
            $rows[] = $mappedRow;
        }

        fclose($handle);

        return $rows;
    }

    private function parseNullableInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $int = filter_var(trim((string) $value), FILTER_VALIDATE_INT);

        return $int === false ? null : (int) $int;
    }

    private function getDepartmentName(int $id): string
    {
        return (string) Department::query()->whereKey($id)->value('name');
    }

    private function getUnitName(int $id): string
    {
        return (string) Unit::query()->whereKey($id)->value('name');
    }

    private function getPositionName(int $id): string
    {
        return (string) Position::query()->whereKey($id)->value('name');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }
}
