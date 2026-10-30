<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_department(): void
    {
        $this->actingAs($this->superAdmin())->post(route('organization.departments.store'), [
            'code' => 'D01',
            'name' => 'Finance',
            'active' => '1',
        ])->assertRedirect(route('organization.departments.index'));

        $this->assertDatabaseHas('departments', ['code' => 'D01', 'name' => 'Finance', 'active' => true]);
    }

    public function test_super_admin_can_open_organization_home_with_master_links_and_sidebar_target(): void
    {
        $activeDepartment = Department::create(['code' => 'D-HOME-A', 'name' => 'Active Department', 'active' => true]);
        Department::create(['code' => 'D-HOME-I', 'name' => 'Inactive Department', 'active' => false]);
        $activeUnit = $activeDepartment->units()->create(['code' => 'U-HOME-A', 'name' => 'Active Unit', 'active' => true]);
        $activeDepartment->units()->create(['code' => 'U-HOME-I', 'name' => 'Inactive Unit', 'active' => false]);
        $activeUnit->positions()->create(['code' => 'P-HOME-A', 'name' => 'Active Position', 'active' => true]);
        $activeUnit->positions()->create(['code' => 'P-HOME-I', 'name' => 'Inactive Position', 'active' => false]);

        $this->actingAs($this->superAdmin())
            ->get(route('organization.index'))
            ->assertOk()
            ->assertSee('Kelola Department')
            ->assertSee('Kelola Unit')
            ->assertSee('Kelola Position')
            ->assertSee(route('organization.departments.index'))
            ->assertSee(route('organization.units.index'))
            ->assertSee(route('organization.positions.index'))
            ->assertSee('<a href="'.route('organization.index').'" title="Master Organization"', false)
            ->assertSee('Total')
            ->assertSee('Aktif');
    }

    public function test_department_page_only_offers_department_creation(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('organization.departments.index'))
            ->assertOk()
            ->assertSee(route('organization.index'))
            ->assertSee('Tambah Department')
            ->assertDontSee(route('organization.units.create'))
            ->assertDontSee(route('organization.positions.create'));
    }

    public function test_organization_lists_and_parent_options_show_names_without_codes(): void
    {
        $department = Department::create([
            'code' => 'DEPARTMENT-CODE-HIDDEN',
            'name' => 'Visible Department Name',
            'active' => true,
        ]);
        $unit = $department->units()->create([
            'code' => 'UNIT-CODE-HIDDEN',
            'name' => 'Visible Unit Name',
            'active' => true,
        ]);
        $unit->positions()->create([
            'code' => 'POSITION-CODE-HIDDEN',
            'name' => 'Visible Position Name',
            'active' => true,
        ]);
        $this->actingAs($this->superAdmin());

        $this->get(route('organization.departments.index'))
            ->assertOk()
            ->assertSee('Visible Department Name')
            ->assertDontSee('DEPARTMENT-CODE-HIDDEN');

        $this->get(route('organization.units.index'))
            ->assertOk()
            ->assertSee('Visible Department Name')
            ->assertSee('Visible Unit Name')
            ->assertDontSee('DEPARTMENT-CODE-HIDDEN')
            ->assertDontSee('UNIT-CODE-HIDDEN');

        $this->get(route('organization.positions.index'))
            ->assertOk()
            ->assertSee('Visible Department Name')
            ->assertSee('Visible Unit Name')
            ->assertSee('Visible Position Name')
            ->assertDontSee('DEPARTMENT-CODE-HIDDEN')
            ->assertDontSee('UNIT-CODE-HIDDEN')
            ->assertDontSee('POSITION-CODE-HIDDEN');
    }

    public function test_delete_forms_use_reusable_sweet_alert_confirmation(): void
    {
        $unit = $this->unit();
        $department = $unit->department;
        $position = $unit->positions()->create([
            'code' => 'P-DELETE',
            'name' => 'Delete Confirmation Position',
            'active' => true,
        ]);
        $this->actingAs($this->superAdmin());

        $this->get(route('organization.departments.index'))
            ->assertOk()
            ->assertSee('action="'.route('organization.departments.destroy', $department).'"', false)
            ->assertSee('data-confirm-delete', false)
            ->assertSee('data-confirm-title="Hapus Department ini?"', false)
            ->assertSee('data-confirm-text="Department hanya dapat dihapus jika tidak memiliki Unit. Data yang dihapus tidak dapat dikembalikan."', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('onsubmit=', false);

        $this->get(route('organization.units.index'))
            ->assertOk()
            ->assertSee('action="'.route('organization.units.destroy', $unit).'"', false)
            ->assertSee('data-confirm-delete', false)
            ->assertSee('data-confirm-title="Hapus Unit ini?"', false)
            ->assertSee('data-confirm-text="Unit hanya dapat dihapus jika tidak memiliki Position. Data yang dihapus tidak dapat dikembalikan."', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('onsubmit=', false);

        $this->get(route('organization.positions.index'))
            ->assertOk()
            ->assertSee('action="'.route('organization.positions.destroy', $position).'"', false)
            ->assertSee('data-confirm-delete', false)
            ->assertSee('data-confirm-title="Hapus Position ini?"', false)
            ->assertSee('data-confirm-text="Data Position yang dihapus tidak dapat dikembalikan."', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('onsubmit=', false);
    }

    public function test_super_admin_can_create_a_unit_for_a_department(): void
    {
        $department = Department::create(['code' => 'D02', 'name' => 'Operations', 'active' => true]);

        $this->actingAs($this->superAdmin())->post(route('organization.units.store'), [
            'department_id' => $department->id,
            'code' => 'U01',
            'name' => 'Field Operations',
            'active' => '1',
        ])->assertRedirect(route('organization.units.index'));

        $this->assertDatabaseHas('units', [
            'department_id' => $department->id,
            'code' => 'U01',
            'name' => 'Field Operations',
        ]);
    }

    public function test_unit_creation_rejects_an_inactive_department(): void
    {
        $department = Department::create(['code' => 'D-INACTIVE', 'name' => 'Inactive Department', 'active' => false]);

        $this->actingAs($this->superAdmin())
            ->from(route('organization.units.create'))
            ->post(route('organization.units.store'), [
                'department_id' => $department->id,
                'code' => 'U-INACTIVE',
                'name' => 'Rejected Unit',
                'active' => '1',
            ])
            ->assertRedirect(route('organization.units.create'))
            ->assertSessionHasErrors([
                'department_id' => 'Department yang dipilih tidak tersedia atau tidak aktif.',
            ]);

        $this->assertDatabaseMissing('units', ['code' => 'U-INACTIVE']);
    }

    public function test_super_admin_can_create_a_position_for_a_unit(): void
    {
        $unit = $this->unit();

        $this->actingAs($this->superAdmin())->post(route('organization.positions.store'), [
            'department_id' => $unit->department_id,
            'unit_id' => $unit->id,
            'code' => 'P01',
            'name' => 'Analyst',
            'active' => '1',
        ])->assertRedirect(route('organization.positions.index'));

        $this->assertDatabaseHas('positions', [
            'unit_id' => $unit->id,
            'code' => 'P01',
            'name' => 'Analyst',
        ]);
    }

    public function test_position_creation_filters_and_validates_department_by_directorate(): void
    {
        $commercialDirectorate = Directorate::create([
            'code' => 'DIR-COM',
            'name' => 'Commercial Directorate',
            'active' => true,
        ]);
        $operationsDirectorate = Directorate::create([
            'code' => 'DIR-OPS',
            'name' => 'Operations Directorate',
            'active' => true,
        ]);
        $commercialDepartment = $commercialDirectorate->departments()->create([
            'code' => 'D-COM',
            'name' => 'Commercial Department',
            'active' => true,
        ]);
        $operationsDepartment = $operationsDirectorate->departments()->create([
            'code' => 'D-OPS',
            'name' => 'Operations Department',
            'active' => true,
        ]);
        $commercialUnit = $commercialDepartment->units()->create([
            'code' => 'U-COM',
            'name' => 'Commercial Unit',
            'active' => true,
        ]);
        $operationsUnit = $operationsDepartment->units()->create([
            'code' => 'U-OPS',
            'name' => 'Operations Unit',
            'active' => true,
        ]);
        $this->actingAs($this->superAdmin());

        $this->get(route('organization.positions.create', [
            'department_id' => $commercialDepartment->id,
            'unit_id' => $commercialUnit->id,
        ]))
            ->assertOk()
            ->assertSee('name="directorate_id"', false)
            ->assertSee("directorateId: '".$commercialDirectorate->id."'", false)
            ->assertSee("departmentId: '".$commercialDepartment->id."'", false)
            ->assertSee("unitId: '".$commercialUnit->id."'", false)
            ->assertSee("departments.filter(item => String(item.directorate_id ?? '') === String(directorateId))", false);

        $this->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                'directorate_id' => $commercialDirectorate->id,
                'department_id' => $operationsDepartment->id,
                'unit_id' => $operationsUnit->id,
                'code' => 'P-WRONG-DIR',
                'name' => 'Wrong Directorate',
                'active' => '1',
            ])
            ->assertSessionHasErrors([
                'department_id' => 'Department yang dipilih tidak aktif atau tidak berada pada Directorate terpilih.',
            ]);

        $this->post(route('organization.positions.store'), [
            'directorate_id' => $operationsDirectorate->id,
            'department_id' => $operationsDepartment->id,
            'unit_id' => $operationsUnit->id,
            'code' => 'P-OPS',
            'name' => 'Operations Position',
            'active' => '1',
        ])->assertRedirect(route('organization.positions.index'));

        $this->assertDatabaseHas('positions', [
            'unit_id' => $operationsUnit->id,
            'code' => 'P-OPS',
        ]);
        $this->assertDatabaseMissing('positions', ['code' => 'P-WRONG-DIR']);
    }

    public function test_position_creation_enforces_active_parent_hierarchy(): void
    {
        $department = Department::create(['code' => 'D-POS-A', 'name' => 'Active Department', 'active' => true]);
        $otherDepartment = Department::create(['code' => 'D-POS-B', 'name' => 'Other Department', 'active' => true]);
        $inactiveDepartment = Department::create(['code' => 'D-POS-I', 'name' => 'Inactive Department', 'active' => false]);
        $unit = $department->units()->create(['code' => 'U-POS-A', 'name' => 'Active Unit', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'U-POS-B', 'name' => 'Other Unit', 'active' => true]);
        $inactiveUnit = $department->units()->create(['code' => 'U-POS-I', 'name' => 'Inactive Unit', 'active' => false]);
        $unitInInactiveDepartment = $inactiveDepartment->units()->create(['code' => 'U-POS-DI', 'name' => 'Unit In Inactive Department', 'active' => true]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                'department_id' => $department->id,
                'unit_id' => $otherUnit->id,
                'code' => 'P-WRONG-BRANCH',
                'name' => 'Wrong Branch',
                'active' => '1',
            ])->assertSessionHasErrors([
                'unit_id' => 'Unit yang dipilih tidak aktif atau tidak berada pada Department terpilih.',
            ]);

        $this->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                'department_id' => $department->id,
                'unit_id' => $inactiveUnit->id,
                'code' => 'P-INACTIVE-UNIT',
                'name' => 'Inactive Unit Parent',
                'active' => '1',
            ])->assertSessionHasErrors('unit_id');

        $this->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                'department_id' => $inactiveDepartment->id,
                'unit_id' => $unitInInactiveDepartment->id,
                'code' => 'P-INACTIVE-DEPARTMENT',
                'name' => 'Inactive Department Parent',
                'active' => '1',
            ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_create_forms_only_offer_active_parents_and_support_valid_query_preselection(): void
    {
        $department = Department::create(['code' => 'D-FORM-A', 'name' => 'Selectable Department', 'active' => true]);
        $otherDepartment = Department::create(['code' => 'D-FORM-B', 'name' => 'Other Selectable Department', 'active' => true]);
        $inactiveDepartment = Department::create(['code' => 'D-FORM-I', 'name' => 'Hidden Department', 'active' => false]);
        $unit = $department->units()->create(['code' => 'U-FORM-A', 'name' => 'Selectable Unit', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'U-FORM-B', 'name' => 'Other Selectable Unit', 'active' => true]);
        $department->units()->create(['code' => 'U-FORM-I', 'name' => 'Hidden Inactive Unit', 'active' => false]);
        $inactiveDepartment->units()->create(['code' => 'U-FORM-DI', 'name' => 'Hidden Branch Unit', 'active' => true]);
        $this->actingAs($this->superAdmin());

        $this->get(route('organization.units.create', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('name="department_id"', false)
            ->assertSee('Selectable Department')
            ->assertDontSee('Hidden Department')
            ->assertSee('value="'.$department->id.'" selected', false)
            ->assertDontSee("@include('organization.units._form", false);

        $this->get(route('organization.positions.create', [
            'department_id' => $department->id,
            'unit_id' => $unit->id,
        ]))
            ->assertOk()
            ->assertSee('name="department_id"', false)
            ->assertSee(':disabled="!departmentId"', false)
            ->assertSee('units.filter(item => String(item.department_id) === String(departmentId))', false)
            ->assertSee("departmentId: '".$department->id."'", false)
            ->assertSee("unitId: '".$unit->id."'", false)
            ->assertSee('Selectable Unit')
            ->assertSee('Other Selectable Unit')
            ->assertDontSee('Hidden Inactive Unit')
            ->assertDontSee('Hidden Branch Unit')
            ->assertDontSee('Hidden Department')
            ->assertDontSee("@include('organization.positions._form", false);

        $this->get(route('organization.positions.create', [
            'department_id' => $department->id,
            'unit_id' => $otherUnit->id,
        ]))
            ->assertOk()
            ->assertSee("departmentId: '".$department->id."'", false)
            ->assertSee("unitId: ''", false);

        $this->assertNotSame($unit->department_id, $otherUnit->department_id);
    }

    public function test_position_form_keeps_valid_parent_choices_after_validation_error(): void
    {
        $unit = $this->unit();

        $this->actingAs($this->superAdmin())
            ->followingRedirects()
            ->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                'department_id' => $unit->department_id,
                'unit_id' => $unit->id,
                'code' => 'P-RETAIN',
                'name' => '',
                'active' => '1',
            ])
            ->assertOk()
            ->assertSee('Nama Position wajib diisi.')
            ->assertSee("departmentId: '".$unit->department_id."'", false)
            ->assertSee("unitId: '".$unit->id."'", false);
    }

    public function test_edit_can_keep_current_inactive_parents_but_cannot_move_to_other_inactive_parents(): void
    {
        $department = Department::create(['code' => 'D-EDIT-I', 'name' => 'Current Inactive Department', 'active' => false]);
        $unit = $department->units()->create(['code' => 'U-EDIT-I', 'name' => 'Current Inactive Unit', 'active' => false]);
        $position = $unit->positions()->create(['code' => 'P-EDIT-I', 'name' => 'Current Position', 'active' => true]);
        $otherDepartment = Department::create(['code' => 'D-EDIT-O', 'name' => 'Other Inactive Department', 'active' => false]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'U-EDIT-O', 'name' => 'Other Inactive Unit', 'active' => false]);
        $this->actingAs($this->superAdmin());

        $this->get(route('organization.units.edit', $unit))
            ->assertOk()
            ->assertSee('Current Inactive Department')
            ->assertDontSee('Other Inactive Department');
        $this->get(route('organization.positions.edit', $position))
            ->assertOk()
            ->assertSee('Current Inactive Department')
            ->assertSee('Current Inactive Unit')
            ->assertDontSee('Other Inactive Department')
            ->assertDontSee('Other Inactive Unit');

        $this->put(route('organization.units.update', $unit), [
            'department_id' => $department->id,
            'code' => $unit->code,
            'name' => 'Unit Retained',
            'active' => '0',
        ])->assertRedirect(route('organization.units.index'));
        $this->put(route('organization.positions.update', $position), [
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'code' => $position->code,
            'name' => 'Position Retained',
            'active' => '1',
        ])->assertRedirect(route('organization.positions.index'));

        $this->from(route('organization.units.edit', $unit))
            ->put(route('organization.units.update', $unit), [
                'department_id' => $otherDepartment->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'active' => '0',
            ])->assertSessionHasErrors('department_id');
        $this->from(route('organization.positions.edit', $position))
            ->put(route('organization.positions.update', $position), [
                'department_id' => $otherDepartment->id,
                'unit_id' => $otherUnit->id,
                'code' => $position->code,
                'name' => $position->name,
                'active' => '1',
            ])->assertSessionHasErrors(['department_id', 'unit_id']);

        $this->assertSame($department->id, $unit->fresh()->department_id);
        $this->assertSame($unit->id, $position->fresh()->unit_id);
    }

    public function test_unit_and_position_require_existing_parent_records(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->from(route('organization.units.create'))
            ->post(route('organization.units.store'), [
                'department_id' => 999,
                'code' => 'U99',
                'name' => 'Invalid Unit',
                'active' => '1',
            ])->assertRedirect(route('organization.units.create'))
            ->assertSessionHasErrors('department_id');

        $this->actingAs($admin)->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                'department_id' => 999,
                'unit_id' => 999,
                'code' => 'P99',
                'name' => 'Invalid Position',
                'active' => '1',
            ])->assertRedirect(route('organization.positions.create'))
            ->assertSessionHasErrors(['department_id', 'unit_id']);

        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_department_unit_position_relationships_are_traversable(): void
    {
        $department = Department::create(['code' => 'D03', 'name' => 'Technology', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U03', 'name' => 'Platforms', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P03', 'name' => 'Engineer', 'active' => true]);

        $this->assertSame($department->id, $unit->department->id);
        $this->assertSame($unit->id, $position->unit->id);
        $this->assertTrue($department->units->contains('id', $unit->id));
        $this->assertTrue($unit->positions->contains('id', $position->id));
        $this->assertInstanceOf(HasMany::class, $position->employees());
        $this->assertSame('position_id', $position->employees()->getForeignKeyName());
        $this->assertSame(Employee::class, get_class($position->employees()->getRelated()));
    }

    public function test_codes_are_unique_within_their_parent_context(): void
    {
        $department = Department::create(['code' => 'D09', 'name' => 'Planning', 'active' => true]);
        $otherDepartment = Department::create(['code' => 'D10', 'name' => 'Strategy', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U01', 'name' => 'Planning Unit', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'U01', 'name' => 'Strategy Unit', 'active' => true]);
        $unit->positions()->create(['code' => 'P01', 'name' => 'Planner', 'active' => true]);

        $this->actingAs($this->superAdmin())->from(route('organization.units.create'))
            ->post(route('organization.units.store'), [
                'department_id' => $department->id,
                'code' => 'U01',
                'name' => 'Duplicate Unit Code',
                'active' => '1',
            ])->assertRedirect(route('organization.units.create'))
            ->assertSessionHasErrors('code');

        $this->post(route('organization.positions.store'), [
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'code' => 'P01',
            'name' => 'Duplicate Position Code',
            'active' => '1',
        ])->assertSessionHasErrors('code');

        $this->post(route('organization.positions.store'), [
            'department_id' => $otherDepartment->id,
            'unit_id' => $otherUnit->id,
            'code' => 'P01',
            'name' => 'Planner',
            'active' => '1',
        ])->assertRedirect(route('organization.positions.index'));

        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('positions', 2);
    }

    public function test_non_super_admin_cannot_create_update_or_delete_organization_records(): void
    {
        $department = Department::create(['code' => 'D04', 'name' => 'Legal', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U04', 'name' => 'Compliance', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P04', 'name' => 'Advisor', 'active' => true]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->post(route('organization.departments.store'), [
            'code' => 'D05', 'name' => 'Blocked', 'active' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('organization.units.store'), [
            'department_id' => $department->id, 'code' => 'U05', 'name' => 'Blocked', 'active' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('organization.positions.store'), [
            'department_id' => $department->id, 'unit_id' => $unit->id, 'code' => 'P05', 'name' => 'Blocked', 'active' => '1',
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('organization.departments.update', $department), [
            'code' => 'D04', 'name' => 'Changed', 'active' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('organization.units.update', $unit), [
            'department_id' => $department->id, 'code' => 'U04', 'name' => 'Changed', 'active' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('organization.positions.update', $position), [
            'department_id' => $department->id, 'unit_id' => $unit->id, 'code' => 'P04', 'name' => 'Changed', 'active' => '1',
        ])->assertForbidden();

        $this->actingAs($admin)->get(route('organization.index'))->assertForbidden();

        $this->actingAs($admin)->delete(route('organization.departments.destroy', $department))->assertForbidden();
        $this->actingAs($admin)->delete(route('organization.units.destroy', $unit))->assertForbidden();
        $this->actingAs($admin)->delete(route('organization.positions.destroy', $position))->assertForbidden();

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Legal']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Compliance']);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'name' => 'Advisor']);
        $this->assertDatabaseMissing('departments', ['code' => 'D05']);
    }

    public function test_super_admin_can_update_and_delete_records_from_the_bottom_up(): void
    {
        $department = Department::create(['code' => 'D06', 'name' => 'People', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U06', 'name' => 'Talent', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P06', 'name' => 'Partner', 'active' => true]);

        $this->actingAs($this->superAdmin())->put(route('organization.departments.update', $department), [
            'code' => 'D06', 'name' => 'People Services', 'active' => '0',
        ])->assertRedirect(route('organization.departments.index'));
        $this->put(route('organization.units.update', $unit), [
            'department_id' => $department->id, 'code' => 'U06', 'name' => 'Talent Team', 'active' => '0',
        ])->assertRedirect(route('organization.units.index'));
        $this->put(route('organization.positions.update', $position), [
            'department_id' => $department->id, 'unit_id' => $unit->id, 'code' => 'P06', 'name' => 'People Partner', 'active' => '0',
        ])->assertRedirect(route('organization.positions.index'));

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'active' => false]);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'active' => false]);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'active' => false]);

        $this->delete(route('organization.positions.destroy', $position))->assertRedirect(route('organization.positions.index'));
        $this->delete(route('organization.units.destroy', $unit))->assertRedirect(route('organization.units.index'));
        $this->delete(route('organization.departments.destroy', $department))->assertRedirect(route('organization.departments.index'));

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_parent_records_with_children_cannot_be_deleted(): void
    {
        $department = Department::create(['code' => 'D07', 'name' => 'Research', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U07', 'name' => 'Lab', 'active' => true]);
        $unit->positions()->create(['code' => 'P07', 'name' => 'Scientist', 'active' => true]);

        $this->actingAs($this->superAdmin())->from(route('organization.departments.index'))
            ->delete(route('organization.departments.destroy', $department))
            ->assertRedirect(route('organization.departments.index'))
            ->assertSessionHasErrors('department');
        $this->from(route('organization.units.index'))
            ->delete(route('organization.units.destroy', $unit))
            ->assertRedirect(route('organization.units.index'))
            ->assertSessionHasErrors('unit');

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_organization_parent_cannot_move_employee_assignments_to_a_different_branch(): void
    {
        $department = Department::create(['code' => 'DPT-MOVE-A', 'name' => 'Move A', 'active' => true]);
        $otherDepartment = Department::create(['code' => 'DPT-MOVE-B', 'name' => 'Move B', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-MOVE-A', 'name' => 'Unit Move A', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'UNT-MOVE-B', 'name' => 'Unit Move B', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-MOVE-A', 'name' => 'Position Move A', 'active' => true]);
        $employee = Employee::create([
            'sap' => 'SAPMOVE001',
            'name' => 'Employee Move',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);

        $this->actingAs($this->superAdmin())->put(route('organization.units.update', $unit), [
            'department_id' => $otherDepartment->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'active' => '1',
        ])->assertSessionHasErrors('department_id');

        $this->put(route('organization.positions.update', $position), [
            'department_id' => $otherDepartment->id,
            'unit_id' => $otherUnit->id,
            'code' => $position->code,
            'name' => $position->name,
            'active' => '1',
        ])->assertSessionHasErrors('unit_id');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);
        $this->assertSame($department->id, $unit->fresh()->department_id);
        $this->assertSame($unit->id, $position->fresh()->unit_id);
    }

    public function test_organization_index_and_form_pages_render(): void
    {
        $department = Department::create(['code' => 'D11', 'name' => 'Security', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U08', 'name' => 'Identity', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P08', 'name' => 'Specialist', 'active' => true]);
        $this->actingAs($this->superAdmin());

        $this->get(route('organization.departments.index'))->assertOk()->assertSee('Security');
        $this->get(route('organization.departments.create'))->assertOk();
        $this->get(route('organization.departments.edit', $department))->assertOk();
        $this->get(route('organization.units.index', ['department_id' => $department->id]))->assertOk()->assertSee('Identity');
        $this->get(route('organization.units.create'))->assertOk();
        $this->get(route('organization.units.edit', $unit))->assertOk();
        $this->get(route('organization.positions.index', ['department_id' => $department->id]))->assertOk()->assertSee('Specialist');
        $this->get(route('organization.positions.create'))->assertOk();
        $this->get(route('organization.positions.edit', $position))->assertOk();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    private function unit(): Unit
    {
        $department = Department::create(['code' => 'D01', 'name' => 'Finance', 'active' => true]);

        return $department->units()->create(['code' => 'U01', 'name' => 'Accounting', 'active' => true]);
    }
}
