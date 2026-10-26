<?php

namespace Tests\Feature;

use App\Enums\CriticalPositionCriticality;
use App\Enums\CriticalPositionStatus;
use App\Enums\UserRole;
use App\Models\CriticalPosition;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CriticalPositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_authorized_user_can_view_the_index_and_empty_state(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('Daftar Critical Position')
            ->assertSee('Belum ada Critical Position')
            ->assertSee('Tambah Critical Position')
            ->assertSee('colspan="10"', escape: false)
            ->assertDontSee('Level Jabatan')
            ->assertDontSee('job_level')
            ->assertSee('table-fixed', escape: false)
            ->assertSee('min-w-[1000px]', escape: false)
            ->assertSee('overflow-x-auto', escape: false)
            ->assertDontSee('min-w-[1480px]', escape: false);
    }

    public function test_authorized_user_can_open_create_and_edit_forms_in_left_side_panels(): void
    {
        $criticalPosition = CriticalPosition::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('Tambah Critical Position')
            ->assertSee('Ubah Critical Position')
            ->assertSee('name="position"', escape: false)
            ->assertSee('name="criticality"', escape: false)
            ->assertSee('name="status"', escape: false)
            ->assertSee('Incumbent (Personal Number)')
            ->assertSee('name="incumbent"', escape: false)
            ->assertSee('placeholder="Masukkan personal number incumbent"', escape: false)
            ->assertSee('placeholder="Masukkan data SAP"', escape: false)
            ->assertDontSee('SAP / Personal Number')
            ->assertDontSee('name="incumbent_name"', escape: false)
            ->assertDontSee('name="job_level"', escape: false)
            ->assertSee('inline-flex items-center justify-center gap-2 whitespace-nowrap', escape: false)
            ->assertSee("x-show=\"panel === 'create'\"", escape: false)
            ->assertSee("x-show=\"panel === 'edit'\"", escape: false)
            ->assertSee('x-transition:enter-start="-translate-x-full"', escape: false)
            ->assertSee('action="'.route('performance-talent.critical-positions.store').'"', escape: false)
            ->assertSee(route('performance-talent.critical-positions.update', $criticalPosition), escape: false);

        $this->assertFalse(Route::has('performance-talent.critical-positions.create'));
        $this->assertFalse(Route::has('performance-talent.critical-positions.edit'));
    }

    public function test_incumbent_personal_number_and_sap_are_stored_and_rendered_separately(): void
    {
        $data = $this->validData([
            'position' => 'Head of Digital Strategy',
            'incumbent' => '00004567',
            'sap' => 'SAP-A12-34',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.critical-positions.store'), $data)
            ->assertRedirect(route('performance-talent.critical-positions.index'))
            ->assertSessionHas('status', 'Critical Position berhasil ditambahkan.');

        $criticalPosition = CriticalPosition::query()->sole();

        $this->assertSame('00004567', $criticalPosition->incumbent);
        $this->assertSame('SAP-A12-34', $criticalPosition->sap);
        $this->assertSame(CriticalPositionCriticality::High, $criticalPosition->criticality);
        $this->assertSame(CriticalPositionStatus::Covered, $criticalPosition->status);
        $this->assertTrue(Schema::hasColumn('critical_positions', 'incumbent'));
        $this->assertFalse(Schema::hasColumn('critical_positions', 'incumbent_name'));
        $this->assertFalse(Schema::hasColumn('critical_positions', 'job_level'));
        $this->assertDatabaseHas('critical_positions', [
            'position' => 'Head of Digital Strategy',
            'incumbent' => '00004567',
            'sap' => 'SAP-A12-34',
        ]);

        $this->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('Head of Digital Strategy')
            ->assertSee('00004567')
            ->assertSee('SAP-A12-34')
            ->assertSee('High')
            ->assertSee('Covered');
    }

    public function test_critical_position_can_be_updated(): void
    {
        $criticalPosition = CriticalPosition::factory()->create();

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.critical-positions.update', $criticalPosition), $this->validData([
                'position' => 'Updated Critical Position',
                'criticality' => CriticalPositionCriticality::Medium->value,
                'status' => CriticalPositionStatus::Vacant->value,
                'incumbent' => '00007890',
                'sap' => 'SAP-UPDATED',
            ]))
            ->assertRedirect(route('performance-talent.critical-positions.index'))
            ->assertSessionHas('status', 'Critical Position berhasil diperbarui.');

        $this->assertDatabaseHas('critical_positions', [
            'id' => $criticalPosition->id,
            'position' => 'Updated Critical Position',
            'criticality' => CriticalPositionCriticality::Medium->value,
            'status' => CriticalPositionStatus::Vacant->value,
            'incumbent' => '00007890',
            'sap' => 'SAP-UPDATED',
        ]);
    }

    public function test_critical_position_can_be_deleted_through_the_confirmed_delete_form(): void
    {
        $criticalPosition = CriticalPosition::factory()->create();
        $this->actingAs($this->superAdmin());

        $this->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('action="'.route('performance-talent.critical-positions.destroy', $criticalPosition).'"', escape: false)
            ->assertSee('data-confirm-submit', escape: false)
            ->assertSee('data-confirm-title="Hapus Critical Position ini?"', escape: false)
            ->assertSee('name="_method" value="DELETE"', escape: false)
            ->assertDontSee('confirm(', escape: false);

        $this->delete(route('performance-talent.critical-positions.destroy', $criticalPosition))
            ->assertRedirect(route('performance-talent.critical-positions.index'))
            ->assertSessionHas('status', 'Critical Position berhasil dihapus.');

        $this->assertDatabaseMissing('critical_positions', ['id' => $criticalPosition->id]);
    }

    public function test_required_fields_are_validated(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->followingRedirects()
            ->from(route('performance-talent.critical-positions.index'))
            ->post(route('performance-talent.critical-positions.store'), []);

        $response
            ->assertOk()
            ->assertSee("panel: 'create'", escape: false)
            ->assertSee('Periksa kembali data berikut:')
            ->assertSeeText('The position field is required.')
            ->assertSeeText('The status field is required.');
    }

    public function test_update_validation_reopens_the_edit_side_panel(): void
    {
        $criticalPosition = CriticalPosition::factory()->create();

        $response = $this->actingAs($this->superAdmin())
            ->followingRedirects()
            ->from(route('performance-talent.critical-positions.index'))
            ->put(route('performance-talent.critical-positions.update', $criticalPosition), $this->validData([
                'critical_position_id' => (string) $criticalPosition->id,
                'position' => '',
            ]));

        $response
            ->assertOk()
            ->assertSee("panel: 'edit'", escape: false)
            ->assertSee('Periksa kembali data berikut:')
            ->assertSeeText('The position field is required.');
    }

    public function test_invalid_criticality_and_status_are_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('performance-talent.critical-positions.index'))
            ->post(route('performance-talent.critical-positions.store'), $this->validData([
                'criticality' => 'Urgent',
                'status' => 'Unknown',
            ]))
            ->assertRedirect(route('performance-talent.critical-positions.index'))
            ->assertSessionHasErrorsIn('createCriticalPosition', ['criticality', 'status']);

        $this->assertDatabaseCount('critical_positions', 0);
    }

    public function test_pagination_keeps_the_row_number_on_the_second_page(): void
    {
        foreach (range(1, 16) as $number) {
            CriticalPosition::factory()->create([
                'position' => sprintf('Position %02d', $number),
            ]);
        }

        $response = $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Position 16');

        $this->assertMatchesRegularExpression('/>\s*16\s*<\/td>/', $response->getContent());
    }

    public function test_user_with_view_permission_cannot_access_management_urls_directly(): void
    {
        $criticalPosition = CriticalPosition::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->grantPermissions($admin->roleEnum(), [
            'workspace.performance-talent-management.view',
            'critical-positions.view',
        ]);

        $this->actingAs($admin)
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertDontSee('Tambah Critical Position')
            ->assertDontSee('>Edit</button>', escape: false)
            ->assertDontSee('>Hapus</button>', escape: false);

        $this->post(route('performance-talent.critical-positions.store'), $this->validData())->assertForbidden();
        $this->put(route('performance-talent.critical-positions.update', $criticalPosition), $this->validData())->assertForbidden();
        $this->delete(route('performance-talent.critical-positions.destroy', $criticalPosition))->assertForbidden();

        $this->assertDatabaseHas('critical_positions', ['id' => $criticalPosition->id]);
    }

    public function test_workspace_permission_is_still_required_for_direct_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->grantPermissions($admin->roleEnum(), ['critical-positions.view']);

        $this->actingAs($admin)
            ->get(route('performance-talent.critical-positions.index'))
            ->assertForbidden();
    }

    public function test_crud_does_not_change_employee_data_or_create_an_employee_relationship(): void
    {
        $employee = Employee::query()->create([
            'sap' => '00123456',
            'personal_number' => 'PN-EXISTING',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.critical-positions.store'), $this->validData([
                'incumbent' => '00001234',
                'sap' => '00123456',
            ]))
            ->assertRedirect(route('performance-talent.critical-positions.index'));

        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'sap' => '00123456',
            'personal_number' => 'PN-EXISTING',
        ]);
        $this->assertDatabaseHas('critical_positions', [
            'incumbent' => '00001234',
            'sap' => '00123456',
        ]);
        $this->assertFalse(Schema::hasColumn('critical_positions', 'employee_id'));
    }

    /** @param array<string, mixed> $overrides */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'position' => 'Manager Business Continuity',
            'level' => 'Division',
            'department' => 'Risk Management',
            'directorate' => 'Direktorat Risk & Compliance',
            'criticality' => CriticalPositionCriticality::High->value,
            'incumbent' => '00005678',
            'sap' => 'SAP-00001234',
            'status' => CriticalPositionStatus::Covered->value,
        ], $overrides);
    }

    /** @param array<int, string> $permissionKeys */
    private function grantPermissions(UserRole $role, array $permissionKeys): void
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

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
