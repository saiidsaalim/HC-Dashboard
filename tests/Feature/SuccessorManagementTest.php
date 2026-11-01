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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuccessorManagementTest extends TestCase
{
    use RefreshDatabase;

    private Position $targetPosition;

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
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    public function test_successor_popup_renders_manual_profile_for_selected_target_position(): void
    {
        $successor = $this->createSuccessor(['photo_path' => 'successors/andi-pratama.jpg']);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertOk()
            ->assertSee('aria-label="Lihat successor untuk Commercial General Manager"', escape: false)
            ->assertSee('Andi Pratama')
            ->assertSee('Manager Human Capital Planning')
            ->assertSee('S2 Manajemen')
            ->assertSee('12 tahun')
            ->assertSee('Human Capital Directorate')
            ->assertSee('IDP: 75%')
            ->assertSee('storage/successors/andi-pratama.jpg', escape: false)
            ->assertSee(route('performance-talent.successors.update', $successor), escape: false);
    }

    public function test_successor_can_be_created_without_employee_using_manual_profile_fields(): void
    {
        $this->assertDatabaseCount('employees', 0);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil ditambahkan.');

        $this->assertDatabaseHas('successors', $this->validData());
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_successor_schema_has_no_employee_or_legacy_structure_dependency(): void
    {
        $this->assertTrue(Schema::hasColumn('successors', 'target_position_id'));
        $this->assertFalse(Schema::hasColumn('successors', 'employee_id'));
        $this->assertFalse(Schema::hasTable('successor_structure_departments'));
        $this->assertFalse(Schema::hasTable('successor_structure_positions'));
    }

    public function test_invalid_target_position_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData([
                'target_position_id' => 999999,
            ]))
            ->assertSessionHasErrorsIn('createSuccessor', ['target_position_id']);

        $this->assertDatabaseCount('successors', 0);
    }

    public function test_manual_profile_and_idp_percentage_are_required_and_validated(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), [
                'target_position_id' => $this->targetPosition->id,
                'idp' => 101,
            ])
            ->assertSessionHasErrorsIn('createSuccessor', [
                'name',
                'position',
                'talent_mapping',
                'succession_readiness',
                'idp',
                'education',
                'tenure',
                'directorate',
            ]);
    }

    public function test_same_manual_candidate_name_can_target_different_positions(): void
    {
        $secondTargetPosition = $this->createTargetPosition('TARGET-2', 'Senior Manager Commercial', 2);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect();

        $this->post(route('performance-talent.successors.store'), $this->validData([
            'target_position_id' => $secondTargetPosition->id,
        ]))->assertRedirect();

        $this->assertDatabaseCount('successors', 2);
        $this->assertSame(
            [$this->targetPosition->id, $secondTargetPosition->id],
            Successor::query()->orderBy('target_position_id')->pluck('target_position_id')->all(),
        );
    }

    public function test_successor_can_be_updated_with_manual_data_and_target_position(): void
    {
        $successor = $this->createSuccessor();
        $secondTargetPosition = $this->createTargetPosition('TARGET-2', 'Senior Manager Commercial', 2);

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.successors.update', $successor), $this->validData([
                'successor_id' => $successor->id,
                'target_position_id' => $secondTargetPosition->id,
                'name' => 'Budi Santoso',
                'position' => 'Manager Strategy',
                'talent_mapping' => 'Key Contributor',
                'succession_readiness' => '1-2 Tahun',
                'idp' => 80,
                'education' => 'S1 Teknik Industri',
                'tenure' => '8 tahun',
                'directorate' => 'Commercial Directorate',
            ]))
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $secondTargetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil diperbarui.');

        $this->assertDatabaseHas('successors', [
            'id' => $successor->id,
            'target_position_id' => $secondTargetPosition->id,
            'name' => 'Budi Santoso',
            'position' => 'Manager Strategy',
            'talent_mapping' => 'Key Contributor',
            'succession_readiness' => '1-2 Tahun',
            'idp' => 80,
            'education' => 'S1 Teknik Industri',
            'tenure' => '8 tahun',
            'directorate' => 'Commercial Directorate',
        ]);
    }

    public function test_creating_successor_does_not_mutate_employee_or_target_position(): void
    {
        $employee = $this->createUnrelatedEmployee();
        $employeeAttributes = $employee->refresh()->getAttributes();
        $positionAttributes = $this->targetPosition->refresh()->getAttributes();

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect();

        $this->assertSame($employeeAttributes, $employee->refresh()->getAttributes());
        $this->assertSame($positionAttributes, $this->targetPosition->refresh()->getAttributes());
    }

    public function test_editing_successor_does_not_mutate_employee_or_target_position(): void
    {
        $employee = $this->createUnrelatedEmployee();
        $successor = $this->createSuccessor();
        $employeeAttributes = $employee->refresh()->getAttributes();
        $positionAttributes = $this->targetPosition->refresh()->getAttributes();

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.successors.update', $successor), $this->validData([
                'successor_id' => $successor->id,
                'name' => 'Updated Manual Name',
            ]))
            ->assertRedirect();

        $this->assertSame($employeeAttributes, $employee->refresh()->getAttributes());
        $this->assertSame($positionAttributes, $this->targetPosition->refresh()->getAttributes());
    }

    public function test_deleting_successor_does_not_delete_employee_or_target_position(): void
    {
        $employee = $this->createUnrelatedEmployee();
        $successor = $this->createSuccessor();

        $this->actingAs($this->superAdmin())
            ->delete(route('performance-talent.successors.destroy', $successor))
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil dihapus.');

        $this->assertDatabaseMissing('successors', ['id' => $successor->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('positions', ['id' => $this->targetPosition->id]);
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
        return Successor::query()->create(array_merge($this->validData(), $overrides));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'target_position_id' => $this->targetPosition->id,
            'name' => 'Andi Pratama',
            'position' => 'Manager Human Capital Planning',
            'talent_mapping' => 'High Potential',
            'succession_readiness' => 'Ready Now',
            'idp' => 75,
            'education' => 'S2 Manajemen',
            'tenure' => '12 tahun',
            'directorate' => 'Human Capital Directorate',
        ], $overrides);
    }

    private function createTargetPosition(string $code, string $name, int $sortOrder): Position
    {
        return Position::query()->create([
            'unit_id' => $this->targetPosition->unit_id,
            'code' => $code,
            'name' => $name,
            'formation_count' => 1,
            'sort_order' => $sortOrder,
            'active' => true,
        ]);
    }

    private function createUnrelatedEmployee(): Employee
    {
        return Employee::query()->forceCreate([
            'sap' => 'SAP-UNRELATED',
            'name' => 'Unrelated Employee',
            'position_id' => $this->targetPosition->id,
            'unit_id' => $this->targetPosition->unit_id,
            'department_id' => $this->targetPosition->unit->department_id,
        ]);
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
