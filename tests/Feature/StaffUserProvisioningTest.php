<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Services\Users\StaffUserProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffUserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlinked_employees_automatically_appear_as_candidates_without_a_candidate_table(): void
    {
        $superAdmin = $this->superAdmin();
        $candidate = $this->employee('SAP-CANDIDATE', 'Calon Staff', 'candidate@example.com');
        $linked = $this->employee('SAP-LINKED', 'Staff Terhubung', 'linked@example.com');
        User::factory()->create(['employee_id' => $linked->id]);
        $incomplete = $this->employee('SAP-NAMELESS', null, 'nameless@example.com');
        $candidate->forceFill([
            'department' => 'Legacy Department',
            'position' => 'Legacy Position',
        ])->save();

        $response = $this->actingAs($superAdmin)->get(route('konfigurasi-user'));

        $response->assertOk()
            ->assertSee('Calon Staff')
            ->assertSee('candidate@example.com')
            ->assertSee('SAP-NAMELESS')
            ->assertSee('Belum siap')
            ->assertSee('Personal Number Employee belum tersedia.')
            ->assertDontSee(route('konfigurasi-user.candidates.approve', $linked))
            ->assertDontSee(config('user_provisioning.temporary_password'));
        $this->assertFalse(Schema::hasTable('user_candidates'));
        $this->assertNull($candidate->user);
        $this->assertNull($incomplete->user);
    }

    public function test_candidate_page_does_not_require_an_employees_name_column(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        $superAdmin = $this->superAdmin();
        $this->employee('SAP-WITHOUT-NAME-COLUMN', 'PN-WITHOUT-NAME-COLUMN', 'without.name.column@example.com');

        $this->actingAs($superAdmin)->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('PN-WITHOUT-NAME-COLUMN');
    }

    public function test_super_admin_can_approve_one_candidate_with_authoritative_employee_identity(): void
    {
        $superAdmin = $this->superAdmin();
        $employee = $this->employee('SAP-ONE', '  PN   0001  ', 'STAFF@EXAMPLE.COM');

        $this->actingAs($superAdmin)->post(
            route('konfigurasi-user.candidates.approve', $employee),
            [
                'name' => 'Nama Manipulasi',
                'email' => 'manipulated@example.com',
                'role' => UserRole::SUPER_ADMIN->value,
                'password' => 'manipulated-password',
            ],
        )->assertRedirect()->assertSessionHasNoErrors();

        $user = $employee->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('PN 0001', $user->name);
        $this->assertSame('staff@example.com', $user->email);
        $this->assertSame(UserRole::STAFF->value, $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check(config('user_provisioning.temporary_password'), $user->password));
        $this->assertFalse(Hash::check('manipulated-password', $user->password));
    }

    public function test_bulk_approval_creates_every_candidate_atomically(): void
    {
        $superAdmin = $this->superAdmin();
        $first = $this->employee('SAP-BULK-1', 'Bulk Satu', 'bulk.one@example.com');
        $second = $this->employee('SAP-BULK-2', 'Bulk Dua', 'bulk.two@example.com');

        $this->actingAs($superAdmin)
            ->post(route('konfigurasi-user.candidates.approve-all'), [
                'candidate_batch_token' => app(StaffUserProvisioningService::class)->candidateBatchToken(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', '2 calon User berhasil disetujui.');

        foreach ([$first, $second] as $employee) {
            $user = $employee->fresh()->user;
            $this->assertNotNull($user);
            $this->assertSame(UserRole::STAFF->value, $user->role);
            $this->assertTrue($user->must_change_password);
        }
    }

    public function test_temporary_password_is_read_from_configuration(): void
    {
        config()->set('user_provisioning.temporary_password', 'configured-temporary-password');
        $superAdmin = $this->superAdmin();
        $employee = $this->employee('SAP-CONFIG-PASSWORD', 'PN-CONFIG-PASSWORD', 'config.password@example.com');

        $this->actingAs($superAdmin)->post(route('konfigurasi-user.candidates.approve', $employee))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('configured-temporary-password', $employee->fresh()->user->password));
    }

    public function test_bulk_approval_rolls_back_all_candidates_when_one_is_invalid(): void
    {
        $superAdmin = $this->superAdmin();
        $valid = $this->employee('SAP-ATOMIC-1', 'Valid Candidate', 'valid@example.com');
        $invalid = $this->employee('SAP-ATOMIC-2', null, 'invalid@example.com');

        $this->actingAs($superAdmin)
            ->post(route('konfigurasi-user.candidates.approve-all'), [
                'candidate_batch_token' => app(StaffUserProvisioningService::class)->candidateBatchToken(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('candidates');

        $this->assertNull($valid->fresh()->user);
        $this->assertNull($invalid->fresh()->user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bulk_approval_rolls_back_when_candidate_data_changed_after_page_load(): void
    {
        $superAdmin = $this->superAdmin();
        $first = $this->employee('SAP-CHANGED-1', 'First Candidate', 'first@example.com');
        $second = $this->employee('SAP-CHANGED-2', 'Second Candidate', 'second@example.com');
        $batchToken = app(StaffUserProvisioningService::class)->candidateBatchToken();
        $second->forceFill(['email' => 'changed@example.com'])->save();

        $this->actingAs($superAdmin)
            ->post(route('konfigurasi-user.candidates.approve-all'), [
                'candidate_batch_token' => $batchToken,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('candidates');

        $this->assertNull($first->fresh()->user);
        $this->assertNull($second->fresh()->user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_only_super_admin_can_approve_candidates(): void
    {
        $employee = $this->employee('SAP-DENIED', 'Denied Candidate', 'denied@example.com');

        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $actor = User::factory()->create(['role' => $role->value]);
            $this->actingAs($actor)
                ->post(route('konfigurasi-user.candidates.approve', $employee))
                ->assertForbidden();
        }

        $this->assertNull($employee->fresh()->user);
    }

    public function test_provisioned_staff_is_restricted_until_the_temporary_password_is_changed(): void
    {
        $superAdmin = $this->superAdmin();
        $employee = $this->employee('SAP-FORCED', 'Forced Change', 'forced@example.com');
        $this->actingAs($superAdmin)->post(route('konfigurasi-user.candidates.approve', $employee));
        $staff = $employee->fresh()->user;
        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('password.first.edit'));
        $this->actingAs($staff)->get(route('wla.staff.index'))->assertRedirect(route('password.first.edit'));
        $this->actingAs($staff)->get(route('profile.edit'))->assertRedirect(route('password.first.edit'));
        $this->actingAs($staff)->get(route('konfigurasi-user'))
            ->assertRedirect(route('password.first.edit'));
        $this->actingAs($staff)->get(route('password.first.edit'))
            ->assertOk()
            ->assertSee('Ganti Password Pertama')
            ->assertDontSee('forced@example.com')
            ->assertDontSee($otherUser->email);

        $this->actingAs($staff)->put(route('password.update'), [
            'current_password' => config('user_provisioning.temporary_password'),
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('portal'))->assertSessionHasNoErrors();

        $this->assertFalse($staff->fresh()->must_change_password);
        $this->assertTrue(Hash::check('new-secure-password', $staff->password));
        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    }

    public function test_first_login_with_temporary_password_redirects_to_password_form(): void
    {
        $superAdmin = $this->superAdmin();
        $employee = $this->employee('SAP-LOGIN', 'First Login', 'first.login@example.com');
        $this->actingAs($superAdmin)->post(route('konfigurasi-user.candidates.approve', $employee));
        $this->post(route('logout'));

        $this->post(route('login'), [
            'email' => 'first.login@example.com',
            'password' => config('user_provisioning.temporary_password'),
        ])->assertRedirect(route('password.first.edit'));
    }

    public function test_first_password_change_is_still_required_after_logout_and_next_login(): void
    {
        $superAdmin = $this->superAdmin();
        $employee = $this->employee('SAP-LOGOUT', 'First Login Logout', 'first.login.logout@example.com');
        $this->actingAs($superAdmin)->post(route('konfigurasi-user.candidates.approve', $employee));
        $staff = $employee->fresh()->user;

        $this->actingAs($staff)->get(route('password.first.edit'))->assertOk();
        $this->post(route('logout'))->assertRedirectToRoute('login');
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => config('user_provisioning.temporary_password'),
        ])->assertRedirect(route('password.first.edit'));

        $this->assertTrue($staff->fresh()->must_change_password);
    }

    public function test_staff_and_member_only_see_their_own_user_while_management_roles_keep_current_visibility(): void
    {
        $other = User::factory()->create(['email' => 'visible-to-management@example.com']);

        foreach ([UserRole::STAFF, UserRole::MEMBER] as $role) {
            $actor = User::factory()->create(['role' => $role->value]);
            $this->actingAs($actor)->get(route('konfigurasi-user'))
                ->assertOk()
                ->assertSee($actor->email)
                ->assertDontSee($other->email)
                ->assertDontSee('Tambah Pengguna');
        }

        foreach ([UserRole::MANAGER, UserRole::ADMIN, UserRole::SUPER_ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role->value]);
            $this->actingAs($actor)->get(route('konfigurasi-user'))
                ->assertOk()
                ->assertSee($other->email);
        }
    }

    public function test_linked_staff_cannot_change_employee_identity_from_profile(): void
    {
        $employee = $this->employee('SAP-PROFILE', 'Employee Identity', 'identity@example.com');
        $staff = User::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Employee Identity',
            'email' => 'identity@example.com',
        ]);

        $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => 'Manipulated Name',
            'email' => 'manipulated@example.com',
        ])->assertForbidden();

        $this->assertSame('Employee Identity', $staff->fresh()->name);
        $this->assertSame('identity@example.com', $staff->email);
    }

    public function test_updating_employee_identity_synchronizes_the_linked_staff_user(): void
    {
        $superAdmin = $this->superAdmin();
        $employee = $this->employee('SAP-SYNC', 'Old Name', 'old@example.com');
        $this->actingAs($superAdmin)->post(route('konfigurasi-user.candidates.approve', $employee));

        $this->actingAs($superAdmin)->put(route('data-pegawai.update', $employee), [
            'sap' => $employee->sap,
            'personal_number' => '  New   Personal Number ',
            'email' => 'NEW.EMAIL@EXAMPLE.COM',
        ])->assertRedirect(route('data-pegawai'));

        $user = $employee->fresh()->user;
        $this->assertSame('New Personal Number', $user->name);
        $this->assertSame('new.email@example.com', $user->email);
        $this->assertSame(UserRole::STAFF->value, $user->role);
    }

    public function test_candidate_approval_forms_use_the_shared_sweetalert_confirmation_attributes(): void
    {
        $superAdmin = $this->superAdmin();
        $this->employee('SAP-CONFIRM', 'Confirm Candidate', 'confirm@example.com');

        $this->actingAs($superAdmin)->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('data-confirm-submit', false)
            ->assertSee('Setujui semua calon User?', false)
            ->assertSee('Setujui calon User ini?', false);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    private function employee(string $sap, ?string $personalNumber, string $email): Employee
    {
        return Employee::query()->create([
            'sap' => $sap,
            'personal_number' => $personalNumber,
            'email' => $email,
        ]);
    }
}
