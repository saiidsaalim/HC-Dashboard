<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\EmployeeImportBatch;
use App\Models\EmployeeImportRow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class EmployeeImportStagingService
{
    private const HEADERS = [
        'SAP', 'ID Number', 'AGKN', 'Position id', 'Personal Number', 'Position',
        'Employee Subgroup', 'Cost Ctr', 'TXT_DIR', 'TXT_DEPT', 'TXT_BIRO', 'TXT_SECT',
        'Birth date', 'Gender Key', 'Personnel Area', 'abrevation position',
        'abrevation organization', 'Organizational Unit', 'Cost Center', 'Masa Kontrak',
        'E-mail', 'Religious', 'Usia', 'Tempat Lahir', 'Pendidikan', 'Hiring',
        'Organilk', 'Alamat',
    ];

    /** @var array<string, string> */
    private const CANONICAL_FIELDS = [
        'SAP' => 'sap',
        'ID Number' => 'id_number',
        'AGKN' => 'agkn',
        'Position id' => 'position_id_source',
        'Personal Number' => 'personal_number',
        'Position' => 'position',
        'Employee Subgroup' => 'employee_subgroup',
        'Cost Ctr' => 'cost_ctr',
        'TXT_DIR' => 'txt_dir',
        'TXT_DEPT' => 'txt_dept',
        'TXT_BIRO' => 'txt_biro',
        'TXT_SECT' => 'txt_sect',
        'Birth date' => 'birth_date',
        'Gender Key' => 'gender_key',
        'Personnel Area' => 'personnel_area',
        'abrevation position' => 'abrevation_position',
        'abrevation organization' => 'abrevation_organization',
        'Organizational Unit' => 'organizational_unit',
        'Cost Center' => 'cost_center',
        'Masa Kontrak' => 'masa_kontrak',
        'E-mail' => 'email',
        'Religious' => 'religious',
        'Usia' => 'usia',
        'Tempat Lahir' => 'tempat_lahir',
        'Pendidikan' => 'pendidikan',
        'Hiring' => 'hiring',
        'Organilk' => 'organilk',
        'Alamat' => 'alamat',
    ];

    /** @var array<string, int> */
    private const STRING_LIMITS = [
        'sap' => 50,
        'id_number' => 100,
        'agkn' => 100,
        'position_id_source' => 100,
        'personal_number' => 100,
        'position' => 255,
        'employee_subgroup' => 100,
        'cost_ctr' => 100,
        'txt_dir' => 255,
        'txt_dept' => 255,
        'txt_biro' => 255,
        'txt_sect' => 255,
        'gender_key' => 20,
        'personnel_area' => 100,
        'abrevation_position' => 100,
        'abrevation_organization' => 100,
        'organizational_unit' => 255,
        'cost_center' => 100,
        'email' => 255,
        'religious' => 100,
        'tempat_lahir' => 255,
        'pendidikan' => 255,
    ];

    public function stage(UploadedFile $file, User $actor): EmployeeImportBatch
    {
        $batch = EmployeeImportBatch::query()->create([
            'source_file' => 'pending',
            'source_type' => strtolower($file->getClientOriginalExtension()),
            'imported_by' => $actor->id,
            'imported_at' => now(),
            'status' => 'PENDING',
        ]);

        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = Storage::disk('local')->putFileAs(
            'employee-imports',
            $file,
            'batch-'.$batch->id.'.'.$extension,
        );

        if ($storedPath === false) {
            $batch->update(['source_file' => 'unavailable', 'status' => 'INVALID']);
            $this->storeFileError($batch, ['Source file could not be stored privately.']);

            return $batch->fresh('rows');
        }

        $batch->update(['source_file' => $storedPath]);

        try {
            [$headers, $sourceRows] = $extension === 'xlsx'
                ? $this->readXlsx($file->getRealPath())
                : $this->readCsv($file->getRealPath());
            $headerErrors = $this->validateHeaders($headers);

            if ($headerErrors !== []) {
                $this->storeFileError($batch, $headerErrors, ['headers' => $headers]);

                return $batch->fresh('rows');
            }

            if ($sourceRows === []) {
                $this->storeFileError($batch, ['Source file contains no employee rows.'], ['headers' => $headers]);

                return $batch->fresh('rows');
            }

            DB::transaction(fn () => $this->stageRows($batch, $headers, $sourceRows));
            $batch->update(['status' => 'REVIEW_REQUIRED']);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->storeFileError($batch, [$exception->getMessage()]);
        }

        $this->refreshBatchCounters($batch);

        return $batch->fresh('rows');
    }

    public function approve(EmployeeImportRow $row, User $actor): EmployeeImportRow
    {
        if ($row->processed_at !== null) {
            throw new InvalidArgumentException('Processed rows cannot be approved again.');
        }

        if ($row->validation_status === 'INVALID') {
            throw new InvalidArgumentException('Invalid rows cannot be approved.');
        }

        if (! in_array($row->validation_status, ['VALID', 'REVIEW_REQUIRED'], true)) {
            throw new InvalidArgumentException('Only validated rows can be approved.');
        }

        if ($row->mapping_status === 'UNMATCHED') {
            throw new InvalidArgumentException('Rows with unmatched mappings cannot be approved.');
        }

        $payload = $row->normalized_payload ?? [];

        $row->forceFill([
            'normalized_payload' => $payload,
            'validation_status' => 'VALID',
            'mapping_status' => 'APPROVED',
            'mapping_errors' => null,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $this->refreshBatchCounters($row->batch);

        return $row->fresh();
    }

    public function approveAutoCandidates(EmployeeImportBatch $batch, User $actor): int
    {
        return DB::transaction(function () use ($batch, $actor): int {
            $rows = $batch->rows()
                ->where('validation_status', 'VALID')
                ->where('mapping_status', 'AUTO_CANDIDATE')
                ->whereNull('processed_at')
                ->orderBy('source_row')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $row->forceFill([
                    'mapping_status' => 'APPROVED',
                    'mapping_errors' => null,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ])->save();
            }

            if ($rows->isNotEmpty()) {
                $this->refreshBatchCounters($batch);
            }

            return $rows->count();
        });
    }

    /** @return array{processed: int, skipped: int, review_required: int} */
    public function process(EmployeeImportBatch $batch, User $actor): array
    {
        $result = [
            'processed' => 0,
            'skipped' => 0,
            'review_required' => $batch->rows()
                ->whereNull('processed_at')
                ->where(function ($query): void {
                    $query->where('validation_status', 'REVIEW_REQUIRED')
                        ->orWhere('mapping_status', 'REVIEW_REQUIRED');
                })
                ->count(),
        ];

        DB::transaction(function () use ($batch, $actor, &$result): void {
            $rows = $batch->rows()
                ->where('mapping_status', 'APPROVED')
                ->where('validation_status', 'VALID')
                ->whereNull('processed_at')
                ->orderBy('source_row')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $payload = $row->normalized_payload ?? [];
                $sap = (string) ($payload['sap'] ?? '');

                if ($sap === '') {
                    $row->forceFill([
                        'validation_status' => 'INVALID',
                        'validation_errors' => ['SAP is required before processing.'],
                    ])->save();
                    $result['skipped']++;

                    continue;
                }

                $employee = Employee::query()->where('sap', $sap)->lockForUpdate()->first();
                $operation = $employee === null ? 'CREATE' : 'UPDATE';
                $attributes = $this->employeeAttributes($payload);

                if ($operation === 'CREATE') {
                    $employee = Employee::query()->create($attributes);
                } else {
                    $employee->fill($attributes)->save();
                }

                $row->forceFill([
                    'processing_result' => [
                        'operation' => $operation,
                        'employee_id' => $employee->id,
                        'sap' => $employee->sap,
                    ],
                    'processed_by' => $actor->id,
                    'processed_at' => now(),
                ])->save();
                $result['processed']++;
            }

            $hasUnprocessedRows = $batch->rows()
                ->where('validation_status', '!=', 'INVALID')
                ->whereNull('processed_at')
                ->exists();
            $batch->update(['status' => $hasUnprocessedRows ? 'REVIEW_REQUIRED' : 'PROCESSED']);
        });

        $this->refreshBatchCounters($batch);

        return $result;
    }

    /** @param array<int, array{source_row: int, values: array<string, string>}> $sourceRows */
    private function stageRows(EmployeeImportBatch $batch, array $headers, array $sourceRows): void
    {
        $sapCounts = [];
        foreach ($sourceRows as $sourceRow) {
            $sap = $this->normalizeSap($this->sourceValue($sourceRow['values'], 'SAP'));
            if ($sap !== '') {
                $sapCounts[$sap] = ($sapCounts[$sap] ?? 0) + 1;
            }
        }

        foreach ($sourceRows as $sourceRow) {
            $sourcePayload = [];
            foreach ($headers as $header) {
                $sourcePayload[$header] = $sourceRow['values'][$header] ?? '';
            }

            $sap = $this->normalizeSap($this->sourceValue($sourceRow['values'], 'SAP'));
            $validationErrors = [];
            $mappingErrors = [];
            $normalized = [];

            foreach (self::CANONICAL_FIELDS as $sourceField => $targetField) {
                $value = trim((string) $this->sourceValue($sourceRow['values'], $sourceField));
                $normalized[$targetField] = $value === '' ? null : $value;
            }
            $normalized['sap'] = $sap === '' ? null : $sap;

            if ($sap === '') {
                $validationErrors[] = 'SAP is required.';
            } elseif (($sapCounts[$sap] ?? 0) > 1) {
                $validationErrors[] = 'SAP appears more than once in this source file.';
            }

            foreach (self::STRING_LIMITS as $field => $maxLength) {
                $value = $normalized[$field] ?? null;
                if ($value !== null && mb_strlen((string) $value) > $maxLength) {
                    $validationErrors[] = "{$field} exceeds {$maxLength} characters.";
                }
            }

            if ($normalized['email'] !== null && filter_var($normalized['email'], FILTER_VALIDATE_EMAIL) === false) {
                $validationErrors[] = 'E-mail is not a valid email address.';
            }

            foreach (['birth_date', 'hiring', 'masa_kontrak', 'organilk'] as $dateField) {
                if ($normalized[$dateField] === null) {
                    continue;
                }

                try {
                    $normalized[$dateField] = $this->parseDate($normalized[$dateField]);
                } catch (InvalidArgumentException) {
                    $validationErrors[] = "{$dateField} is not a valid date.";
                }
            }

            if ($normalized['usia'] !== null) {
                $age = filter_var($normalized['usia'], FILTER_VALIDATE_INT);
                if ($age === false || $age < 0) {
                    $validationErrors[] = 'usia must be a non-negative integer.';
                } else {
                    $normalized['usia'] = $age;
                }
            }

            $employeeExists = $sap !== '' && Employee::query()->where('sap', $sap)->exists();
            $operation = $employeeExists ? 'UPDATE' : 'CREATE';
            $normalized['_operation'] = $operation;

            if ($validationErrors !== []) {
                $validationStatus = 'INVALID';
                $mappingStatus = $sap === '' ? 'UNMATCHED' : 'AUTO_CANDIDATE';
            } else {
                $validationStatus = 'VALID';
                $mappingStatus = 'AUTO_CANDIDATE';
            }

            EmployeeImportRow::query()->create([
                'employee_import_batch_id' => $batch->id,
                'source_row' => $sourceRow['source_row'],
                'source_reference' => 'row '.$sourceRow['source_row'],
                'source_payload' => $sourcePayload,
                'normalized_payload' => $normalized,
                'validation_status' => $validationStatus,
                'mapping_status' => $mappingStatus,
                'validation_errors' => $validationErrors === [] ? null : $validationErrors,
                'mapping_errors' => $mappingErrors === [] ? null : $mappingErrors,
            ]);
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function employeeAttributes(array $payload): array
    {
        $attributes = [];
        foreach (array_values(self::CANONICAL_FIELDS) as $field) {
            if ($field !== 'sap' && array_key_exists($field, $payload)) {
                $attributes[$field] = $payload[$field];
            }
        }
        $attributes['sap'] = $payload['sap'];

        return $attributes;
    }

    private function refreshBatchCounters(EmployeeImportBatch $batch): void
    {
        $batch->forceFill([
            'total_rows' => $batch->rows()->count(),
            'valid_rows' => $batch->rows()->whereIn('validation_status', ['VALID', 'REVIEW_REQUIRED'])->count(),
            'invalid_rows' => $batch->rows()->where('validation_status', 'INVALID')->count(),
            'approved_rows' => $batch->rows()->where('mapping_status', 'APPROVED')->count(),
            'processed_rows' => $batch->rows()->whereNotNull('processed_at')->count(),
        ])->save();
    }

    private function storeFileError(EmployeeImportBatch $batch, array $messages, array $sourcePayload = []): void
    {
        EmployeeImportRow::query()->create([
            'employee_import_batch_id' => $batch->id,
            'source_row' => 0,
            'source_reference' => 'file',
            'source_payload' => $sourcePayload,
            'validation_status' => 'INVALID',
            'mapping_status' => 'UNMATCHED',
            'validation_errors' => $messages,
        ]);
        $batch->update(['status' => 'INVALID']);
        $this->refreshBatchCounters($batch);
    }

    /** @param array<int, string|null> $headers @return array<int, string> */
    private function validateHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $header = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header));
            if ($header === 'Position ID') {
                $header = 'Position id';
            }
            $normalized[] = $header;
        }

        $duplicates = array_keys(array_filter(array_count_values($normalized), fn (int $count): bool => $count > 1));
        $missing = array_values(array_diff(self::HEADERS, $normalized));
        $unexpected = array_values(array_diff($normalized, self::HEADERS));
        $errors = [];
        if ($duplicates !== []) {
            $errors[] = 'Duplicate headers: '.implode(', ', $duplicates).'.';
        }
        if ($missing !== []) {
            $errors[] = 'Missing headers: '.implode(', ', $missing).'.';
        }
        if ($unexpected !== []) {
            $errors[] = 'Unexpected headers: '.implode(', ', $unexpected).'.';
        }

        return $errors;
    }

    /** @return array{0: array<int, string>, 1: array<int, array{source_row: int, values: array<string, string>}>} */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('CSV file could not be opened.');
        }

        $firstLine = fgets($handle);
        fclose($handle);
        if ($firstLine === false) {
            return [[], []];
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('CSV file could not be reopened.');
        }

        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn ($header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)), $headers);
        $rows = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if ($values === [null] || count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = ['source_row' => $rowNumber, 'values' => $this->combineValues($headers, $values)];
        }
        fclose($handle);

        return [$headers, $rows];
    }

    /** @return array{0: array<int, string>, 1: array<int, array{source_row: int, values: array<string, string>}>} */
    private function readXlsx(string $path): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Excel file could not be opened.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($archive);
            $sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('The first worksheet was not found.');
            }
        } finally {
            $archive->close();
        }

        $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);
        if ($sheet === false) {
            throw new RuntimeException('Excel worksheet XML is invalid.');
        }

        $rows = [];
        $headers = [];

        foreach ($sheet->sheetData->row as $sheetRow) {
            $rowValues = [];
            foreach ($sheetRow->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } elseif (isset($cell->f)) {
                    throw new RuntimeException('Formula cells are not accepted in SAP source rows.');
                }
                $rowValues[$column] = $value;
            }

            if ($rowValues === []) {
                continue;
            }

            ksort($rowValues);
            if ($headers === []) {
                $headers = array_map(fn ($value): string => trim((string) $value), array_values($rowValues));

                continue;
            }

            if (count(array_filter($rowValues, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $values = [];
            foreach ($headers as $index => $header) {
                $values[$header] = (string) ($rowValues[$index] ?? '');
            }
            $rows[] = ['source_row' => (int) ($sheetRow['r'] ?: count($rows) + 2), 'values' => $values];
        }

        return [$headers, $rows];
    }

    /** @return array<int, string> */
    private function readSharedStrings(ZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        if ($document === false) {
            throw new RuntimeException('Excel shared strings XML is invalid.');
        }

        $strings = [];
        foreach ($document->si as $item) {
            $text = (string) ($item->t ?? '');
            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /** @param array<int, string> $headers @param array<int, string|null> $values @return array<string, string> */
    private function combineValues(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = trim((string) ($values[$index] ?? ''));
        }

        return $row;
    }

    /** @param array<string, string> $values */
    private function sourceValue(array $values, string $header): ?string
    {
        if ($header === 'Position id') {
            return $values['Position id'] ?? $values['Position ID'] ?? null;
        }

        return $values[$header] ?? null;
    }

    private function normalizeSap(?string $sap): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $sap)) ?? '';
    }

    private function parseDate(string $value): string
    {
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['!Y-m-d', '!d-m-Y', '!j-n-Y', '!d.m.Y', '!j.n.Y', '!d/m/Y', '!j/n/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== false && $date->format(substr($format, 1)) === $value) {
                return $date->toDateString();
            }
        }

        throw new InvalidArgumentException('Invalid date.');
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/[A-Z]+/i', $cellReference, $matches);
        $index = 0;
        foreach (str_split(strtoupper($matches[0] ?? 'A')) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }
}
