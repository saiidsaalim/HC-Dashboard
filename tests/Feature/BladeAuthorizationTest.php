<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BladeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_employee_create_controls(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)->get(route('data-pegawai'))
            ->assertOk()
            ->assertSee('Tambah Pegawai')
            ->assertSee('employee-modal-title', escape: false)
            ->assertSee('Simpan Data Pegawai');
    }

    #[DataProvider('rolesWithoutEmployeeManagement')]
    public function test_non_super_admin_does_not_see_employee_create_controls(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role->value]);

        $this->actingAs($user)->get(route('data-pegawai'))
            ->assertOk()
            ->assertDontSee('Tambah Pegawai')
            ->assertDontSee('employee-modal-title', escape: false)
            ->assertDontSee('Simpan Data Pegawai');
    }

    #[DataProvider('wlaManagementRoles')]
    public function test_wla_management_role_sees_wla_navigation(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role->value]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('title="WLA"', escape: false);
    }

    #[DataProvider('rolesWithoutWlaAccess')]
    public function test_non_management_role_does_not_see_wla_navigation(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role->value]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('title="WLA"', escape: false);
    }

    #[DataProvider('userManagersWithoutEditAccess')]
    public function test_admin_and_manager_do_not_receive_user_edit_modals(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role->value]);
        $managedUser = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($actor)->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertDontSee('id="user-edit-'.$managedUser->id.'"', escape: false);
    }

    public function test_super_admin_receives_user_edit_modals(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $managedUser = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($superAdmin)->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('id="user-edit-'.$managedUser->id.'"', escape: false);
    }

    /** @return array<string, array{UserRole}> */
    public static function rolesWithoutEmployeeManagement(): array
    {
        return [
            'admin' => [UserRole::ADMIN],
            'manager' => [UserRole::MANAGER],
            'staff' => [UserRole::STAFF],
            'member' => [UserRole::MEMBER],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function wlaManagementRoles(): array
    {
        return [
            'super admin' => [UserRole::SUPER_ADMIN],
            'admin' => [UserRole::ADMIN],
            'manager' => [UserRole::MANAGER],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function rolesWithoutWlaAccess(): array
    {
        return [
            'staff' => [UserRole::STAFF],
            'member' => [UserRole::MEMBER],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function userManagersWithoutEditAccess(): array
    {
        return [
            'admin' => [UserRole::ADMIN],
            'manager' => [UserRole::MANAGER],
        ];
    }

    /**
     * Prepare the in-memory test database without invoking migrate:fresh.
     */
    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }
}
