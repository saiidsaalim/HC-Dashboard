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
use Illuminate\Support\Facades\DB;
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
        $generalManager = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'GM-TALENT',
            'name' => 'General Manager of Talent',
            'formation_count' => 1,
            'sort_order' => 0,
            'active' => true,
        ]);
        $generalManager->forceFill(['job_level' => Position::JOB_LEVEL_GENERAL_MANAGER])->save();
        $seniorManager = Position::query()->create([
            'unit_id' => $unit->id,
            'parent_position_id' => $generalManager->id,
            'code' => 'SM-TALENT',
            'name' => 'Senior Manager of Talent Development',
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
        $seniorManager->forceFill(['job_level' => Position::JOB_LEVEL_SENIOR_MANAGER])->save();
        $manager = Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'parent_position_id' => $seniorManager->id,
            'code' => 'MGR-TALENT',
            'name' => 'Manager of Talent Performance',
            'formation_count' => 1,
            'sort_order' => 2,
            'active' => true,
        ]);
        $manager->forceFill(['job_level' => Position::JOB_LEVEL_MANAGER])->save();
        $position->update(['parent_position_id' => $manager->id]);
        $secondStaff = Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'parent_position_id' => $manager->id,
            'code' => 'STAFF-TALENT-2',
            'name' => 'Talent Management Officer',
            'formation_count' => 1,
            'sort_order' => 4,
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
            'formation_count' => 9,
            'sort_order' => 2,
            'active' => false,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertViewHas('successorStructures', function (array $structures) use ($department, $position, $secondStaff): bool {
                $generalManager = $structures[$department->name][0];
                $seniorManager = $generalManager['children'][0];
                $manager = $seniorManager['children'][0];

                return $generalManager['level'] === 'General Manager'
                    && $seniorManager['level'] === 'Senior Manager'
                    && $manager['level'] === 'Manager'
                    && array_column($manager['children'], 'key') === [(string) $position->id, (string) $secondStaff->id];
            })
            ->assertSee($department->name)
            ->assertSeeInOrder([
                'General Manager of Talent',
                'Senior Manager of Talent Development',
                'Manager of Talent Performance',
                $position->name,
                $secondStaff->name,
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

    public function test_staff_falls_directly_under_general_manager_when_intermediate_levels_are_missing(): void
    {
        $department = Department::query()->create([
            'code' => 'PROJECT-SUPPORT',
            'name' => 'Project Management & Maint. Support',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'TPM',
            'name' => 'TPM Unit',
            'active' => true,
        ]);
        $generalManager = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'GM-PROJECT',
            'name' => 'GM of Project Mngmt & Maint. Support',
            'sort_order' => 1,
            'active' => true,
        ]);
        $generalManager->forceFill(['job_level' => Position::JOB_LEVEL_GENERAL_MANAGER])->save();
        $firstStaff = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'C-TPM',
            'name' => 'C TPM Officer',
            'sort_order' => 2,
            'active' => true,
        ]);
        $secondStaff = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'TPM-OFFICER',
            'name' => 'TPM Officer',
            'sort_order' => 3,
            'active' => true,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertViewHas('successorStructures', function (array $structures) use ($department, $generalManager, $firstStaff, $secondStaff): bool {
                $root = $structures[$department->name][0];

                return $root['key'] === (string) $generalManager->id
                    && $root['level'] === Position::JOB_LEVEL_GENERAL_MANAGER
                    && array_column($root['children'], 'key') === [(string) $firstStaff->id, (string) $secondStaff->id]
                    && collect($root['children'])->every(
                        fn (array $child): bool => $child['level'] === Position::JOB_LEVEL_STAFF,
                    );
            })
            ->assertSeeInOrder([
                $generalManager->name,
                $firstStaff->name,
                $secondStaff->name,
            ]);
    }

    public function test_changes_in_master_position_are_reflected_in_successor_view(): void
    {
        [$department, , , $position] = $this->organization();
        $position->forceFill(['job_level' => Position::JOB_LEVEL_MANAGER])->save();

        $position->update([
            'name' => 'Senior Manager Updated Target Position',
            'formation_count' => 7,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertSee('Senior Manager Updated Target Position')
            ->assertViewHas('successorStructures', function (array $structures) use ($department): bool {
                return $structures[$department->name][0]['level'] === Position::JOB_LEVEL_MANAGER;
            })
            ->assertSee('Formasi')
            ->assertSee('>7</strong>', escape: false)
            ->assertSee('Terisi');
    }

    public function test_unknown_stored_job_level_falls_back_to_staff_in_successor_view(): void
    {
        [$department, , , $position] = $this->organization();
        DB::table('positions')->where('id', $position->id)->update(['job_level' => 'Supervisor']);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertViewHas('successorStructures', function (array $structures) use ($department, $position): bool {
                $node = $structures[$department->name][0];

                return $node['key'] === (string) $position->id
                    && $node['level'] === Position::JOB_LEVEL_STAFF;
            });
    }

    public function test_master_position_with_successors_cannot_be_deleted(): void
    {
        [, , , $targetPosition] = $this->organization();
        Successor::query()->create([
            'target_position_id' => $targetPosition->id,
            'name' => 'Manual Successor',
            'position' => 'Current Position',
            'talent_mapping' => 'High Potential',
            'succession_readiness' => 'Ready Now',
            'idp' => 60,
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
            'name' => 'Supervisor Talent Operations',
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
