<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\Successor;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuccessorStructureManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_successor_structure_uses_active_master_organization_records(): void
    {
        [$department, $unit, $section, $position] = $this->organization();
        Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'GM-TALENT',
            'name' => 'General Manager of Talent',
            'job_level' => 'General Manager',
            'formation_count' => 1,
            'sort_order' => 0,
            'active' => true,
        ]);
        Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'SM-TALENT',
            'name' => 'Senior Manager of Talent Development',
            'job_level' => 'Senior Manager',
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
        Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'MGR-TALENT',
            'name' => 'Manager of Talent Performance',
            'job_level' => 'Manager',
            'formation_count' => 1,
            'sort_order' => 2,
            'active' => true,
        ]);
        foreach (range(1, 2) as $number) {
            Employee::query()->forceCreate([
                'sap' => 'SAP-TALENT-'.$number,
                'name' => 'Talent Employee '.$number,
                'position_id' => $position->id,
            ]);
        }
        Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'INACTIVE',
            'name' => 'Inactive Position',
            'job_level' => 'Staff',
            'formation_count' => 9,
            'sort_order' => 2,
            'active' => false,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertViewHas('successorStructures', function (array $structures) use ($department, $position): bool {
                $generalManager = $structures[$department->name][0];
                $seniorManager = $generalManager['children'][0];
                $manager = $seniorManager['children'][0];

                return $generalManager['level'] === 'General Manager'
                    && $seniorManager['level'] === 'Senior Manager'
                    && $manager['level'] === 'Manager'
                    && $manager['children'][0]['key'] === (string) $position->id;
            })
            ->assertSee($department->name)
            ->assertSeeInOrder([
                'General Manager of Talent',
                'Senior Manager of Talent Development',
                'Manager of Talent Performance',
                $position->name,
            ])
            ->assertSee('Formasi')
            ->assertSee('Terisi')
            ->assertSee('>2</span>', escape: false)
            ->assertSee('aria-label="Lihat successor untuk '.$position->name.'"', escape: false)
            ->assertDontSee($unit->name)
            ->assertDontSee($section->name)
            ->assertDontSee('data-organization-node', escape: false)
            ->assertDontSee('Inactive Position')
            ->assertDontSee('Editor Struktur')
            ->assertDontSee('Tambah Bawahan');
    }

    public function test_changes_in_master_position_are_reflected_in_successor_view(): void
    {
        [$department, , , $position] = $this->organization();

        $position->update([
            'name' => 'Updated Target Position',
            'job_level' => 'Senior Manager',
            'formation_count' => 7,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertSee('Updated Target Position')
            ->assertSee('Senior Manager')
            ->assertSee('Formasi')
            ->assertSee('>7</strong>', escape: false)
            ->assertSee('Terisi');
    }

    public function test_master_position_with_successors_cannot_be_deleted(): void
    {
        [, $unit, , $targetPosition] = $this->organization();
        $employeePosition = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'CURRENT',
            'name' => 'Current Position',
            'job_level' => 'Staff',
            'formation_count' => 1,
            'sort_order' => 2,
            'active' => true,
        ]);
        $employee = Employee::query()->forceCreate([
            'sap' => 'SAP-SUCCESSOR',
            'name' => 'Successor Employee',
            'position' => $employeePosition->name,
            'position_id' => $employeePosition->id,
            'unit_id' => $unit->id,
            'department_id' => $unit->department_id,
        ]);
        Successor::query()->create([
            'employee_id' => $employee->id,
            'target_position_id' => $targetPosition->id,
            'name' => $employee->name,
            'position' => $employee->position,
            'talent_mapping' => 'High Potential',
            'succession_readiness' => 'Ready Now',
            'idp' => 'Leadership program',
            'education' => '-',
            'tenure' => '-',
            'directorate' => '-',
        ]);

        $this->actingAs($this->superAdmin())
            ->from(route('organization.positions.index'))
            ->delete(route('organization.positions.destroy', $targetPosition))
            ->assertRedirect(route('organization.positions.index'))
            ->assertSessionHasErrors('position');

        $this->assertDatabaseHas('positions', ['id' => $targetPosition->id]);
    }

    public function test_legacy_structure_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('performance-talent.successor-structure.positions.store'));
        $this->assertFalse(Route::has('performance-talent.successor-structure.positions.update'));
        $this->assertFalse(Route::has('performance-talent.successor-structure.positions.destroy'));
    }

    /** @return array{Department, Unit, Section, Position} */
    private function organization(): array
    {
        $department = Department::query()->create([
            'code' => 'TALENT',
            'name' => 'Talent Department',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'DEV',
            'name' => 'Talent Development Unit',
            'active' => true,
        ]);
        $section = Section::query()->create([
            'unit_id' => $unit->id,
            'code' => 'SUCCESSION',
            'name' => 'Succession Section',
            'active' => true,
        ]);
        $position = Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'TARGET',
            'name' => 'Target Position',
            'job_level' => 'Staff',
            'formation_count' => 3,
            'sort_order' => 1,
            'active' => true,
        ]);

        return [$department, $unit, $section, $position];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
