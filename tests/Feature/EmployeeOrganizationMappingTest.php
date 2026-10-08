<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Employees\EmployeeOrganizationMapper;
use App\Services\Employees\EmployeeOrganizationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

        foreach (['department', 'txt_dept', 'txt_biro', 'organizational_unit', 'position', 'bureau', 'section'] as $legacyField) {
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

        $parentScoped = $mapper->resolve($department->name, $unit->name, 'Manager');
        $this->assertSame([], $parentScoped['conflicts']);
        $this->assertSame('unmapped', $parentScoped['statuses']['position']);
        $this->assertNull($parentScoped['ids']['position_id']);
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
            ->expectsOutput('Mode: dry-run')
            ->expectsOutput('Total Employees: 4')
            ->expectsOutput('Empty txt_dept: 1')
            ->expectsOutput('Employees Requiring Review: 2')
            ->expectsOutput('Dry-run selesai. Database tidak diubah.')
            ->assertSuccessful();

        $this->assertNull($mappable->fresh()->department_id);
        $this->assertNull($mappable->fresh()->unit_id);
        $this->assertNull($mappable->fresh()->position_id);
        $this->assertSame('Sales', $mappable->fresh()->txt_dept);
        $this->assertNull($unmatched->fresh()->department_id);
        $this->assertNull($conflicting->fresh()->position_id);

        $this->artisan('employees:map-organization --apply --chunk=1 --confirm=APPLY-EMPLOYEE-ORGANIZATION')
            ->expectsOutput('Mode: apply')
            ->expectsOutput('Employee rows updated: 2')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', [
            'id' => $mappable->id,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'txt_dept' => 'Sales',
            'txt_biro' => 'Field Team',
            'position' => 'Analyst',
        ]);
        $this->assertNull($unmatched->fresh()->department_id);
        $this->assertNotNull($conflicting->fresh()->department_id);
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
            ->expectsOutputToContain('Review required:')
            ->expectsOutput('REVIEW EMPLOYEES')
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
            'txt_biro' => 'Field Team',
            'position' => 'Analyst',
        ]);
    }

    public function test_default_command_is_dry_run_and_uses_txt_biro_instead_of_organizational_unit(): void
    {
        $this->organization();
        $employee = Employee::create([
            ...$this->employeeAttributes('SAPSYNC001', 'Sales', 'Field Team', 'Analyst'),
            'organizational_unit' => 'Wrong Unit Source',
        ]);

        $this->artisan('employees:map-organization')
            ->expectsOutput('Mode: dry-run')
            ->expectsOutput('Foreign Keys To Update: 3')
            ->assertSuccessful();

        $employee->refresh();
        $this->assertNull($employee->department_id);
        $this->assertNull($employee->unit_id);
        $this->assertNull($employee->position_id);
        $this->assertSame('Wrong Unit Source', $employee->organizational_unit);
        $this->assertDatabaseCount('departments', 1);
    }

    public function test_apply_reuses_normalized_masters_and_preserves_legacy_values(): void
    {
        [$department, $unit, $position] = $this->organization();
        $employee = Employee::create($this->employeeAttributes('SAPSYNC002', '  SALES ', ' field   team ', ' ANALYST '));

        app(EmployeeOrganizationSyncService::class)->apply(1);

        $employee->refresh();
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($unit->id, $employee->unit_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame('  SALES ', $employee->txt_dept);
        $this->assertSame(' field   team ', $employee->txt_biro);
        $this->assertSame(' ANALYST ', $employee->position);
        $this->assertDatabaseCount('departments', 1);
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_apply_creates_deterministic_hierarchical_masters_and_is_idempotent(): void
    {
        $first = Employee::create($this->employeeAttributes('SAPSYNC003', 'Operations', 'Control Room', 'Operator'));
        $second = Employee::create($this->employeeAttributes('SAPSYNC004', ' operations ', ' CONTROL   ROOM ', ' operator '));
        $service = app(EmployeeOrganizationSyncService::class);

        $this->artisan('employees:map-organization --dry-run --chunk=1')
            ->expectsOutput('New Departments: 1')
            ->expectsOutput('New Units: 1')
            ->expectsOutput('New Positions: 1')
            ->assertSuccessful();
        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('positions', 0);

        $firstRun = $service->apply(1);
        $codes = [
            Department::query()->sole()->code,
            Unit::query()->sole()->code,
            Position::query()->sole()->code,
        ];
        $secondRun = $service->apply(1);

        $this->assertSame(2, $firstRun['applied']['employees_updated']);
        $this->assertSame(0, $secondRun['applied']['employees_updated']);
        $this->assertSame(0, $secondRun['applied']['departments_created']);
        $this->assertSame(0, $secondRun['applied']['units_created']);
        $this->assertSame(0, $secondRun['applied']['positions_created']);
        $this->assertSame(2, $secondRun['summary']['statuses']['already_mapped']);
        $this->assertSame($first->fresh()->department_id, $second->fresh()->department_id);
        $this->assertSame($first->fresh()->unit_id, $second->fresh()->unit_id);
        $this->assertSame($first->fresh()->position_id, $second->fresh()->position_id);
        $this->assertSame($codes, [Department::query()->sole()->code, Unit::query()->sole()->code, Position::query()->sole()->code]);
        foreach ($codes as $code) {
            $this->assertStringStartsWith('AUTO-', $code);
        }
    }

    public function test_same_unit_and_position_names_are_scoped_to_their_parents(): void
    {
        $first = Employee::create($this->employeeAttributes('SAPSYNC005', 'North', 'Operations', 'Analyst'));
        $second = Employee::create($this->employeeAttributes('SAPSYNC006', 'South', 'Operations', 'Analyst'));

        app(EmployeeOrganizationSyncService::class)->apply();

        $first->refresh();
        $second->refresh();
        $this->assertNotSame($first->department_id, $second->department_id);
        $this->assertNotSame($first->unit_id, $second->unit_id);
        $this->assertNotSame($first->position_id, $second->position_id);
        $this->assertDatabaseCount('departments', 2);
        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('positions', 2);
    }

    public function test_empty_hierarchy_values_require_review_and_are_not_changed(): void
    {
        $employee = Employee::create($this->employeeAttributes('SAPSYNC007', null, 'Unit Without Department', 'Analyst'));

        $audit = app(EmployeeOrganizationSyncService::class)->audit();
        app(EmployeeOrganizationSyncService::class)->apply();

        $this->assertSame('needs_review', $audit['rows'][0]['status']);
        $this->assertStringContainsString('txt_dept kosong', $audit['rows'][0]['reason']);
        $this->assertDatabaseCount('departments', 0);
        $this->assertNull($employee->fresh()->department_id);
    }

    public function test_conflicting_existing_foreign_keys_are_not_overwritten(): void
    {
        $this->organization();
        $otherDepartment = Department::create(['code' => 'DPT-CONFLICT', 'name' => 'Engineering', 'active' => true]);
        $employee = Employee::create([
            ...$this->employeeAttributes('SAPSYNC008', 'Sales', 'Field Team', 'Analyst'),
            'department_id' => $otherDepartment->id,
        ]);

        $audit = app(EmployeeOrganizationSyncService::class)->audit();
        app(EmployeeOrganizationSyncService::class)->apply();

        $this->assertSame('conflict', $audit['rows'][0]['status']);
        $this->assertSame($otherDepartment->id, $employee->fresh()->department_id);
        $this->assertNull($employee->fresh()->unit_id);
        $this->assertNull($employee->fresh()->position_id);
    }

    public function test_duplicate_normalized_master_within_same_parent_is_a_conflict(): void
    {
        $department = Department::create(['code' => 'DPT-DUP', 'name' => 'Sales', 'active' => true]);
        $department->units()->create(['code' => 'UNT-DUP-A', 'name' => 'Field Team', 'active' => true]);
        $department->units()->create(['code' => 'UNT-DUP-B', 'name' => ' field   team ', 'active' => true]);
        $employee = Employee::create($this->employeeAttributes('SAPSYNC009', 'Sales', 'FIELD TEAM', 'Analyst'));

        $audit = app(EmployeeOrganizationSyncService::class)->audit();

        $this->assertSame('conflict', $audit['rows'][0]['status']);
        $this->assertContains('unit', $audit['rows'][0]['conflict_fields']);
        $this->assertNull($employee->fresh()->unit_id);
    }

    public function test_apply_rolls_back_all_changes_when_an_employee_update_fails(): void
    {
        Employee::create($this->employeeAttributes('SAPSYNC010', 'Operations', 'Control', 'Operator'));
        Employee::create($this->employeeAttributes('SAPSYNC011', 'Finance', 'Treasury', 'Analyst'));
        $updates = 0;
        Employee::updating(function () use (&$updates): void {
            $updates++;
            if ($updates === 2) {
                throw new RuntimeException('Simulated mapping failure.');
            }
        });

        try {
            app(EmployeeOrganizationSyncService::class)->apply(1);
            $this->fail('The simulated failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated mapping failure.', $exception->getMessage());
        } finally {
            Employee::flushEventListeners();
        }

        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->assertSame(0, Employee::query()->whereNotNull('department_id')->count());
        $this->assertDatabaseCount('employee_organization_mapping_audits', 0);
    }

    public function test_csv_report_contains_internal_ids_but_no_personal_data(): void
    {
        Employee::create([
            ...$this->employeeAttributes('SECRET-SAP', 'Sales', 'Field Team', 'Analyst'),
            'email' => 'secret@example.test',
            'address' => 'Private address',
        ]);
        $path = sys_get_temp_dir().'/employee-organization-report-'.uniqid().'.csv';

        $this->artisan('employees:map-organization', ['--report' => $path])
            ->expectsOutputToContain('CSV report dibuat:')
            ->assertSuccessful();

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('employee_id', $contents);
        $this->assertStringNotContainsString('SECRET-SAP', $contents);
        $this->assertStringNotContainsString('secret@example.test', $contents);
        $this->assertStringNotContainsString('Private address', $contents);
        unlink($path);
    }

    public function test_apply_requires_confirmation_and_explicit_token_can_apply(): void
    {
        $employee = Employee::create($this->employeeAttributes('SAPSYNC012', 'Sales', 'Field Team', 'Analyst'));

        $this->artisan('employees:map-organization --apply')
            ->expectsConfirmation('Terapkan sinkronisasi organisasi pegawai yang aman?', 'no')
            ->assertFailed();
        $this->assertNull($employee->fresh()->department_id);

        $this->artisan('employees:map-organization --apply --confirm='.EmployeeOrganizationSyncService::Confirmation)
            ->assertSuccessful();
        $this->assertNotNull($employee->fresh()->department_id);
    }

    public function test_non_interactive_apply_requires_the_explicit_confirmation_token(): void
    {
        $employee = Employee::create($this->employeeAttributes('SAPSYNC014', 'Sales', 'Field Team', 'Analyst'));

        $exitCode = Artisan::call('employees:map-organization', [
            '--apply' => true,
            '--no-interaction' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Mode non-interaktif memerlukan', Artisan::output());
        $this->assertNull($employee->fresh()->department_id);
    }

    public function test_employee_sync_does_not_change_wla_or_final_snapshot(): void
    {
        [$department, $unit, $position] = $this->organization();
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $schedule = WorkSchedule::create([
            'code' => 'DAYSHIFT-SYNC',
            'name' => 'Dayshift Sync',
            'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00',
            'working_days_per_week' => 5,
            'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => 2025,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 17,
            'common_leave' => 6,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'active' => true,
        ]);
        $assessment = WlaAssessment::create([
            'assessment_code' => 'WLA-SYNC-UNCHANGED',
            'period' => 2025,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'work_schedule_id' => $schedule->id,
            'work_calendar_id' => $calendar->id,
            'efficiency_factor' => '0.9000',
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $user->id,
        ]);
        $assessment->forceFill([
            'status' => WlaAssessmentStatus::Final,
            'finalization_key' => '2025:'.$position->id,
            'finalized_at' => now(),
            'finalized_by' => $user->id,
            'final_snapshot' => ['assessment_code' => 'WLA-SYNC-UNCHANGED', 'fte' => '1.234567'],
        ])->save();
        $before = $assessment->fresh()->getRawOriginal();
        Employee::create($this->employeeAttributes('SAPSYNC013', 'Sales', 'Field Team', 'Analyst'));

        app(EmployeeOrganizationSyncService::class)->apply();

        $after = $assessment->fresh()->getRawOriginal();
        $this->assertSame($before['department_id'], $after['department_id']);
        $this->assertSame($before['unit_id'], $after['unit_id']);
        $this->assertSame($before['position_id'], $after['position_id']);
        $this->assertSame($before['status'], $after['status']);
        $this->assertSame($before['final_snapshot'], $after['final_snapshot']);
        $this->assertSame($before['finalization_key'], $after['finalization_key']);
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
            'txt_biro' => $unit,
            'organizational_unit' => 'THIS FIELD MUST NOT BE USED',
            'position' => $position,
        ];
    }
}
