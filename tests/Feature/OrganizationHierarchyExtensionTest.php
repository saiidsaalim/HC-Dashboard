<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use App\Models\User;
use App\Services\Employees\EmployeeOrganizationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationHierarchyExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sync_maps_txt_dir_and_txt_sect_into_complete_hierarchy(): void
    {
        $employee = Employee::query()->create([
            'sap' => 'FULL-HIERARCHY-001',
            'txt_dir' => 'Commercial Directorate',
            'txt_dept' => 'Sales',
            'txt_biro' => 'Retail',
            'txt_sect' => 'Domestic',
            'position' => 'Analyst',
        ]);

        $result = app(EmployeeOrganizationSyncService::class)->apply();

        $directorate = Directorate::query()->sole();
        $department = Department::query()->sole();
        $unit = Unit::query()->sole();
        $section = Section::query()->sole();
        $position = Position::query()->sole();
        $employee->refresh();

        $this->assertSame($directorate->id, $department->directorate_id);
        $this->assertSame($department->id, $unit->department_id);
        $this->assertSame($unit->id, $section->unit_id);
        $this->assertSame($section->id, $position->section_id);
        $this->assertSame($unit->id, $position->unit_id);
        $this->assertSame($directorate->id, $employee->directorate_id);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($unit->id, $employee->unit_id);
        $this->assertSame($section->id, $employee->section_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame(1, $result['applied']['directorates_created']);
        $this->assertSame(1, $result['applied']['sections_created']);
        $this->assertSame('Commercial Directorate', $employee->txt_dir);
        $this->assertSame('Domestic', $employee->txt_sect);
    }

    public function test_sync_scopes_same_department_and_section_names_to_their_parents(): void
    {
        foreach (['North Directorate', 'South Directorate'] as $index => $directorate) {
            Employee::query()->create([
                'sap' => 'SCOPED-'.($index + 1),
                'txt_dir' => $directorate,
                'txt_dept' => 'Operations',
                'txt_biro' => 'Control',
                'txt_sect' => 'Planning',
                'position' => 'Officer',
            ]);
        }

        app(EmployeeOrganizationSyncService::class)->apply();

        $this->assertDatabaseCount('directorates', 2);
        $this->assertDatabaseCount('departments', 2);
        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('sections', 2);
        $this->assertDatabaseCount('positions', 2);
        $this->assertCount(2, Employee::query()->pluck('section_id')->unique());
    }

    public function test_sync_can_upgrade_matching_legacy_foreign_keys_to_the_extended_hierarchy(): void
    {
        $legacyDepartment = Department::query()->create(['code' => 'LEGACY-D', 'name' => 'Sales', 'active' => true]);
        $legacyUnit = $legacyDepartment->units()->create(['code' => 'LEGACY-U', 'name' => 'Retail', 'active' => true]);
        $legacyPosition = $legacyUnit->positions()->create(['code' => 'LEGACY-P', 'name' => 'Analyst', 'active' => true]);
        $employee = Employee::query()->create([
            'sap' => 'LEGACY-HIERARCHY-001',
            'txt_dir' => 'Commercial Directorate',
            'txt_dept' => 'Sales',
            'txt_biro' => 'Retail',
            'txt_sect' => 'Domestic',
            'position' => 'Analyst',
            'department_id' => $legacyDepartment->id,
            'unit_id' => $legacyUnit->id,
            'position_id' => $legacyPosition->id,
        ]);
        $service = app(EmployeeOrganizationSyncService::class);

        $preview = $service->audit();
        $result = $service->apply();
        $employee->refresh();

        $this->assertSame('ready_create_master', $preview['rows'][0]['status']);
        $this->assertSame(5, $preview['rows'][0]['foreign_keys_to_update']);
        $this->assertSame(1, $result['applied']['employees_updated']);
        $this->assertNotSame($legacyDepartment->id, $employee->department_id);
        $this->assertNotSame($legacyUnit->id, $employee->unit_id);
        $this->assertNotSame($legacyPosition->id, $employee->position_id);
        $this->assertNotNull($employee->directorate_id);
        $this->assertNotNull($employee->section_id);
    }

    public function test_sync_can_replace_legacy_ref_unit_when_source_uses_zero_placeholder(): void
    {
        $legacyDepartment = Department::query()->create(['code' => 'LEGACY-REF-D', 'name' => 'Infrastructure', 'active' => true]);
        $legacyUnit = $legacyDepartment->units()->create(['code' => 'LEGACY-REF-U', 'name' => '#REF!', 'active' => true]);
        $legacyPosition = $legacyUnit->positions()->create(['code' => 'LEGACY-REF-P', 'name' => 'General Manager', 'active' => true]);
        $employee = Employee::query()->create([
            'sap' => 'LEGACY-REF-001',
            'txt_dir' => 'Operation Directorate',
            'txt_dept' => 'Infrastructure',
            'txt_biro' => '0',
            'txt_sect' => '0',
            'position' => 'General Manager',
            'department_id' => $legacyDepartment->id,
            'unit_id' => $legacyUnit->id,
            'position_id' => $legacyPosition->id,
        ]);
        $service = app(EmployeeOrganizationSyncService::class);

        $preview = $service->audit();
        $service->apply();
        $employee->refresh();

        $this->assertSame('ready_create_master', $preview['rows'][0]['status']);
        $this->assertSame('0', $employee->unit->name);
        $this->assertSame('0', $employee->organizationSection->name);
        $this->assertSame('General Manager', $employee->organizationPosition->name);
    }

    public function test_master_organization_exposes_directorate_and_section_crud(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $this->actingAs($user)->post(route('organization.directorates.store'), [
            'code' => 'DIR-001',
            'name' => 'Corporate Directorate',
            'active' => '1',
        ])->assertRedirect(route('organization.directorates.index'));

        $directorate = Directorate::query()->sole();
        $department = Department::query()->create(['directorate_id' => $directorate->id, 'code' => 'D-001', 'name' => 'People', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U-001', 'name' => 'Talent', 'active' => true]);

        $this->post(route('organization.sections.store'), [
            'unit_id' => $unit->id,
            'code' => 'S-001',
            'name' => 'Development',
            'active' => '1',
        ])->assertRedirect(route('organization.sections.index'));

        $this->get(route('organization.index'))
            ->assertOk()
            ->assertSeeInOrder(['Directorate', 'Department', 'Unit', 'Section', 'Position']);
        $this->assertDatabaseHas('sections', ['unit_id' => $unit->id, 'name' => 'Development']);
    }
}
