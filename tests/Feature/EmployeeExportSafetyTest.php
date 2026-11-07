<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EmployeeExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserting_a_lower_sap_during_export_does_not_repeat_existing_employees(): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        Employee::query()->insert(array_map(fn (int $number): array => [
            'sap' => sprintf('SAFE-%04d', $number),
        ], range(1, 501)));
        $insertedBetweenChunks = false;
        DB::listen(function (QueryExecuted $query) use (&$insertedBetweenChunks): void {
            if (! $insertedBetweenChunks
                && str_contains($query->sql, '"employees"')
                && str_starts_with(strtolower($query->sql), 'select')) {
                $insertedBetweenChunks = true;
                Employee::query()->create(['sap' => 'SAFE-0000']);
            }
        });

        $response = $this->actingAs($actor)->get(route('data-pegawai.export'));

        $response->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $spreadsheet = IOFactory::load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $exportedSaps = array_column($sheet->rangeToArray('A2:A'.$sheet->getHighestDataRow()), 0);
            $this->assertTrue($insertedBetweenChunks);
            $this->assertCount(501, $exportedSaps);
            $this->assertCount(501, array_unique($exportedSaps));
            $this->assertSame('SAFE-0001', $exportedSaps[0]);
            $this->assertSame('SAFE-0501', $exportedSaps[500]);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_excel_export_preserves_all_rows_in_sap_order_across_chunks_and_keeps_formulas_as_text(): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $formula = '=HYPERLINK("http://localhost/", "Example")';
        Employee::query()->insert(array_map(fn (int $number): array => [
            'sap' => sprintf('SAFE-%04d', $number),
            'personal_number' => $formula,
        ], range(501, 1)));

        $response = $this->actingAs($actor)->get(route('data-pegawai.export'));

        $response->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $spreadsheet = IOFactory::load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame(502, $sheet->getHighestDataRow());
            $this->assertSame('SAFE-0001', $sheet->getCell('A2')->getValue());
            $this->assertSame('SAFE-0500', $sheet->getCell('A501')->getValue());
            $this->assertSame('SAFE-0501', $sheet->getCell('A502')->getValue());
            $this->assertSame($formula, $sheet->getCell('D502')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D502')->getDataType());
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($path);
        }
    }
}
