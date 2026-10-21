<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_first_password_change_page_is_only_available_while_the_flag_is_true(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::STAFF->value,
            'must_change_password' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('password.first.edit'));

        $response
            ->assertOk()
            ->assertSee('Ganti Password Pertama')
            ->assertSee('Untuk keamanan akun, ganti password sementara sebelum melanjutkan.')
            ->assertSee('Password Sementara/Password Saat Ini')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('data-confirm-submit', false)
            ->assertSee('data-confirm-title="Simpan password baru?"', false)
            ->assertSee('SIMPAN PASSWORD')
            ->assertSee('Keluar')
            ->assertDontSee('Pengguna dan Role')
            ->assertDontSee('Swal.fire(', false)
            ->assertDontSee('confirm(', false);

        $user->update(['must_change_password' => false]);

        $this->get(route('password.first.edit'))
            ->assertRedirect(route('portal'));
    }

    public function test_flagged_staff_is_restricted_to_the_first_password_page_and_logout(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::STAFF->value,
            'must_change_password' => true,
        ]);

        $this->actingAs($user);

        foreach (['dashboard', 'wla.staff.index', 'konfigurasi-user', 'profile.edit'] as $route) {
            $this->get(route($route))->assertRedirect(route('password.first.edit'));
        }

        $this->get(route('password.first.edit'))->assertOk();
        $this->post(route('logout'))->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    public function test_first_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('password.first.edit'))
            ->put(route('password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect(route('password.first.edit'));

        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_first_password_change_requires_confirmation(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('password.first.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password')
            ->assertRedirect(route('password.first.edit'));

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_first_password_change_rejects_the_current_password_as_the_new_password(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('password.first.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password')
            ->assertRedirect(route('password.first.edit'));

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_valid_first_password_change_is_hashed_clears_the_flag_and_regenerates_the_session(): void
    {
        $temporaryPassword = 'temporary-password-value';
        $newPassword = 'new-secure-password';
        $user = User::factory()->create([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);
        $logHandler = new TestHandler;
        Log::swap(new Logger('password-test', [$logHandler]));

        $this->actingAs($user);
        $this->get(route('password.first.edit'));
        $previousSessionId = session()->getId();

        $response = $this
            ->from(route('password.first.edit'))
            ->put(route('password.update'), [
                'current_password' => $temporaryPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal'))
            ->assertDontSee($temporaryPassword)
            ->assertDontSee($newPassword);

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertStringNotContainsString($temporaryPassword, json_encode($logHandler->getRecords(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($newPassword, json_encode($logHandler->getRecords(), JSON_THROW_ON_ERROR));

        $this->get(route('password.first.edit'))
            ->assertRedirect(route('portal'));
    }

    public function test_user_configuration_does_not_render_a_password_form_for_any_role(): void
    {
        foreach ([UserRole::STAFF, UserRole::MEMBER, UserRole::MANAGER, UserRole::ADMIN, UserRole::SUPER_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role->value]);

            $this->actingAs($user)->get(route('konfigurasi-user'))
                ->assertOk()
                ->assertDontSee('Ganti Password Pertama')
                ->assertDontSee('Ubah Password')
                ->assertDontSee('action="'.route('password.update').'"', false);
        }
    }
}
