<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_users_schema_has_final_authentication_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'employee_id',
            'name',
            'email',
            'email_verified_at',
            'password',
            'role',
            'must_change_password',
            'remember_token',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_users_without_an_explicit_role_default_to_staff(): void
    {
        $attributes = User::factory()->raw();
        unset($attributes['role']);

        $user = User::query()->create($attributes);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'Staff']);
    }

    public function test_explicit_application_roles_are_preserved(): void
    {
        foreach (['Super Admin', 'Admin', 'Manager', 'Staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertSame($role, $user->fresh()->role);
        }
    }
}
