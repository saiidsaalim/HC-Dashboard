<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
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

    public function test_department_master_page_links_directly_to_unit_and_position_creation(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('organization.departments.index'))
            ->assertOk()
            ->assertSee(route('organization.units.create'))
            ->assertSee(route('organization.positions.create'))
            ->assertSee('Tambah Unit')
            ->assertSee('Tambah Position');
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

    public function test_super_admin_can_create_a_position_for_a_unit(): void
    {
        $unit = $this->unit();

        $this->actingAs($this->superAdmin())->post(route('organization.positions.store'), [
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
                'unit_id' => 999,
                'code' => 'P99',
                'name' => 'Invalid Position',
                'active' => '1',
            ])->assertRedirect(route('organization.positions.create'))
            ->assertSessionHasErrors('unit_id');

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
            'unit_id' => $unit->id,
            'code' => 'P01',
            'name' => 'Duplicate Position Code',
            'active' => '1',
        ])->assertSessionHasErrors('code');

        $this->post(route('organization.positions.store'), [
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
            'unit_id' => $unit->id, 'code' => 'P05', 'name' => 'Blocked', 'active' => '1',
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('organization.departments.update', $department), [
            'code' => 'D04', 'name' => 'Changed', 'active' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('organization.units.update', $unit), [
            'department_id' => $department->id, 'code' => 'U04', 'name' => 'Changed', 'active' => '1',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('organization.positions.update', $position), [
            'unit_id' => $unit->id, 'code' => 'P04', 'name' => 'Changed', 'active' => '1',
        ])->assertForbidden();

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
            'unit_id' => $unit->id, 'code' => 'P06', 'name' => 'People Partner', 'active' => '0',
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
