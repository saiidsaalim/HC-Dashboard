<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_super_admin_can_import_users_from_csv_with_email_and_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $file = UploadedFile::fake()->createWithContent('users.csv', "email,password\nsiti.tester@example.com,password123\n");

        $response = $this->actingAs($admin)->post(route('konfigurasi-user.import'), ['file' => $file]);

        $response->assertRedirect()->assertSessionHas('status', 'Import selesai: 1 user ditambahkan, 0 email duplikat dilewati.');
        $this->assertDatabaseHas('users', [
            'name' => 'Siti Tester',
            'email' => 'siti.tester@example.com',
            'role' => UserRole::STAFF->value,
        ]);
    }

    public function test_import_rejects_a_file_without_email_and_password_columns(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,role\nSiti Tester,Member\n");

        $response = $this->actingAs($admin)->from('/konfigurasi-user')->post(route('konfigurasi-user.import'), ['file' => $file]);

        $response->assertRedirect('/konfigurasi-user')->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('users', ['email' => 'siti@example.com']);
    }
}
