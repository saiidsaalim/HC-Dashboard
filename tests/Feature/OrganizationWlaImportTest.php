<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Organization\OrganizationWlaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\WlaOrganizationFixture;
use Tests\TestCase;

class OrganizationWlaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_tabel_posisi_sheet_and_validates_headers(): void
    {
        $fixturePath = sys_get_temp_dir().'/wla-valid.xlsx';
        WlaOrganizationFixture::write($fixturePath, [
            ['department' => 'Department A', 'unit' => 'Unit A', 'position' => 'Manager'],
            ['department' => 'Department B', 'unit' => 'Unit B', 'position' => 'Staff'],
        ]);

        $service = app(OrganizationWlaImportService::class);
        $report = $service->import($fixturePath, true);

        $this->assertSame(2, $report['source_rows']);
        $this->assertSame(2, $report['unique_departments']);
        $this->assertSame(2, $report['unique_units']);
        $this->assertSame(2, $report['unique_positions']);
        $this->assertSame(0, $report['duplicate_rows_removed']);
    }

    public function test_it_imports_department_unit_and_position_and_consolidates_duplicates(): void
    {
        $fixturePath = sys_get_temp_dir().'/wla-import.xlsx';
        WlaOrganizationFixture::write($fixturePath, [
            ['department' => 'Department A', 'unit' => 'Unit A', 'position' => 'Manager'],
            ['department' => 'Department A', 'unit' => 'Unit A', 'position' => 'Manager'],
            ['department' => 'Department A', 'unit' => 'Unit B', 'position' => 'Staff'],
            ['department' => 'Department B', 'unit' => 'Unit C', 'position' => 'Analyst'],
        ]);

        $service = app(OrganizationWlaImportService::class);
        $report = $service->import($fixturePath, false);

        $this->assertSame(4, $report['source_rows']);
        $this->assertSame(2, $report['unique_departments']);
        $this->assertSame(3, $report['unique_units']);
        $this->assertSame(3, $report['unique_positions']);
        $this->assertSame(1, $report['duplicate_rows_removed']);
        $this->assertDatabaseHas('departments', ['name' => 'Department A']);
        $this->assertDatabaseHas('units', ['name' => 'Unit A', 'department_id' => Department::query()->where('name', 'Department A')->value('id')]);
        $this->assertDatabaseHas('positions', ['name' => 'Manager', 'unit_id' => Unit::query()->where('name', 'Unit A')->value('id')]);
    }

    public function test_it_is_idempotent_and_does_not_touch_existing_employee_records(): void
    {
        $fixturePath = sys_get_temp_dir().'/wla-idempotent.xlsx';
        WlaOrganizationFixture::write($fixturePath, [
            ['department' => 'Department A', 'unit' => 'Unit A', 'position' => 'Manager'],
            ['department' => 'Department B', 'unit' => 'Unit B', 'position' => 'Staff'],
        ]);

        $employee = Employee::create([
            'sap' => 'SAP100',
            'name' => 'Pegawai Lama',
            'department' => 'Legacy Department',
            'organizational_unit' => 'Legacy Unit',
            'position' => 'Legacy Position',
            'department_id' => null,
            'unit_id' => null,
            'position_id' => null,
        ]);

        $service = app(OrganizationWlaImportService::class);
        $first = $service->import($fixturePath, false);
        $second = $service->import($fixturePath, false);

        $this->assertSame(0, $second['duplicate_rows_removed'] ?? 0);
        $this->assertDatabaseCount('departments', 2);
        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('positions', 2);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => null,
            'unit_id' => null,
            'position_id' => null,
            'department' => 'Legacy Department',
            'organizational_unit' => 'Legacy Unit',
            'position' => 'Legacy Position',
        ]);
        $this->assertSame(2, $first['unique_departments']);
        $this->assertSame(2, $second['unique_departments']);
    }

    public function test_it_does_not_create_new_positions_when_names_only_differ_by_whitespace(): void
    {
        $fixturePath = sys_get_temp_dir().'/wla-whitespace-idempotent.xlsx';
        WlaOrganizationFixture::write($fixturePath, [
            ['department' => 'Department A', 'unit' => 'Unit A', 'position' => 'Tender Silo 2/3'],
            ['department' => 'Department A', 'unit' => 'Unit A', 'position' => 'Tender Silo  2/3'],
        ]);

        $service = app(OrganizationWlaImportService::class);
        $first = $service->import($fixturePath, false);
        $second = $service->import($fixturePath, false);

        $this->assertSame(1, $first['unique_positions']);
        $this->assertSame(1, $second['unique_positions']);
        $this->assertSame(0, $second['departments']['new']);
        $this->assertSame(0, $second['units']['new']);
        $this->assertSame(0, $second['positions']['new']);
        $this->assertSame(1, Position::query()->count());
    }

    public function test_it_refuses_missing_file_and_keeps_existing_master_intact(): void
    {
        $service = app(OrganizationWlaImportService::class);
        $department = Department::create(['code' => 'ORG-D-KEEP', 'name' => 'Keep Department', 'active' => true]);
        $unit = $department->units()->create(['code' => 'ORG-U-KEEP', 'name' => 'Keep Unit', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'ORG-P-KEEP', 'name' => 'Keep Position', 'active' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File Excel WLA tidak ditemukan');
        $service->import(sys_get_temp_dir().'/not-found.xlsx', true);

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Keep Department']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Keep Unit']);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'name' => 'Keep Position']);
    }
}
