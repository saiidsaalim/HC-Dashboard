<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'Staff',
        ]);
    }

    public function test_registration_ignores_a_submitted_super_admin_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'Staff',
        ]);
    }

    public function test_registration_assigns_staff_when_the_database_default_is_pegawai(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('Pegawai')->change();
        });

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'Staff',
        ]);
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
