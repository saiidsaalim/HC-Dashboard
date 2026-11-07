<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationFlowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_with_unstaffed_positions_cannot_move_to_another_unit(): void
    {
        $unit = $this->unit();
        $otherUnit = $unit->department->units()->create(['code' => 'OTHER', 'name' => 'Other Unit']);
        $section = $unit->sections()->create(['code' => 'SECTION', 'name' => 'Section']);
        $position = $unit->positions()->create(['section_id' => $section->id, 'code' => 'POSITION', 'name' => 'Position']);

        $this->actingAs($this->superAdmin())->from(route('organization.sections.edit', $section))
            ->put(route('organization.sections.update', $section), [
                'unit_id' => $otherUnit->id,
                'code' => $section->code,
                'name' => 'Should Not Be Saved',
                'active' => '1',
            ])->assertRedirect(route('organization.sections.edit', $section))
            ->assertSessionHasErrors([
                'unit_id' => 'Section tidak dapat dipindahkan selama masih memiliki Position. Pindahkan Position melalui pengelolaan posisi terlebih dahulu.',
            ])->assertSessionHasInput('unit_id', $otherUnit->id);

        $this->assertDatabaseHas('sections', ['id' => $section->id, 'unit_id' => $unit->id, 'name' => 'Section']);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'unit_id' => $unit->id, 'section_id' => $section->id]);
    }

    public function test_unreferenced_section_can_move_to_another_unit(): void
    {
        $unit = $this->unit();
        $otherUnit = $unit->department->units()->create(['code' => 'OTHER', 'name' => 'Other Unit']);
        $section = $unit->sections()->create(['code' => 'SECTION', 'name' => 'Section']);

        $this->actingAs($this->superAdmin())->put(route('organization.sections.update', $section), [
            'unit_id' => $otherUnit->id,
            'code' => $section->code,
            'name' => 'Moved Section',
            'active' => '1',
        ])->assertRedirect(route('organization.sections.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sections', ['id' => $section->id, 'unit_id' => $otherUnit->id, 'name' => 'Moved Section']);
    }

    #[DataProvider('externalReportingDirections')]
    public function test_unit_move_rejects_reporting_edges_crossing_the_unit_boundary(string $direction): void
    {
        $unit = $this->unit();
        $outsideUnit = $unit->department->units()->create(['code' => 'OUTSIDE', 'name' => 'Outside Unit']);
        $targetDepartment = Department::query()->create(['code' => 'TARGET', 'name' => 'Target Department']);
        $movingPosition = $unit->positions()->create(['code' => 'MOVING', 'name' => 'Moving Position']);
        $outsidePosition = $outsideUnit->positions()->create(['code' => 'OUTSIDE', 'name' => 'Outside Position']);
        $child = $direction === 'parent' ? $movingPosition : $outsidePosition;
        $parent = $direction === 'parent' ? $outsidePosition : $movingPosition;
        $child->update(['parent_position_id' => $parent->id]);

        $this->actingAs($this->superAdmin())->from(route('organization.units.edit', $unit))
            ->put(route('organization.units.update', $unit), [
                'department_id' => $targetDepartment->id,
                'code' => $unit->code,
                'name' => 'Should Not Be Saved',
                'active' => '1',
            ])->assertRedirect(route('organization.units.edit', $unit))
            ->assertSessionHasErrors([
                'department_id' => 'Department Unit tidak dapat dipindahkan selama Position memiliki relasi atasan atau bawahan di Unit lain. Sesuaikan relasi posisi terlebih dahulu.',
            ])->assertSessionHasInput('department_id', $targetDepartment->id);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'department_id' => $outsideUnit->department_id, 'name' => 'Unit']);
        $this->assertDatabaseHas('positions', ['id' => $movingPosition->id, 'unit_id' => $unit->id]);
        $this->assertDatabaseHas('positions', ['id' => $outsidePosition->id, 'unit_id' => $outsideUnit->id]);
        $this->assertDatabaseHas('positions', ['id' => $child->id, 'parent_position_id' => $parent->id]);
    }

    public function test_unit_with_only_internal_reporting_edges_can_move_without_rewriting_positions(): void
    {
        $unit = $this->unit();
        $targetDepartment = Department::query()->create(['code' => 'TARGET', 'name' => 'Target Department']);
        $parent = $unit->positions()->create(['code' => 'PARENT', 'name' => 'Parent Position']);
        $child = $unit->positions()->create(['code' => 'CHILD', 'name' => 'Child Position', 'parent_position_id' => $parent->id]);

        $this->actingAs($this->superAdmin())->put(route('organization.units.update', $unit), [
            'department_id' => $targetDepartment->id,
            'code' => $unit->code,
            'name' => 'Moved Unit',
            'active' => '1',
        ])->assertRedirect(route('organization.units.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'department_id' => $targetDepartment->id, 'name' => 'Moved Unit']);
        $this->assertDatabaseHas('positions', ['id' => $parent->id, 'unit_id' => $unit->id, 'parent_position_id' => null]);
        $this->assertDatabaseHas('positions', ['id' => $child->id, 'unit_id' => $unit->id, 'parent_position_id' => $parent->id]);
    }

    public function test_unit_move_preserves_employee_mapping_recorded_only_on_a_section(): void
    {
        $unit = $this->unit();
        $section = $unit->sections()->create(['code' => 'SECTION', 'name' => 'Section']);
        $employee = Employee::query()->create(['sap' => 'SECTION-ONLY', 'section_id' => $section->id]);
        $targetDepartment = Department::query()->create(['code' => 'TARGET', 'name' => 'Target Department']);

        $this->actingAs($this->superAdmin())->from(route('organization.units.edit', $unit))
            ->put(route('organization.units.update', $unit), [
                'department_id' => $targetDepartment->id,
                'code' => $unit->code,
                'name' => 'Should Not Be Saved',
                'active' => '1',
            ])->assertSessionHasErrors([
                'department_id' => 'Department Unit tidak dapat dipindahkan selama ada Employee yang terhubung.',
            ]);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'department_id' => $unit->department_id, 'name' => 'Unit']);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'unit_id' => null, 'department_id' => null, 'section_id' => $section->id]);
    }

    #[DataProvider('descendantEmployeeMappings')]
    public function test_department_move_preserves_employee_mapping_recorded_only_on_a_descendant(string $field): void
    {
        $directorate = Directorate::query()->create(['code' => 'ORIGINAL', 'name' => 'Original Directorate']);
        $targetDirectorate = Directorate::query()->create(['code' => 'TARGET', 'name' => 'Target Directorate']);
        $department = Department::query()->create(['code' => 'DEPARTMENT', 'name' => 'Department', 'directorate_id' => $directorate->id]);
        $unit = $department->units()->create(['code' => 'UNIT', 'name' => 'Unit']);
        $section = $unit->sections()->create(['code' => 'SECTION', 'name' => 'Section']);
        $position = $unit->positions()->create(['code' => 'POSITION', 'name' => 'Position', 'section_id' => $section->id]);
        $mappingId = $field === 'position_id' ? $position->id : $section->id;
        $employee = Employee::query()->create(['sap' => 'DESCENDANT-ONLY', $field => $mappingId]);

        $this->actingAs($this->superAdmin())->from(route('organization.departments.edit', $department))
            ->put(route('organization.departments.update', $department), [
                'directorate_id' => $targetDirectorate->id,
                'code' => $department->code,
                'name' => 'Should Not Be Saved',
                'active' => '1',
            ])->assertRedirect(route('organization.departments.edit', $department))
            ->assertSessionHasErrors([
                'directorate_id' => 'Department tidak dapat dipindahkan selama ada Employee yang terhubung.',
            ]);

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'directorate_id' => $directorate->id, 'name' => 'Department']);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'directorate_id' => null, 'department_id' => null, 'unit_id' => null, $field => $mappingId]);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'unit_id' => $unit->id, 'section_id' => $section->id]);
    }

    public function test_department_can_change_directorate_when_descendants_have_no_employees(): void
    {
        $unit = $this->unit();
        $department = $unit->department;
        $position = $unit->positions()->create(['code' => 'POSITION', 'name' => 'Position']);
        $targetDirectorate = Directorate::query()->create(['code' => 'TARGET', 'name' => 'Target Directorate']);

        $this->actingAs($this->superAdmin())->put(route('organization.departments.update', $department), [
            'directorate_id' => $targetDirectorate->id,
            'code' => $department->code,
            'name' => 'Moved Department',
            'active' => '1',
        ])->assertRedirect(route('organization.departments.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'directorate_id' => $targetDirectorate->id, 'name' => 'Moved Department']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'department_id' => $department->id]);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'unit_id' => $unit->id]);
    }

    #[DataProvider('masterResources')]
    public function test_deleting_a_master_with_direct_employee_links_preserves_the_mapping(string $resource, string $field, string $label): void
    {
        $record = $this->standaloneMaster($resource);
        $employee = Employee::query()->create(['sap' => 'LINKED', $field => $record->id]);

        $this->actingAs($this->superAdmin())->from(route('organization.'.$resource.'.index'))
            ->delete(route('organization.'.$resource.'.destroy', $record))
            ->assertRedirect(route('organization.'.$resource.'.index'))
            ->assertSessionHasErrors([
                substr($field, 0, -3) => $label.' tidak dapat dihapus selama ada Employee yang terhubung.',
            ]);

        $this->assertModelExists($record);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, $field => $record->id]);
    }

    #[DataProvider('masterResources')]
    public function test_deleting_a_master_referenced_by_history_returns_an_actionable_error(string $resource, string $field, string $label): void
    {
        $record = $this->standaloneMaster($resource);
        $employee = Employee::query()->create(['sap' => 'HISTORY', 'name' => 'Historical Employee']);
        $mutation = EmployeeMutation::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => $employee->name,
            'departemen_lama' => 'Original Department',
            'jabatan_lama' => 'Original Position',
            'departemen_baru' => 'New Department',
            'jabatan_baru' => 'New Position',
            'tmt' => '2026-11-07',
            'pg' => 'PG',
            'level_lama' => 'A',
            'jg_lama' => '1',
            'level_baru' => 'B',
            'jg_baru' => '2',
            'old_'.$field => $record->id,
        ]);

        $this->actingAs($this->superAdmin())->from(route('organization.'.$resource.'.index'))
            ->delete(route('organization.'.$resource.'.destroy', $record))
            ->assertRedirect(route('organization.'.$resource.'.index'))
            ->assertSessionHasErrors([
                substr($field, 0, -3) => $label.' tidak dapat dihapus karena masih digunakan oleh data lain.',
            ]);

        $this->assertModelExists($record);
        $this->assertDatabaseHas('employee_mutations', ['id' => $mutation->id, 'old_'.$field => $record->id]);
    }

    #[DataProvider('masterResources')]
    public function test_unreferenced_master_records_can_still_be_deleted(string $resource, string $field, string $label): void
    {
        $record = $this->standaloneMaster($resource);

        $this->actingAs($this->superAdmin())->delete(route('organization.'.$resource.'.destroy', $record))
            ->assertRedirect(route('organization.'.$resource.'.index'))
            ->assertSessionHas('status', $label.' berhasil dihapus.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
    }

    /** @return array<string, array{string}> */
    public static function externalReportingDirections(): array
    {
        return ['parent outside unit' => ['parent'], 'child outside unit' => ['child']];
    }

    /** @return array<string, array{string}> */
    public static function descendantEmployeeMappings(): array
    {
        return ['position only' => ['position_id'], 'section only' => ['section_id']];
    }

    /** @return array<string, array{string, string, string}> */
    public static function masterResources(): array
    {
        return [
            'directorate' => ['directorates', 'directorate_id', 'Directorate'],
            'department' => ['departments', 'department_id', 'Department'],
            'unit' => ['units', 'unit_id', 'Unit'],
            'section' => ['sections', 'section_id', 'Section'],
        ];
    }

    private function standaloneMaster(string $resource): Model
    {
        return match ($resource) {
            'directorates' => Directorate::query()->create(['code' => 'DIRECTORATE', 'name' => 'Directorate']),
            'departments' => Department::query()->create(['code' => 'DEPARTMENT', 'name' => 'Department']),
            'units' => $this->unit(),
            'sections' => $this->unit()->sections()->create(['code' => 'SECTION', 'name' => 'Section']),
        };
    }

    private function unit(): Unit
    {
        $department = Department::query()->create(['code' => 'DEPARTMENT', 'name' => 'Department']);

        return $department->units()->create(['code' => 'UNIT', 'name' => 'Unit']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
