<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\PermissionRegistry;
use App\Services\Authorization\PermissionSynchronizer;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PermissionSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_sync_command_is_registered(): void
    {
        $this->assertArrayHasKey('permissions:sync', Artisan::all());
    }

    public function test_first_sync_creates_every_permission_and_second_sync_is_idempotent(): void
    {
        $first = $this->synchronizer()->synchronize();
        $permissionCount = count(PermissionRegistry::permissions());
        $pivotCount = DB::table('permission_role')->count();

        $this->assertSame($permissionCount, $first['created']);
        $this->assertSame($permissionCount, Permission::query()->count());

        $second = $this->synchronizer()->synchronize();

        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(0, $second['relations_added']);
        $this->assertSame(0, $second['relations_removed']);
        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertSame($pivotCount, DB::table('permission_role')->count());
    }

    public function test_sync_updates_permission_metadata_by_stable_key(): void
    {
        $this->synchronizer()->synchronize();
        Permission::query()->where('key', 'dashboard.view')->update([
            'label' => 'Label Lama',
            'group' => 'legacy',
            'group_label' => 'Legacy',
            'sort_order' => 999,
        ]);

        $result = $this->synchronizer()->synchronize();

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('permissions', [
            'key' => 'dashboard.view',
            'label' => 'Lihat dashboard',
            'group' => 'dashboard',
            'group_label' => 'Dashboard',
            'sort_order' => 0,
        ]);
    }

    public function test_dry_run_reports_changes_without_mutating_database(): void
    {
        $this->artisan('permissions:sync --dry-run')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Permission dibuat')
            ->expectsOutputToContain('Relasi ditambahkan')
            ->assertSuccessful();

        $this->assertDatabaseCount('permissions', 0);
        $this->assertDatabaseCount('permission_role', 0);
    }

    public function test_default_mode_preserves_manual_role_configuration(): void
    {
        $this->synchronizer()->synchronize(applyDefaults: true);
        $dashboardId = Permission::query()->where('key', 'dashboard.view')->value('id');
        $employeeCreateId = Permission::query()->where('key', 'employees.create')->value('id');

        DB::table('permission_role')
            ->where('role', UserRole::MANAGER->value)
            ->where('permission_id', $dashboardId)
            ->delete();
        DB::table('permission_role')->insert([
            'role' => UserRole::MANAGER->value,
            'permission_id' => $employeeCreateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->synchronizer()->synchronize();

        $this->assertSame(0, $result['relations_removed']);
        $this->assertDatabaseMissing('permission_role', [
            'role' => UserRole::MANAGER->value,
            'permission_id' => $dashboardId,
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role' => UserRole::MANAGER->value,
            'permission_id' => $employeeCreateId,
        ]);
    }

    public function test_new_permission_receives_defaults_only_when_first_created(): void
    {
        $this->synchronizer()->synchronize(applyDefaults: true);
        Permission::query()->where('key', 'formation.view')->delete();

        $first = $this->synchronizer()->synchronize();
        $permissionId = Permission::query()->where('key', 'formation.view')->value('id');

        $this->assertContains('formation.view', $first['created_keys']);
        $this->assertDatabaseHas('permission_role', [
            'role' => UserRole::MANAGER->value,
            'permission_id' => $permissionId,
        ]);

        DB::table('permission_role')
            ->where('role', UserRole::MANAGER->value)
            ->where('permission_id', $permissionId)
            ->delete();

        $this->synchronizer()->synchronize();

        $this->assertDatabaseMissing('permission_role', [
            'role' => UserRole::MANAGER->value,
            'permission_id' => $permissionId,
        ]);
    }

    public function test_apply_defaults_restores_the_exact_registry_baseline(): void
    {
        $this->synchronizer()->synchronize();
        $employeeCreateId = Permission::query()->where('key', 'employees.create')->value('id');
        DB::table('permission_role')->insertOrIgnore([
            'role' => UserRole::MANAGER->value,
            'permission_id' => $employeeCreateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->synchronizer()->synchronize(applyDefaults: true);

        foreach (UserRole::cases() as $role) {
            $expectedKeys = collect(PermissionRegistry::permissions())
                ->filter(fn (array $definition): bool => $role === UserRole::SUPER_ADMIN
                    || in_array($role, $definition['default_roles'], true))
                ->keys()
                ->all();
            $actualKeys = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('permission_role.role', $role->value)
                ->pluck('permissions.key')
                ->all();

            $this->assertEqualsCanonicalizing($expectedKeys, $actualKeys, "Baseline {$role->value} berbeda.");
        }

        $this->assertDatabaseMissing('permission_role', [
            'role' => UserRole::MANAGER->value,
            'permission_id' => $employeeCreateId,
        ]);
    }

    public function test_apply_defaults_command_requires_confirmation_and_applies_baseline(): void
    {
        $this->synchronizer()->synchronize();
        $employeeCreateId = Permission::query()->where('key', 'employees.create')->value('id');
        DB::table('permission_role')->insert([
            'role' => UserRole::MANAGER->value,
            'permission_id' => $employeeCreateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('permissions:sync --apply-defaults')
            ->expectsConfirmation('Terapkan baseline hak akses role?', 'yes')
            ->expectsOutputToContain('konfigurasi hak akses role lokal akan disamakan')
            ->assertSuccessful();

        $this->assertDatabaseMissing('permission_role', [
            'role' => UserRole::MANAGER->value,
            'permission_id' => $employeeCreateId,
        ]);
    }

    public function test_apply_defaults_is_rejected_non_interactively(): void
    {
        $this->artisan('permissions:sync --apply-defaults --no-interaction')
            ->expectsOutputToContain('ditolak pada proses non-interaktif')
            ->assertFailed();

        $this->assertDatabaseCount('permissions', 0);
        $this->assertDatabaseCount('permission_role', 0);
    }

    public function test_super_admin_is_repaired_without_changing_other_roles(): void
    {
        $this->synchronizer()->synchronize(applyDefaults: true);
        DB::table('permission_role')->where('role', UserRole::SUPER_ADMIN->value)->delete();
        $managerCount = DB::table('permission_role')->where('role', UserRole::MANAGER->value)->count();

        $this->synchronizer()->synchronize();

        $this->assertSame(
            count(PermissionRegistry::permissions()),
            DB::table('permission_role')->where('role', UserRole::SUPER_ADMIN->value)->count(),
        );
        $this->assertSame($managerCount, DB::table('permission_role')->where('role', UserRole::MANAGER->value)->count());
    }

    public function test_duplicate_registry_key_is_rejected_with_a_clear_error(): void
    {
        $definition = PermissionRegistry::permissions()['dashboard.view'];
        $synchronizer = new class(app(RolePermissionCache::class), $definition) extends PermissionSynchronizer
        {
            /** @param array<string, mixed> $definition */
            public function __construct(RolePermissionCache $cache, private array $definition)
            {
                parent::__construct($cache);
            }

            protected function permissionDefinitions(): array
            {
                return [$this->definition, $this->definition];
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Permission key ganda terdeteksi: dashboard.view.');

        $synchronizer->synchronize(dryRun: true);
    }

    public function test_unregistered_workspace_permission_is_rejected(): void
    {
        Config::set('portal.units.0.workspaces.0.permission', 'workspace.not-registered.view');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('workspace.not-registered.view');

        $this->synchronizer()->synchronize(dryRun: true);
    }

    public function test_stale_permission_is_reported_and_never_deleted(): void
    {
        $this->synchronizer()->synchronize(applyDefaults: true);
        $stale = Permission::query()->create([
            'key' => 'legacy.permission',
            'label' => 'Legacy Permission',
            'group' => 'legacy',
            'group_label' => 'Legacy',
            'sort_order' => 999,
        ]);
        DB::table('permission_role')->insert([
            'permission_id' => $stale->id,
            'role' => UserRole::MANAGER->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->synchronizer()->synchronize(applyDefaults: true);

        $this->assertSame(['legacy.permission'], $result['stale_keys']);
        $this->assertDatabaseHas('permissions', ['key' => 'legacy.permission']);
        $this->assertDatabaseHas('permission_role', [
            'permission_id' => $stale->id,
            'role' => UserRole::MANAGER->value,
        ]);
    }

    public function test_manager_can_login_see_and_open_the_wla_workspace(): void
    {
        $this->seed(PermissionSeeder::class);
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);

        $this->post('/login', [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect(route('portal', absolute: false));
        $this->assertAuthenticatedAs($manager);

        $this->get(route('portal'))
            ->assertOk()
            ->assertSee('HC OD & Career Management')
            ->assertDontSee('Belum ada workspace yang dapat diakses.');
        $this->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard');
    }

    public function test_manager_without_workspace_permission_cannot_see_or_open_it(): void
    {
        $this->seed(PermissionSeeder::class);
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $permissionId = Permission::query()
            ->where('key', 'workspace.hc-od-career-management.view')
            ->value('id');
        DB::table('permission_role')
            ->where('role', UserRole::MANAGER->value)
            ->where('permission_id', $permissionId)
            ->delete();
        app(RolePermissionCache::class)->clear();

        $this->actingAs($manager)->get(route('portal'))
            ->assertOk()
            ->assertDontSee('HC OD & Career Management');
        $this->get(route('portal.workspace', 'hc-od-career-management'))->assertForbidden();
    }

    public function test_empty_workspace_state_only_appears_when_no_workspace_is_authorized(): void
    {
        $this->seed(PermissionSeeder::class);
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $workspacePermissionIds = Permission::query()
            ->where('key', 'like', 'workspace.%.view')
            ->pluck('id');
        DB::table('permission_role')
            ->where('role', UserRole::MANAGER->value)
            ->whereIn('permission_id', $workspacePermissionIds)
            ->delete();
        app(RolePermissionCache::class)->clear();

        $this->actingAs($manager)->get(route('portal'))
            ->assertOk()
            ->assertSee('Belum ada workspace yang dapat diakses.');

        $allowedPermissionId = Permission::query()
            ->where('key', 'workspace.hc-od-career-management.view')
            ->value('id');
        DB::table('permission_role')->insert([
            'permission_id' => $allowedPermissionId,
            'role' => UserRole::MANAGER->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(RolePermissionCache::class)->clear();

        $this->get(route('portal'))
            ->assertOk()
            ->assertDontSee('Belum ada workspace yang dapat diakses.')
            ->assertSee('HC OD & Career Management');
    }

    public function test_popup_automatically_lists_a_permission_created_by_sync(): void
    {
        $this->seed(PermissionSeeder::class);
        Permission::query()->where('key', 'reports.view')->delete();
        $this->synchronizer()->synchronize();

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('reports.view')
            ->assertSee('Lihat Laporan');
    }

    public function test_permission_seeder_is_repeatable(): void
    {
        $this->seed(PermissionSeeder::class);
        $permissionCount = Permission::query()->count();
        $pivotCount = DB::table('permission_role')->count();

        $this->seed(PermissionSeeder::class);

        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertSame($pivotCount, DB::table('permission_role')->count());
    }

    public function test_sync_does_not_change_users_or_business_data(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $employee = Employee::query()->create(['sap' => 'SYNC-SAFETY']);

        $this->synchronizer()->synchronize(applyDefaults: true);

        $this->assertSame(UserRole::MANAGER->value, $manager->fresh()->role);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'sap' => 'SYNC-SAFETY']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_successful_sync_clears_authorization_cache(): void
    {
        Cache::put(RolePermissionCache::CacheKey, [
            'configured' => true,
            'roles' => [],
        ]);

        $this->synchronizer()->synchronize();

        $this->assertFalse(Cache::has(RolePermissionCache::CacheKey));
    }

    private function synchronizer(): PermissionSynchronizer
    {
        return app(PermissionSynchronizer::class);
    }
}
