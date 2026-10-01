<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Services\Employees\EmployeeOrganizationMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeOrganizationMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_fk_columns_are_nullable_and_legacy_columns_remain(): void
    {
        $columns = collect(Schema::getColumns('employees'))->keyBy('name');

        foreach (['department_id', 'unit_id', 'position_id'] as $foreignKey) {
            $this->assertTrue($columns->has($foreignKey));
            $this->assertTrue($columns[$foreignKey]['nullable']);
        }

        foreach (['department', 'organizational_unit', 'position', 'bureau', 'section'] as $legacyField) {
            $this->assertTrue($columns->has($legacyField));
        }
    }

    public function test_mapper_supports_exact_and_normalized_hierarchy_matches(): void
    {
        [$department, $unit, $position] = $this->organization();
        $mapper = app(EmployeeOrganizationMapper::class);

        $exact = $mapper->resolve('Sales', 'Field Team', 'Analyst');
        $this->assertSame([
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ], $exact['ids']);
        $this->assertSame(['department' => 'exact', 'unit' => 'exact', 'position' => 'exact'], $exact['statuses']);
        $this->assertSame([], $exact['conflicts']);

        $normalized = $mapper->resolve(' sales ', 'FIELD   TEAM', ' analyst ');
        $this->assertSame($exact['ids'], $normalized['ids']);
        $this->assertSame(['department' => 'normalized', 'unit' => 'normalized', 'position' => 'normalized'], $normalized['statuses']);
        $this->assertSame([], $normalized['conflicts']);
    }

    public function test_mapper_reports_unmatched_and_parent_context_conflicts(): void
    {
        [$department, $unit] = $this->organization();
        $otherDepartment = Department::create(['code' => 'DPT-MAP-B', 'name' => 'Engineering', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'UNT-MAP-B', 'name' => 'Platform Team', 'active' => true]);
        $otherUnit->positions()->create(['code' => 'POS-MAP-B', 'name' => 'Manager', 'active' => true]);
        $mapper = app(EmployeeOrganizationMapper::class);

        $unmatched = $mapper->resolve('Unknown Department', 'Unknown Unit', 'Unknown Position');
        $this->assertSame(['department_id' => null, 'unit_id' => null, 'position_id' => null], $unmatched['ids']);
        $this->assertSame(['department' => 'unmapped', 'unit' => 'unmapped', 'position' => 'unmapped'], $unmatched['statuses']);
        $this->assertSame([], $unmatched['conflicts']);

        $conflict = $mapper->resolve($department->name, $unit->name, 'Manager');
        $this->assertContains('position', $conflict['conflicts']);
        $this->assertSame('conflict', $conflict['statuses']['position']);
        $this->assertNull($conflict['ids']['position_id']);
    }

    public function test_mapping_command_dry_run_reports_without_writing_and_apply_updates_in_chunks(): void
    {
        [$department, $unit, $position] = $this->organization();
        $otherDepartment = Department::create(['code' => 'DPT-MAP-C', 'name' => 'Engineering', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'UNT-MAP-C', 'name' => 'Platform Team', 'active' => true]);
        $otherUnit->positions()->create(['code' => 'POS-MAP-C', 'name' => 'Manager', 'active' => true]);

        $mappable = Employee::create($this->employeeAttributes('SAPMAP100', 'Sales', 'Field Team', 'Analyst'));
        $unmatched = Employee::create($this->employeeAttributes('SAPMAP101', 'Unknown Department', null, null));
        $conflicting = Employee::create($this->employeeAttributes('SAPMAP102', 'Sales', 'Field Team', 'Manager'));
        Employee::create($this->employeeAttributes('SAPMAP103', null, null, null));

        $this->artisan('employees:map-organization --dry-run --chunk=1')
            ->expectsOutput('Total Employees: 4')
            ->expectsOutput('Mapped Department: 2')
            ->expectsOutput('Unmapped Department: 1')
            ->expectsOutput('Empty Department text: 1')
            ->expectsOutput('Conflicts: 1')
            ->expectsOutput('Mode: dry-run')
            ->expectsOutput('Employee rows updated: 0')
            ->assertSuccessful();

        $this->assertNull($mappable->fresh()->department_id);
        $this->assertNull($mappable->fresh()->unit_id);
        $this->assertNull($mappable->fresh()->position_id);
        $this->assertSame('Sales', $mappable->fresh()->txt_dept);
        $this->assertNull($unmatched->fresh()->department_id);
        $this->assertNull($conflicting->fresh()->position_id);

        $this->artisan('employees:map-organization --apply --chunk=1')
            ->expectsOutput('Mode: apply')
            ->expectsOutput('Employee rows updated: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', [
            'id' => $mappable->id,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'txt_dept' => 'Sales',
            'organizational_unit' => 'Field Team',
            'position' => 'Analyst',
        ]);
        $this->assertNull($unmatched->fresh()->department_id);
        $this->assertNull($conflicting->fresh()->department_id);
    }

    public function test_diagnostic_command_reports_matches_and_unmatched_without_writing(): void
    {
        $this->organization();
        Employee::create($this->employeeAttributes('SAPMAP200', 'Sales', 'Field Team', 'Analyst'));
        Employee::create($this->employeeAttributes('SAPMAP201', '  sales  ', '  FIELD   TEAM ', ' Analyst '));
        Employee::create($this->employeeAttributes('SAPMAP202', 'Unknown Department', 'Unknown Unit', 'Unknown Position'));

        $this->artisan('employees:diagnose-organization')
            ->expectsOutput('EMPLOYEE ORGANIZATION MAPPING DIAGNOSTIC')
            ->expectsOutput('Employees: 3')
            ->expectsOutputToContain('Departments:')
            ->expectsOutputToContain('Exact matches:')
            ->expectsOutputToContain('Normalized matches:')
            ->expectsOutputToContain('Unmatched:')
            ->expectsOutput('UNMATCHED EMPLOYEES')
            ->assertSuccessful();
    }

    public function test_export_template_creates_csv_for_manual_review(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPMAP300', 'Sales', 'Field Team', 'Analyst'));

        $path = sys_get_temp_dir().'/employee-mapping-template.csv';
        $this->artisan('employees --export-template')
            ->expectsOutputToContain('Template mapping telah dibuat')
            ->assertSuccessful();

        $this->assertFileExists(storage_path('app/imports/employee-organization-mapping-template.csv'));

        $rows = array_map('str_getcsv', file(storage_path('app/imports/employee-organization-mapping-template.csv')));
        $this->assertSame([
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
        ], $rows[0]);
        $this->assertSame('AUTO_CANDIDATE', $rows[1][11]);
        $this->assertSame((string) $employee->id, $rows[1][0]);
    }

    public function test_validate_mapping_accepts_valid_rows_and_rejects_invalid_data(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPMAP301', 'Sales', 'Field Team', 'Analyst'));

        $file = sys_get_temp_dir().'/employee-mapping-valid.csv';
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'department_id', 'department_name', 'unit_id', 'unit_name', 'position_id', 'position_name', 'mapping_status', 'mapping_note']);
        fputcsv($handle, [$employee->id, $employee->name, 'Sales', 'Field Team', 'Analyst', $department->id, $department->name, $unit->id, $unit->name, $position->id, $position->name, 'APPROVED', 'Manual review approved']);
        fclose($handle);

        $this->artisan('employees', ['--validate' => $file])
            ->expectsOutput('Valid: 1')
            ->expectsOutput('Invalid: 0')
            ->assertSuccessful();

        $badFile = sys_get_temp_dir().'/employee-mapping-invalid.csv';
        $handle = fopen($badFile, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'department_id', 'department_name', 'unit_id', 'unit_name', 'position_id', 'position_name', 'mapping_status', 'mapping_note']);
        fputcsv($handle, [999999, 'Ghost', 'Sales', 'Field Team', 'Analyst', $department->id, $department->name, $unit->id, $unit->name, $position->id, $position->name, 'APPROVED', 'Invalid employee']);
        fclose($handle);

        $this->artisan('employees', ['--validate' => $badFile])
            ->assertExitCode(1);
    }

    public function test_preview_command_is_read_only_and_shows_approved_changes(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPMAP302', 'Sales', 'Field Team', 'Analyst'));

        $file = sys_get_temp_dir().'/employee-mapping-preview.csv';
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'department_id', 'department_name', 'unit_id', 'unit_name', 'position_id', 'position_name', 'mapping_status', 'mapping_note']);
        fputcsv($handle, [$employee->id, $employee->name, 'Sales', 'Field Team', 'Analyst', $department->id, $department->name, $unit->id, $unit->name, $position->id, $position->name, 'APPROVED', 'Approved for preview']);
        fclose($handle);

        $this->artisan('employees', ['--preview' => $file])
            ->expectsOutput('Employees to update: 1')
            ->expectsOutput('Department changes: 1')
            ->expectsOutput('Unit changes: 1')
            ->expectsOutput('Position changes: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'department_id' => null, 'unit_id' => null, 'position_id' => null]);
    }

    public function test_preview_counts_approved_updates_when_employee_fks_are_null(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPMAP304', null, null, null));

        $file = sys_get_temp_dir().'/employee-mapping-null-fks-preview.csv';
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'department_id', 'department_name', 'unit_id', 'unit_name', 'position_id', 'position_name', 'mapping_status', 'mapping_note']);
        fputcsv($handle, [$employee->id, $employee->name, 'Sales', 'Field Team', 'Analyst', $department->id, $department->name, $unit->id, $unit->name, $position->id, $position->name, 'APPROVED', 'Approved with null FKs']);
        fclose($handle);

        $this->artisan('employees', ['--preview' => $file])
            ->expectsOutput('Employees to update: 1')
            ->expectsOutput('Department changes: 1')
            ->expectsOutput('Unit changes: 1')
            ->expectsOutput('Position changes: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'department_id' => null, 'unit_id' => null, 'position_id' => null]);
    }

    public function test_preview_ignores_auto_candidate_status(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPMAP305', null, null, null));

        $file = sys_get_temp_dir().'/employee-mapping-auto-candidate-preview.csv';
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'department_id', 'department_name', 'unit_id', 'unit_name', 'position_id', 'position_name', 'mapping_status', 'mapping_note']);
        fputcsv($handle, [$employee->id, $employee->name, 'Sales', 'Field Team', 'Analyst', $department->id, $department->name, $unit->id, $unit->name, $position->id, $position->name, 'AUTO_CANDIDATE', 'Auto-candidate requires manual approval']);
        fclose($handle);

        $this->artisan('employees', ['--preview' => $file])
            ->expectsOutput('Employees to update: 0')
            ->expectsOutput('Department changes: 0')
            ->expectsOutput('Unit changes: 0')
            ->expectsOutput('Position changes: 0')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'department_id' => null, 'unit_id' => null, 'position_id' => null]);
    }

    public function test_apply_requires_approved_status_and_updates_in_transaction(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPMAP303', 'Sales', 'Field Team', 'Analyst'));

        $file = sys_get_temp_dir().'/employee-mapping-apply.csv';
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['employee_id', 'employee_name', 'legacy_department', 'legacy_unit', 'legacy_position', 'department_id', 'department_name', 'unit_id', 'unit_name', 'position_id', 'position_name', 'mapping_status', 'mapping_note']);
        fputcsv($handle, [$employee->id, $employee->name, 'Sales', 'Field Team', 'Analyst', $department->id, $department->name, $unit->id, $unit->name, $position->id, $position->name, 'APPROVED', 'Approved mapping']);
        fclose($handle);

        $this->artisan('employees', ['--apply' => $file])
            ->expectsOutput('Total approved: 1')
            ->expectsOutput('Successfully updated: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'txt_dept' => 'Sales',
            'organizational_unit' => 'Field Team',
            'position' => 'Analyst',
        ]);
    }

    /** @return array<int, mixed> */
    private function organization(): array
    {
        $department = Department::create(['code' => 'DPT-MAP-A', 'name' => 'Sales', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-MAP-A', 'name' => 'Field Team', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-MAP-A', 'name' => 'Analyst', 'active' => true]);

        return [$department, $unit, $position];
    }

    /** @return array<string, mixed> */
    private function employeeAttributes(string $personnelNumber, ?string $department, ?string $unit, ?string $position): array
    {
        return [
            'sap' => $personnelNumber,
            'txt_dept' => $department,
            'organizational_unit' => $unit,
            'position' => $position,
        ];
    }
}
