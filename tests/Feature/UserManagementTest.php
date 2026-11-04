<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeePromotion;
use App\Models\PromotionApproval;
use App\Models\PromotionApprovalConfig;
use App\Models\User;
use App\Services\Users\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_identity_is_used_when_creating_a_linked_staff_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::create([
            'sap' => 'SAP-ACCOUNT-001',
            'personal_number' => 'PN-ACCOUNT-001',
            'email' => 'employee@example.com',
        ]);

        $this->actingAs($admin)->post(route('konfigurasi-user.store'), [
            'name' => 'Nama dari Request',
            'email' => 'request@example.com',
            'password' => 'password123',
            'role' => UserRole::MEMBER->value,
            'employee_id' => $employee->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'PN-ACCOUNT-001',
            'email' => 'employee@example.com',
            'role' => UserRole::STAFF->value,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_employee_identity_is_used_when_linking_an_existing_staff_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $user = User::factory()->create(['role' => UserRole::STAFF->value]);
        $employee = Employee::create([
            'sap' => 'SAP-ACCOUNT-002',
            'personal_number' => 'PN-ACCOUNT-002',
            'email' => 'linked@example.com',
        ]);

        $this->actingAs($admin)->put(route('konfigurasi-user.update', $user), [
            'name' => 'Nama yang Diabaikan',
            'email' => 'ignored@example.com',
            'password' => '',
            'employee_id' => $employee->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'PN-ACCOUNT-002',
            'email' => 'linked@example.com',
            'role' => UserRole::STAFF->value,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_super_admin_can_edit_a_managed_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $user = User::factory()->create(['role' => UserRole::STAFF->value]);

        $response = $this->actingAs($admin)->put(route('konfigurasi-user.update', $user), [
            'name' => 'Nama Diperbarui',
            'email' => 'updated@example.com',
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect()->assertSessionHas('status', 'Data pengguna berhasil diperbarui.');
        $updatedUser = $user->fresh();

        $this->assertSame('Nama Diperbarui', $updatedUser->name);
        $this->assertSame('updated@example.com', $updatedUser->email);
        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));
    }

    public function test_super_admin_can_delete_a_managed_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $user = User::factory()->create(['role' => UserRole::STAFF->value]);

        $response = $this->actingAs($admin)->delete(route('konfigurasi-user.destroy', $user));

        $response->assertRedirect();
        $this->assertNull($user->fresh());
    }

    public function test_configured_approver_cannot_be_deleted_or_downgraded(): void
    {
        $approver = User::factory()->create(['role' => UserRole::MANAGER->value]);
        PromotionApprovalConfig::query()->create(['step_order' => 1, 'user_id' => $approver->id]);
        $service = app(UserAccountService::class);

        foreach ([
            fn () => $service->delete($approver),
            fn () => $service->changeRole($approver, UserRole::STAFF),
        ] as $action) {
            try {
                $action();
                $this->fail('Approver konfigurasi seharusnya dilindungi.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Promosi', $exception->getMessage());
                $this->assertStringContainsString('langkah 1', $exception->getMessage());
            }
        }

        $this->assertSame(UserRole::MANAGER->value, $approver->fresh()->role);
    }

    public function test_current_approver_cannot_be_deleted_but_historical_approver_can(): void
    {
        $activeApprover = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $activeMovement = $this->promotionForApproverTest('ACTIVE', 'Waiting Approval', 1);
        PromotionApproval::query()->create([
            'employee_promotion_id' => $activeMovement->id,
            'approval_round' => 1,
            'step_order' => 1,
            'manager_id' => $activeApprover->id,
            'approver_name_snapshot' => $activeApprover->name,
            'approver_role_snapshot' => $activeApprover->role,
            'status' => 'Pending',
        ]);
        $service = app(UserAccountService::class);

        try {
            $service->delete($activeApprover);
            $this->fail('Approver aktif seharusnya tidak dapat dihapus.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('approver aktif Promosi', $exception->getMessage());
        }

        $historicalEmployee = Employee::query()->create(['sap' => 'APPROVER-HISTORICAL']);
        $historicalApprover = User::factory()->create([
            'employee_id' => $historicalEmployee->id,
            'role' => UserRole::MANAGER->value,
        ]);
        $historicalMovement = $this->promotionForApproverTest('HISTORICAL', 'Final', 1);
        $approval = PromotionApproval::query()->create([
            'employee_promotion_id' => $historicalMovement->id,
            'approval_round' => 1,
            'step_order' => 1,
            'manager_id' => $historicalApprover->id,
            'approver_name_snapshot' => $historicalApprover->name,
            'approver_role_snapshot' => $historicalApprover->role,
            'approver_number_snapshot' => $historicalEmployee->sap,
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        $service->delete($historicalApprover);

        $approval->refresh();
        $this->assertNull($approval->manager_id);
        $this->assertSame($historicalApprover->name, $approval->approver_name_snapshot);
        $this->assertSame(UserRole::MANAGER->value, $approval->approver_role_snapshot);
        $this->assertSame('APPROVER-HISTORICAL', $approval->approver_number_snapshot);
    }

    public function test_user_cannot_delete_their_own_account_from_user_management(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($admin)->delete(route('konfigurasi-user.destroy', $admin));

        $response->assertForbidden();
        $this->assertNotNull($admin->fresh());
    }

    public function test_admin_cannot_delete_a_privileged_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);

        $response = $this->actingAs($admin)->delete(route('konfigurasi-user.destroy', $manager));

        $response->assertForbidden();
        $this->assertNotNull($manager->fresh());
    }

    public function test_admin_cannot_edit_user_data_or_change_roles(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $user = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($admin)->put(route('konfigurasi-user.update', $user), [
            'name' => 'Nama Terlarang',
            'email' => $user->email,
            'password' => '',
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('konfigurasi-user.role', $user), [
            'role' => UserRole::MEMBER->value,
        ])->assertForbidden();

        $this->assertSame('Staff', $user->fresh()->role);
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_changing_linked_staff_to_manager_or_admin_preserves_employee_relationship(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        foreach ([UserRole::MANAGER, UserRole::ADMIN] as $newRole) {
            $employee = Employee::create([
                'sap' => 'SAP-'.$newRole->value,
                'personal_number' => 'PN-'.$newRole->value,
                'email' => strtolower($newRole->value).'@example.com',
            ]);
            $user = User::factory()->create([
                'role' => UserRole::STAFF->value,
                'employee_id' => $employee->id,
            ]);

            $this->actingAs($superAdmin)->patch(route('konfigurasi-user.role', $user), [
                'role' => $newRole->value,
            ])->assertRedirect()->assertSessionHas('status', 'Role pengguna berhasil diperbarui.');

            $updatedUser = $user->fresh();
            $this->assertSame($newRole->value, $updatedUser->role);
            $this->assertSame($employee->id, $updatedUser->employee_id);
            $this->assertTrue($updatedUser->employee->is($employee));
        }

        $this->actingAs($superAdmin)->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('PN-Manager')
            ->assertSee('PN-Admin');
    }

    public function test_changing_role_does_not_create_an_employee_relationship_for_an_unlinked_user(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $user = User::factory()->create([
            'role' => UserRole::STAFF->value,
            'employee_id' => null,
        ]);

        $this->actingAs($superAdmin)->patch(route('konfigurasi-user.role', $user), [
            'role' => UserRole::MANAGER->value,
        ])->assertRedirect();

        $this->assertSame(UserRole::MANAGER->value, $user->fresh()->role);
        $this->assertNull($user->fresh()->employee_id);
    }

    public function test_manager_cannot_change_roles_and_super_admin_cannot_demote_self(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $user = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($manager)->patch(route('konfigurasi-user.role', $user), [
            'role' => UserRole::ADMIN->value,
        ])->assertForbidden();

        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $this->actingAs($superAdmin)->patch(route('konfigurasi-user.role', $superAdmin), [
            'role' => UserRole::MANAGER->value,
        ])->assertRedirect()->assertSessionHasErrors('role');

        $this->assertSame(UserRole::STAFF->value, $user->fresh()->role);
        $this->assertSame(UserRole::SUPER_ADMIN->value, $superAdmin->fresh()->role);
    }

    public function test_linked_manager_still_cannot_access_staff_wla(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::create([
            'sap' => 'SAP-WLA-ROLE',
            'personal_number' => 'PN-WLA-ROLE',
            'email' => 'wla-role@example.com',
        ]);
        $user = User::factory()->create([
            'role' => UserRole::STAFF->value,
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($superAdmin)->patch(route('konfigurasi-user.role', $user), [
            'role' => UserRole::MANAGER->value,
        ])->assertRedirect();

        $this->actingAs($user->fresh())->get(route('wla.staff.index'))->assertForbidden();
    }

    private function promotionForApproverTest(string $suffix, string $status, int $round): EmployeePromotion
    {
        $employee = Employee::query()->create(['sap' => "WORKFLOW-{$suffix}"]);

        return EmployeePromotion::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => "Pegawai {$suffix}",
            'departemen_lama' => 'Departemen Lama',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_lama' => 'Jabatan Lama',
            'jabatan_baru' => 'Jabatan Baru',
            'tmt' => today(),
            'pg' => 'PG-1',
            'level_lama' => 'B2',
            'jg_lama' => 'JG-2',
            'level_baru' => 'B1',
            'jg_baru' => 'JG-1',
            'verification_status' => $status,
            'approval_round' => $round,
            'current_approval_step' => $status === 'Waiting Approval' ? 1 : null,
        ]);
    }
}
