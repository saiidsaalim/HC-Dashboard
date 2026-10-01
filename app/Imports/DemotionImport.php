<?php

namespace App\Imports;

use App\Models\EmployeeDemotion;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class DemotionImport
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = [
        'sap', 'nama', 'departemen_lama', 'jabatan_lama', 'departemen_baru', 'jabatan_baru',
        'tmt', 'pg', 'band_lama', 'jg_lama', 'band_baru', 'jg_baru',
    ];

    public function import(UploadedFile $file): int
    {
        $rows = $this->readRows($file);
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File demosi tidak memiliki data.']);
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

                $data['tmt'] = $this->parseDate($data['tmt'], $rowNumber);
                EmployeeDemotion::create($data);
                $created++;
            }
        });

        return $created;
    }

    /** @return array<int, array<string, string>> */
    private function readRows(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx'
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
            $cells = [];
            foreach ($sheetRow->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $value = (string) ($cell->v ?? '');
                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }
                $cells[$column] = $value;
            }
            if ($cells === []) {
                continue;
            }

            $values = [];
            for ($column = 0; $column <= max(array_keys($cells)); $column++) {
                $values[$column] = $cells[$column] ?? '';
            }
            if ($headers === []) {
                $headers = $values;
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
        if ($document === false) {
            return [];
        }
        $strings = [];
        foreach ($document->si as $item) {
            $text = isset($item->t) ? (string) $item->t : '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
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
            return is_numeric($value)
                ? Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString()
                : Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => "TMT pada baris {$rowNumber} tidak valid.",
            ]);
        }
    }
}
