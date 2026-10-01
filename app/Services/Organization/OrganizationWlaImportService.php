<?php

namespace App\Services\Organization;

use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class OrganizationWlaImportService
{
    /**
     * @return array{
     *   source_rows: int,
     *   unique_departments: int,
     *   unique_units: int,
     *   unique_positions: int,
     *   duplicate_rows_removed: int,
     *   invalid_rows: int,
     *   departments: array{new: int, existing: int},
     *   units: array{new: int, existing: int},
     *   positions: array{new: int, existing: int},
     *   dry_run: bool,
     *   database_changes: int,
     *   conflicts: int,
     *   created: array{departments: int, units: int, positions: int},
     *   existing: array{departments: int, units: int, positions: int},
     * }
     */
    public function import(string $path, bool $dryRun = true): array
    {
        $resolvedPath = $this->resolvePath($path);
        $rows = $this->readTabelPosisiRows($resolvedPath);

        if ($rows === []) {
            throw new RuntimeException('Sheet "Tabel Posisi" tidak memiliki data yang valid.');
        }

        $deduplicated = $this->deduplicateRows($rows);
        $uniqueRows = $deduplicated['rows'];

        $report = [
            'source_rows' => count($rows),
            'unique_departments' => count($deduplicated['departments']),
            'unique_units' => count($deduplicated['units']),
            'unique_positions' => count($deduplicated['positions']),
            'duplicate_rows_removed' => count($rows) - count($uniqueRows),
            'invalid_rows' => 0,
            'departments' => ['new' => 0, 'existing' => 0],
            'units' => ['new' => 0, 'existing' => 0],
            'positions' => ['new' => 0, 'existing' => 0],
            'dry_run' => $dryRun,
            'database_changes' => 0,
            'conflicts' => 0,
            'created' => ['departments' => 0, 'units' => 0, 'positions' => 0],
            'existing' => ['departments' => 0, 'units' => 0, 'positions' => 0],
        ];

        if ($dryRun) {
            foreach ($uniqueRows as $positionRow) {
                $department = $this->findDepartment($positionRow['department']);
                $unit = $department !== null ? $this->findUnit($department->id, $positionRow['unit']) : null;
                $position = $unit !== null ? $this->findPosition($unit->id, $positionRow['position']) : null;

                if ($department === null) {
                    $report['departments']['new']++;
                } else {
                    $report['departments']['existing']++;
                }

                if ($unit === null) {
                    $report['units']['new']++;
                } else {
                    $report['units']['existing']++;
                }

                if ($position === null) {
                    $report['positions']['new']++;
                } else {
                    $report['positions']['existing']++;
                }
            }

            $report['database_changes'] = 0;

            return $report;
        }

        return DB::transaction(function () use ($uniqueRows, $deduplicated, $report): array {
            $createdDepartments = 0;
            $createdUnits = 0;
            $createdPositions = 0;

            foreach ($uniqueRows as $positionRow) {
                $department = $this->ensureDepartment($positionRow['department']);
                $unit = $this->ensureUnit($department, $positionRow['unit']);
                $this->ensurePosition($unit, $positionRow['position']);

                $report['departments']['existing'] += $department->wasRecentlyCreated ? 0 : 1;
                $report['departments']['new'] += $department->wasRecentlyCreated ? 1 : 0;
                $report['units']['existing'] += $unit->wasRecentlyCreated ? 0 : 1;
                $report['units']['new'] += $unit->wasRecentlyCreated ? 1 : 0;

                $position = Position::query()
                    ->where('unit_id', $unit->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim($positionRow['position']))])
                    ->first();

                if ($position !== null && $position->wasRecentlyCreated) {
                    $createdPositions++;
                }
            }

            $report['created']['departments'] = Department::query()->where('code', 'like', 'ORG-D-%')->count();
            $report['created']['units'] = Unit::query()->where('code', 'like', 'ORG-U-%')->count();
            $report['created']['positions'] = Position::query()->where('code', 'like', 'ORG-P-%')->count();
            $report['existing']['departments'] = $report['unique_departments'] - $report['created']['departments'];
            $report['existing']['units'] = $report['unique_units'] - $report['created']['units'];
            $report['existing']['positions'] = $report['unique_positions'] - $report['created']['positions'];
            $report['database_changes'] = $report['created']['departments'] + $report['created']['units'] + $report['created']['positions'];

            return $report;
        });
    }

    /**
     * @return array<int, array{department: string, unit: string, position: string}>
     */
    private function readTabelPosisiRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka: '.$path);
        }

        $sheetFiles = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (str_starts_with((string) $name, 'xl/worksheets/') && str_ends_with((string) $name, '.xml')) {
                $sheetFiles[] = $name;
            }
        }
        $zip->close();

        if ($sheetFiles === []) {
            throw new RuntimeException('Sheet "Tabel Posisi" tidak ditemukan. Pastikan file Excel memiliki sheet dengan nama yang benar.');
        }

        $sharedStrings = $this->readSharedStrings($path);

        foreach ($sheetFiles as $sheetFile) {
            $sheetXml = $this->readSheetXml($path, $sheetFile);
            $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);
            if ($sheet === false) {
                continue;
            }

            $headerValues = [];
            $rows = [];
            $rowIndex = 0;
            foreach ($sheet->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $reference = (string) ($cell['r'] ?? '');
                    $column = $this->columnIndexFromReference($reference);
                    $cells[$column] = $this->readCellValue($cell, $sharedStrings);
                }

                if ($rowIndex === 0) {
                    $headerValues = [
                        'A' => trim((string) ($cells['A'] ?? '')),
                        'B' => trim((string) ($cells['B'] ?? '')),
                        'C' => trim((string) ($cells['C'] ?? '')),
                        'D' => trim((string) ($cells['D'] ?? '')),
                    ];

                    $rowIndex++;
                    continue;
                }

                if (count(array_filter($cells, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                    $rowIndex++;
                    continue;
                }

                $rows[] = [
                    'department' => trim((string) ($cells['B'] ?? '')),
                    'unit' => trim((string) ($cells['C'] ?? '')),
                    'position' => trim((string) ($cells['D'] ?? '')),
                ];
                $rowIndex++;
            }

            $normalizedHeader = [
                'A' => $this->normalizeHeader($headerValues['A'] ?? ''),
                'B' => $this->normalizeHeader($headerValues['B'] ?? ''),
                'C' => $this->normalizeHeader($headerValues['C'] ?? ''),
                'D' => $this->normalizeHeader($headerValues['D'] ?? ''),
            ];

            if (in_array('department', $normalizedHeader, true) && in_array('unit', $normalizedHeader, true) && (in_array('nama posisi', $normalizedHeader, true) || in_array('position', $normalizedHeader, true))) {
                $this->validateHeader($headerValues);

                return $rows;
            }
        }

        throw new RuntimeException('Sheet "Tabel Posisi" tidak ditemukan. Pastikan file Excel memiliki sheet dengan nama yang benar.');
    }

    /**
     * @param array<string, string> $headerRow
     */
    private function validateHeader(array $headerRow): void
    {
        $normalized = [
            'A' => $this->normalizeHeader($headerRow['A'] ?? ''),
            'B' => $this->normalizeHeader($headerRow['B'] ?? ''),
            'C' => $this->normalizeHeader($headerRow['C'] ?? ''),
            'D' => $this->normalizeHeader($headerRow['D'] ?? ''),
        ];

        if (! in_array('no', $normalized, true)) {
            throw new RuntimeException('Header "No" tidak ditemukan pada kolom A sheet "Tabel Posisi".');
        }

        if (! in_array('department', $normalized, true)) {
            throw new RuntimeException('Header "Department" tidak ditemukan pada sheet "Tabel Posisi".');
        }

        if (! in_array('unit', $normalized, true)) {
            throw new RuntimeException('Header "Unit" tidak ditemukan pada sheet "Tabel Posisi".');
        }

        if (! in_array('nama posisi', $normalized, true) && ! in_array('position', $normalized, true)) {
            throw new RuntimeException('Header "Nama Posisi" tidak ditemukan pada sheet "Tabel Posisi".');
        }
    }

    /**
     * @return array<int, array{department: string, unit: string, position: string}>
     */
    private function deduplicateRows(array $rows): array
    {
        $departmentKeyMap = [];
        $unitKeyMap = [];
        $positionKeyMap = [];
        $deduplicated = [];

        foreach ($rows as $row) {
            $departmentName = $this->normalizeName($row['department']);
            $unitName = $this->normalizeName($row['unit']);
            $positionName = $this->normalizeName($row['position']);

            if ($departmentName === '' || $unitName === '' || $positionName === '') {
                continue;
            }

            $departmentKey = $departmentName;
            if (! isset($departmentKeyMap[$departmentKey])) {
                $departmentKeyMap[$departmentKey] = trim((string) $row['department']);
            }

            $unitKey = $departmentName.'|'.$unitName;
            if (! isset($unitKeyMap[$unitKey])) {
                $unitKeyMap[$unitKey] = [
                    'department' => trim((string) $row['department']),
                    'unit' => trim((string) $row['unit']),
                ];
            }

            $positionKey = $departmentName.'|'.$unitName.'|'.$positionName;
            if (! isset($positionKeyMap[$positionKey])) {
                $positionKeyMap[$positionKey] = [
                    'department' => trim((string) $row['department']),
                    'unit' => trim((string) $row['unit']),
                    'position' => trim((string) $row['position']),
                ];
            }
        }

        foreach ($positionKeyMap as $entry) {
            $deduplicated[] = $entry;
        }

        return [
            'departments' => array_values(array_unique(array_map(fn (array $entry): string => $this->normalizeName($entry['department']), $deduplicated))),
            'units' => array_values(array_unique(array_map(fn (array $entry): string => $this->normalizeName($entry['department']).'|'.$this->normalizeName($entry['unit']), $deduplicated))),
            'positions' => array_values(array_unique(array_map(fn (array $entry): string => $this->normalizeName($entry['department']).'|'.$this->normalizeName($entry['unit']).'|'.$this->normalizeName($entry['position']), $deduplicated))),
            'rows' => array_values($positionKeyMap),
        ];
    }

    private function findDepartment(string $name): ?Department
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        return Department::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    private function findUnit(int $departmentId, string $name): ?Unit
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        return Unit::query()
            ->where('department_id', $departmentId)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    private function findPosition(int $unitId, string $name): ?Position
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return null;
        }

        return Position::query()
            ->where('unit_id', $unitId)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    private function ensureDepartment(string $name): Department
    {
        $normalized = $this->normalizeName($name);
        $department = Department::query()->whereRaw('LOWER(name) = ?', [$normalized])->first();

        if ($department !== null) {
            return $department;
        }

        return Department::query()->create([
            'code' => $this->technicalCode('ORG-D', $normalized),
            'name' => trim($name),
            'active' => true,
        ]);
    }

    private function ensureUnit(Department $department, string $name): Unit
    {
        $normalized = $this->normalizeName($name);
        $unit = Unit::query()
            ->where('department_id', $department->id)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if ($unit !== null) {
            return $unit;
        }

        return Unit::query()->create([
            'department_id' => $department->id,
            'code' => $this->technicalCode('ORG-U', $department->name.'|'.$normalized),
            'name' => trim($name),
            'active' => true,
        ]);
    }

    private function ensurePosition(Unit $unit, string $name): Position
    {
        $normalized = $this->normalizeName($name);
        $position = Position::query()
            ->where('unit_id', $unit->id)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if ($position !== null) {
            return $position;
        }

        return Position::query()->create([
            'unit_id' => $unit->id,
            'code' => $this->technicalCode('ORG-P', $unit->department->name.'|'.$unit->name.'|'.$normalized),
            'name' => trim($name),
            'active' => true,
        ]);
    }

    private function technicalCode(string $prefix, string $canonical): string
    {
        return $prefix.'-'.substr(md5($canonical), 0, 12);
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    }

    private function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);
        $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);

        return strtolower(trim($value));
    }

    private function columnIndexFromReference(string $reference): string
    {
        $reference = strtoupper((string) $reference);
        $letters = preg_replace('/\d+/', '', $reference) ?? '';

        return strtoupper($letters === '' ? 'A' : $letters);
    }

    private function readCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        if ($type === 's' && isset($cell->v)) {
            $index = (int) ((string) $cell->v);

            return $sharedStrings[$index] ?? '';
        }

        return trim((string) ($cell->v ?? ''));
    }

    /** @return array<int, string> */
    private function readSharedStrings(string $path): array
    {
        $zip = new ZipArchive();
        $open = $zip->open($path);
        if ($open !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka untuk shared strings.');
        }

        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        if ($document === false) {
            return [];
        }

        $strings = [];
        foreach ($document->si as $item) {
            $text = '';
            foreach ($item->children() as $child) {
                if ($child->getName() === 't') {
                    $text .= (string) $child;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function resolvePath(string $path): string
    {
        $candidates = [];
        $resolved = trim($path);
        if ($resolved === '') {
            $resolved = 'storage/app/import/A. Form WLA.xlsx';
        }

        $candidates[] = $resolved;

        if (! preg_match('/^[A-Za-z]:[\\\\\/]/', $resolved) && ! str_starts_with($resolved, '/')) {
            $candidates[] = base_path($resolved);
            $candidates[] = base_path(str_replace('/imports/', '/import/', $resolved));
            $candidates[] = base_path(str_replace('/import/', '/imports/', $resolved));
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('File Excel WLA tidak ditemukan. Cek path: '.$resolved);
    }

    private function readSheetXml(string $path, string $sheetFile): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka: '.$path);
        }

        $xml = $zip->getFromName($sheetFile);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Sheet XML pada file Excel tidak dapat dibaca.');
        }

        return $xml;
    }
}
