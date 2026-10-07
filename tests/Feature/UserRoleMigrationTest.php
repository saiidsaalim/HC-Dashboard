<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoleMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_24_000003_add_role_to_users_table.php'))->up();
    }

    public function test_migration_changes_only_pegawai_users_to_staff(): void
    {
        $legacyUser = User::factory()->create(['role' => 'Pegawai']);
        $unchangedRoles = ['Super Admin', 'Admin', 'Manager', 'Staff', 'Member', 'Contractor', 'pegawai', 'Pegawai '];
        $otherUsers = User::factory()
            ->count(count($unchangedRoles))
            ->sequence(...array_map(fn (string $role): array => ['role' => $role], $unchangedRoles))
            ->create();

        $this->roleMigration()->up();

        $this->assertDatabaseHas('users', ['id' => $legacyUser->id, 'role' => 'Staff']);
        foreach ($otherUsers as $index => $user) {
            $this->assertSame($unchangedRoles[$index], $user->fresh()->role);
        }
        $this->assertDatabaseCount('users', count($unchangedRoles) + 1);
    }

    public function test_users_without_an_explicit_role_default_to_staff(): void
    {
        $this->roleMigration()->up();
        $attributes = User::factory()->raw();
        unset($attributes['role']);

        $user = User::create($attributes);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'Staff']);
    }

    public function test_rollback_restores_the_default_without_relabeling_staff_users(): void
    {
        $legacyUser = User::factory()->create(['role' => 'Pegawai']);
        $existingStaff = User::factory()->create(['role' => 'Staff']);
        $migration = $this->roleMigration();
        $migration->up();

        $migration->down();

        $this->assertDatabaseHas('users', ['id' => $legacyUser->id, 'role' => 'Staff']);
        $this->assertDatabaseHas('users', ['id' => $existingStaff->id, 'role' => 'Staff']);
        $attributes = User::factory()->raw();
        unset($attributes['role']);
        $user = User::create($attributes);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'Pegawai']);
    }

    private function roleMigration(): Migration
    {
        return require database_path('migrations/2026_10_07_064034_change_users_role_default_to_staff.php');
    }
}
