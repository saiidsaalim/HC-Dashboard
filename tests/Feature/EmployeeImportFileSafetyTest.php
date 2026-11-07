<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmployeeImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class EmployeeImportFileSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'SAP', 'ID Number', 'AGKN', 'Position id', 'Personal Number', 'Position',
        'Employee Subgroup', 'Cost Ctr', 'TXT_DIR', 'TXT_DEPT', 'TXT_BIRO', 'TXT_SECT',
        'Birth date', 'Gender Key', 'Personnel Area', 'abrevation position',
        'abrevation organization', 'Organizational Unit', 'Cost Center', 'Masa Kontrak',
        'E-mail', 'Religious', 'Usia', 'Tempat Lahir', 'Pendidikan', 'Hiring',
        'Organilk', 'Alamat',
    ];

    public function test_worksheet_without_sheet_data_becomes_an_invalid_batch(): void
    {
        $this->assertRejectedUpload($this->xlsxFile('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'),
            'Excel worksheet must contain sheetData.');
    }

    public function test_malformed_xml_becomes_an_invalid_batch_without_parser_warnings(): void
    {
        $this->assertRejectedUpload($this->xlsxFile('<worksheet><sheetData>'),
            'Excel worksheet XML is invalid.');
    }

    public function test_xml_document_type_declarations_are_rejected(): void
    {
        $this->assertRejectedUpload($this->xlsxFile('<!DOCTYPE worksheet [<!ENTITY example "test">]><worksheet><sheetData/></worksheet>'),
            'Excel XML document type declarations are not accepted.');
    }

    #[DataProvider('utf16Encodings')]
    public function test_utf16_xml_document_type_declarations_are_rejected(string $encoding): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE worksheet [<!ENTITY harmless "test">]><worksheet><sheetData/></worksheet>';
        $byteOrderMark = $encoding === 'UTF-16LE' ? "\xFF\xFE" : "\xFE\xFF";
        $encodedXml = $byteOrderMark.mb_convert_encoding($xml, $encoding, 'UTF-8');

        $this->assertRejectedUpload($this->xlsxFile($encodedXml),
            'Excel XML document type declarations are not accepted.');
    }

    /** @return array<string, array{string}> */
    public static function utf16Encodings(): array
    {
        return [
            'little endian' => ['UTF-16LE'],
            'big endian' => ['UTF-16BE'],
        ];
    }

    public function test_compressed_xml_is_limited_by_its_expanded_size(): void
    {
        config(['workforce.employee_import.max_xml_bytes' => 1024]);

        $this->assertRejectedUpload($this->xlsxFile('<worksheet><sheetData/>'.str_repeat(' ', 2048).'</worksheet>'),
            'Excel XML exceeds the allowed expanded size.');
    }

    public function test_archive_has_an_expanded_size_limit_including_unused_entries(): void
    {
        config(['workforce.employee_import.max_archive_bytes' => 1024]);

        $this->assertRejectedUpload($this->xlsxFile('<worksheet><sheetData/></worksheet>'),
            'Excel archive exceeds the allowed expanded size.');
    }

    public function test_archive_entry_limit_rejects_the_upload(): void
    {
        config(['workforce.employee_import.max_archive_entries' => 1]);

        $this->assertRejectedUpload($this->xlsxFile('<worksheet><sheetData/></worksheet>'),
            'Excel archive contains too many entries.');
    }

    public function test_csv_row_limit_rejects_the_entire_batch_before_staging_employees(): void
    {
        config(['workforce.employee_import.max_rows' => 2]);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::HEADERS);
        foreach (range(1, 3) as $row) {
            fputcsv($stream, array_map(fn (string $header): string => $header === 'SAP' ? 'SAFE-'.$row : '', self::HEADERS));
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $this->assertRejectedUpload(UploadedFile::fake()->createWithContent('employees.csv', $contents),
            'Source file exceeds the maximum of 2 employee rows.');
    }

    public function test_xlsx_row_limit_rejects_the_entire_batch_before_staging_employees(): void
    {
        config(['workforce.employee_import.max_rows' => 2]);

        $this->assertRejectedUpload($this->xlsxFile(null, 3),
            'Source file exceeds the maximum of 2 employee rows.');
    }

    public function test_xlsx_at_the_row_limit_is_staged_for_review_without_saving_employees(): void
    {
        Storage::fake('local');
        config(['workforce.employee_import.max_rows' => 2]);
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $this->xlsxFile(null, 2)])
            ->assertRedirect(route('data-pegawai'));

        $batch = EmployeeImportBatch::query()->sole();
        $this->assertSame('REVIEW_REQUIRED', $batch->status);
        $this->assertSame(2, $batch->valid_rows);
        Storage::disk('local')->assertExists($batch->source_file);
        $this->assertDatabaseCount('employees', 0);
    }

    private function assertRejectedUpload(UploadedFile $file, string $error): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'));

        $batch = EmployeeImportBatch::query()->sole();
        $this->assertSame('INVALID', $batch->status);
        $this->assertSame(1, $batch->invalid_rows);
        $this->assertSame([$error], $batch->rows()->sole()->validation_errors);
        Storage::disk('local')->assertExists($batch->source_file);
        $this->assertDatabaseCount('employees', 0);
    }

    private function xlsxFile(?string $worksheetXml, int $employeeRows = 0): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');
        for ($row = 1; $row <= $employeeRows; $row++) {
            $sheet->setCellValue('A'.($row + 1), 'SAFE-'.$row);
        }
        $path = tempnam(sys_get_temp_dir(), 'employee-file-safety-');
        $this->assertNotFalse($path);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        if ($worksheetXml !== null) {
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path) === true);
            $this->assertTrue($archive->addFromString('xl/worksheets/sheet1.xml', $worksheetXml));
            $archive->close();
        }
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('employees.xlsx', $contents);
    }
}
