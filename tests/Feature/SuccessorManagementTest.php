<?php

namespace Tests\Feature;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Successor;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuccessorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Position $targetPosition;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $department = Department::query()->create([
            'code' => 'COMMERCIAL',
            'name' => 'Commercial Department',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'STRATEGY',
            'name' => 'Strategy Unit',
            'active' => true,
        ]);
        $this->targetPosition = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'TARGET',
            'name' => 'Commercial General Manager',
            'job_level' => 'General Manager',
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
        $currentPosition = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'CURRENT',
            'name' => 'Senior Manager Strategy',
            'job_level' => 'Senior Manager',
            'formation_count' => 1,
            'sort_order' => 2,
            'active' => true,
        ]);
        $this->employee = Employee::query()->forceCreate([
            'sap' => 'SAP-001',
            'name' => 'Andi Pratama',
            'position' => $currentPosition->name,
            'education' => 'S2 Manajemen',
            'hiring_date' => now()->subYears(12),
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $currentPosition->id,
            'directorate' => 'Commercial Directorate',
        ]);
    }

    public function test_successor_view_renders_employee_and_master_position_data(): void
    {
        $successor = $this->createSuccessor(['photo_path' => 'successors/andi-pratama.jpg']);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertOk()
            ->assertSee('aria-label="Lihat successor untuk Commercial General Manager"', escape: false)
            ->assertSee('Andi Pratama')
            ->assertSee('Senior Manager Strategy')
            ->assertSee('S2 Manajemen')
            ->assertSee('12 tahun')
            ->assertSee('Commercial Directorate')
            ->assertSee('storage/successors/andi-pratama.jpg', escape: false)
            ->assertSee(route('performance-talent.successors.update', $successor), escape: false);
    }

    public function test_successor_can_be_created_from_employee_and_position_master(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil ditambahkan.');

        $this->assertDatabaseHas('successors', [
            'employee_id' => $this->employee->id,
            'target_position_id' => $this->targetPosition->id,
            'name' => $this->employee->name,
            'position' => $this->employee->position,
            'education' => $this->employee->education,
        ]);
    }

    public function test_same_employee_cannot_be_nominated_twice_for_the_same_position(): void
    {
        $this->createSuccessor();

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertSessionHasErrorsIn('createSuccessor', ['employee_id']);

        $this->assertDatabaseCount('successors', 1);
    }

    public function test_successor_photo_can_be_uploaded_replaced_and_deleted(): void
    {
        Storage::fake('public');
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData([
                'photo' => UploadedFile::fake()->image('candidate-one.jpg'),
            ]))
            ->assertRedirect();

        $successor = Successor::query()->sole();
        $firstPhotoPath = $successor->photo_path;
        Storage::disk('public')->assertExists($firstPhotoPath);

        $this->put(route('performance-talent.successors.update', $successor), $this->validData([
            'successor_id' => $successor->id,
            'photo' => UploadedFile::fake()->image('candidate-two.webp'),
        ]))->assertRedirect();

        $successor->refresh();
        Storage::disk('public')->assertMissing($firstPhotoPath);
        Storage::disk('public')->assertExists($successor->photo_path);

        $this->delete(route('performance-talent.successors.destroy', $successor))->assertRedirect();
        Storage::disk('public')->assertMissing($successor->photo_path);
    }

    public function test_successor_requires_known_employee_position_and_talent_data(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), [
                'target_position_id' => 999999,
                'employee_id' => 999999,
            ])
            ->assertSessionHasErrorsIn('createSuccessor', [
                'target_position_id',
                'employee_id',
                'talent_mapping',
                'succession_readiness',
                'idp',
            ]);
    }

    public function test_user_with_view_only_permission_cannot_manage_successors(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $successor = $this->createSuccessor();
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
            ->assertDontSee('Tambah Successor');

        $this->post(route('performance-talent.successors.store'), $this->validData())->assertForbidden();
        $this->put(route('performance-talent.successors.update', $successor), $this->validData([
            'successor_id' => $successor->id,
        ]))->assertForbidden();
        $this->delete(route('performance-talent.successors.destroy', $successor))->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function createSuccessor(array $overrides = []): Successor
    {
        return Successor::query()->create(array_merge([
            ...$this->validData(),
            'name' => $this->employee->name,
            'position' => $this->employee->position,
            'education' => $this->employee->education,
            'tenure' => '12 tahun',
            'directorate' => $this->employee->directorate,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'target_position_id' => $this->targetPosition->id,
            'employee_id' => $this->employee->id,
            'talent_mapping' => 'High Potential',
            'succession_readiness' => 'Ready Now',
            'idp' => 'Executive leadership program.',
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
        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id', 'key');

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
