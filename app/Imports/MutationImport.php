<?php

namespace App\Imports;

use App\Models\EmployeeMutation;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class MutationImport
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = [
        'sap',
        'nama',
        'departemen_lama',
        'jabatan_lama',
        'departemen_baru',
        'jabatan_baru',
        'tmt',
        'pg',
        'band_lama',
        'jg_lama',
        'band_baru',
        'jg_baru',
    ];

    public function import(UploadedFile $file): int
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File mutasi tidak memiliki data.']);
        }

        $created = 0;

        DB::transaction(function () use ($rows, &$created): void {
            foreach ($rows as $rowNumber => $row) {
                $data = [];

                foreach (self::REQUIRED_HEADERS as $header) {
                    $value = trim((string) ($row[$header] ?? ''));

                    if ($value === '') {
                        throw ValidationException::withMessages([
                            'file' => "Kolom {$header} pada baris {$rowNumber} wajib diisi.",
                        ]);
                    }

                    $data[$header] = $value;
                }

                $data['tmt'] = $this->parseDate(
                    $data['tmt'],
                    $rowNumber,
                );

                EmployeeMutation::create($data);
                $created++;
            }
        });

        return $created;
    }

    /** @return array<int, array<string, string>> */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $extension === 'xlsx'
            ? $this->readXlsx($file->getRealPath())
            : $this->readCsv($file->getRealPath());
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return [];
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        $this->validateHeaders($headers ?: []);
        $rows = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($values === [null] || count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[$rowNumber] = $this->combineRow($headers, $values);
        }

        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<string, string>> */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Sheet pertama pada file Excel tidak ditemukan.');
        }

        $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);

        if ($sheet === false) {
            throw new RuntimeException('Struktur file Excel tidak valid.');
        }

        $headers = [];
        $rows = [];
        $rowNumber = 0;

        foreach ($sheet->sheetData->row as $sheetRow) {
            $rowNumber++;
            $values = [];

            foreach ($sheetRow->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $value = (string) ($cell->v ?? '');

                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $values[$column] = $value;
            }

            if ($rowNumber === 1) {
                $headers = array_values($values);
                $this->validateHeaders($headers);

                continue;
            }

            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) > 0) {
                $rows[$rowNumber] = $this->combineRow($headers, $values);
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        $strings = [];

        if ($document === false) {
            return $strings;
        }

        foreach ($document->si as $item) {
            $strings[] = isset($item->t)
                ? (string) $item->t
                : implode('', array_map('strval', iterator_to_array($item->r->t ?? [])));
        }

        return $strings;
    }

    /** @param array<int, string|null> $headers */
    private function validateHeaders(array $headers): void
    {
        $normalized = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);

        if (array_diff(self::REQUIRED_HEADERS, $normalized) !== []) {
            throw ValidationException::withMessages([
                'file' => 'Header wajib: SAP, Nama, Departemen Lama, Jabatan Lama, Departemen Baru, Jabatan Baru, TMT, PG, Band Lama, JG Lama, Band Baru, dan JG Baru.',
            ]);
        }
    }

    /** @param array<int, string|null> $headers @param array<int, string|null> $values */
    private function combineRow(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$this->normalizeHeader((string) $header)] = (string) ($values[$index] ?? '');
        }

        return $row;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header)) ?? $header;

        return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower($header)), '_');
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/[A-Z]+/i', $cellReference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function parseDate(string $value, int $rowNumber): string
    {
        try {
            if (is_numeric($value)) {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => "TMT pada baris {$rowNumber} tidak valid.",
            ]);
        }
    }
}
