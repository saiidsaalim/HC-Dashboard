<?php

namespace Tests\Feature;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Successor;
use App\Models\SuccessorStructureDepartment;
use App\Models\SuccessorStructurePosition;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuccessorStructureManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_structure_editor_renders_database_positions(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index'))
            ->assertOk()
            ->assertSee('Edit Struktur')
            ->assertSee('Editor Struktur')
            ->assertSee('Tambah Posisi')
            ->assertSee('Tambah Bawahan')
            ->assertSee('Product, Pricing &amp; Promotion Management', escape: false);

        $this->assertDatabaseCount('successor_structure_departments', 12);
        $this->assertDatabaseCount('successor_structure_positions', 30);
    }

    public function test_structure_position_can_be_created_and_rendered_as_a_child(): void
    {
        $department = SuccessorStructureDepartment::query()->where('slug', 'internal-audit')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successor-structure.positions.store'), $this->validData($department, [
                'level' => 'General Manager',
                'name' => 'Internal Audit General Manager',
            ]))
            ->assertRedirect(route('performance-talent.successors.index', [
                'department' => 'internal-audit',
                'structure_editor' => 1,
            ]))
            ->assertSessionHas('status', 'Posisi struktur berhasil ditambahkan.');

        $root = SuccessorStructurePosition::query()->where('key', 'internal-audit.internal-audit-general-manager')->sole();

        $this->post(route('performance-talent.successor-structure.positions.store'), $this->validData($department, [
            'parent_id' => $root->id,
            'level' => 'Senior Manager',
            'name' => 'Audit Assurance Management',
            'formation_count' => 2,
        ]))->assertRedirect();

        $child = SuccessorStructurePosition::query()->where('key', 'internal-audit.audit-assurance-management')->sole();

        $this->assertSame($root->id, $child->parent_id);
        $this->get(route('performance-talent.successors.index', ['department' => 'internal-audit']))
            ->assertOk()
            ->assertSee('Internal Audit General Manager')
            ->assertSee('Audit Assurance Management');
    }

    public function test_structure_position_can_be_updated_without_changing_its_key(): void
    {
        $position = SuccessorStructurePosition::query()
            ->where('key', 'market-planning-development.product-advocacy-staff')
            ->firstOrFail();
        $originalKey = $position->key;
        Successor::factory()->create(['structure_position_key' => $originalKey]);

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.successor-structure.positions.update', $position), [
                'structure_position_id' => $position->id,
                'department_id' => $position->department_id,
                'parent_id' => $position->parent_id,
                'level' => 'Senior Staff',
                'name' => 'Product Advocacy Specialist',
                'formation_count' => 2,
                'sort_order' => 5,
                'align_with_grandchildren' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Posisi struktur berhasil diperbarui.');

        $position->refresh();

        $this->assertSame($originalKey, $position->key);
        $this->assertSame('Product Advocacy Specialist', $position->name);
        $this->assertSame(2, $position->formation_count);
        $this->assertDatabaseHas('successors', ['structure_position_key' => $originalKey]);
    }

    public function test_structure_rejects_a_second_root_and_circular_parent(): void
    {
        $department = SuccessorStructureDepartment::query()
            ->where('slug', 'market-planning-development')
            ->firstOrFail();
        $root = SuccessorStructurePosition::query()
            ->where('key', 'market-planning-development.general-manager')
            ->firstOrFail();
        $child = SuccessorStructurePosition::query()
            ->where('parent_id', $root->id)
            ->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->from(route('performance-talent.successors.index'))
            ->post(route('performance-talent.successor-structure.positions.store'), $this->validData($department, [
                'name' => 'Another Root',
            ]))
            ->assertSessionHasErrorsIn('createStructurePosition', ['parent_id']);

        $this->from(route('performance-talent.successors.index'))
            ->put(route('performance-talent.successor-structure.positions.update', $root), [
                'structure_position_id' => $root->id,
                'department_id' => $department->id,
                'parent_id' => $child->id,
                'level' => $root->level,
                'name' => $root->name,
                'formation_count' => $root->formation_count,
                'sort_order' => $root->sort_order,
                'align_with_grandchildren' => false,
            ])
            ->assertSessionHasErrorsIn('updateStructurePosition', ['parent_id']);
    }

    public function test_structure_position_with_children_or_successors_cannot_be_deleted(): void
    {
        $root = SuccessorStructurePosition::query()
            ->where('key', 'market-planning-development.general-manager')
            ->firstOrFail();
        $leaf = SuccessorStructurePosition::query()
            ->where('key', 'market-planning-development.product-advocacy-staff')
            ->firstOrFail();
        Successor::factory()->create(['structure_position_key' => $leaf->key]);

        $this->actingAs($this->superAdmin())
            ->delete(route('performance-talent.successor-structure.positions.destroy', $root))
            ->assertSessionHasErrorsIn('structurePosition', ['structure_position']);

        $this->delete(route('performance-talent.successor-structure.positions.destroy', $leaf))
            ->assertSessionHasErrorsIn('structurePosition', ['structure_position']);

        $this->assertDatabaseHas('successor_structure_positions', ['id' => $root->id]);
        $this->assertDatabaseHas('successor_structure_positions', ['id' => $leaf->id]);
    }

    public function test_unused_leaf_structure_position_can_be_deleted(): void
    {
        $leaf = SuccessorStructurePosition::query()
            ->where('key', 'market-planning-development.product-advocacy-staff')
            ->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->delete(route('performance-talent.successor-structure.positions.destroy', $leaf))
            ->assertRedirect()
            ->assertSessionHas('status', 'Posisi struktur berhasil dihapus.');

        $this->assertDatabaseMissing('successor_structure_positions', ['id' => $leaf->id]);
    }

    public function test_user_without_successor_update_permission_cannot_manage_structure(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $department = SuccessorStructureDepartment::query()->where('slug', 'sales')->firstOrFail();
        $position = SuccessorStructurePosition::query()->firstOrFail();
        $this->grantPermissionsToRole(UserRole::ADMIN, [
            'workspace.performance-talent-management.view',
            'successors.view',
        ]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, [
            'hc-od-career-management',
            'performance-talent-management',
        ]);

        $this->actingAs($admin)
            ->get(route('performance-talent.successors.index'))
            ->assertOk()
            ->assertDontSee('Edit Struktur');

        $this->post(route('performance-talent.successor-structure.positions.store'), $this->validData($department))
            ->assertForbidden();
        $this->put(route('performance-talent.successor-structure.positions.update', $position), [
            ...$this->validData($position->department, ['parent_id' => $position->parent_id]),
            'structure_position_id' => $position->id,
        ])->assertForbidden();
        $this->delete(route('performance-talent.successor-structure.positions.destroy', $position))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(SuccessorStructureDepartment $department, array $overrides = []): array
    {
        return array_merge([
            'department_id' => $department->id,
            'parent_id' => null,
            'level' => 'Manager',
            'name' => 'Sales Management',
            'formation_count' => 1,
            'sort_order' => 1,
            'align_with_grandchildren' => false,
        ], $overrides);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    /** @param array<int, string> $permissionKeys */
    private function grantPermissionsToRole(UserRole $role, array $permissionKeys): void
    {
        $now = now();
        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id', 'key');

        DB::table('permission_role')->insert(array_map(
            fn (string $permissionKey): array => [
                'permission_id' => $permissionIds->get($permissionKey),
                'role' => $role->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $permissionKeys,
        ));

        app(RolePermissionCache::class)->clear();
    }
}
