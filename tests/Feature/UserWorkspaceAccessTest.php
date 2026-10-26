<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_only_super_admin_sees_workspace_access_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($superAdmin)
            ->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('Akses Workspace')
            ->assertSee('name="workspace_access[]"', escape: false)
            ->assertSee('Performance &amp; Talent Management', escape: false);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->grantPermissionsToRole(UserRole::ADMIN, ['users.view', 'users.update']);

        $this->actingAs($admin)
            ->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertSee('user-edit-'.$target->id)
            ->assertDontSee('Akses Workspace')
            ->assertDontSee('name="workspace_access[]"', escape: false);
    }

    public function test_non_super_admin_defaults_to_hc_od_workspace_only(): void
    {
        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $user = User::factory()->create(['role' => $role->value]);

            $this->assertTrue($user->hasPermission('workspace.hc-od-career-management.view'));
            $this->assertFalse($user->hasPermission('workspace.performance-talent-management.view'));
            $this->assertFalse($user->hasPermission('workspace.learning-development.view'));

            $response = $this->actingAs($user)->get(route('portal'))->assertOk();
            $response
                ->assertSee('HC OD &amp; Career Management', escape: false)
                ->assertDontSee('Performance &amp; Talent Management', escape: false)
                ->assertDontSee('Learning &amp; Development', escape: false);
        }
    }

    public function test_super_admin_can_grant_and_revoke_workspace_access_without_changing_other_permissions_or_role(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);
        $nonWorkspacePermission = Permission::query()->where('key', 'employees.view')->sole();

        DB::table('permission_user')->insert([
            'permission_id' => $nonWorkspacePermission->id,
            'user_id' => $target->id,
            'granted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->put(route('konfigurasi-user.update', $target), $this->updatePayload($target, [
                'workspace_access_submitted' => '1',
                'workspace_access' => [
                    'performance-talent-management',
                    'performance-talent-management',
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Akses workspace pengguna berhasil diperbarui.')
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::STAFF, $target->fresh()->roleEnum());
        $this->assertDatabaseHas('permission_user', [
            'permission_id' => $nonWorkspacePermission->id,
            'user_id' => $target->id,
            'granted' => true,
        ]);
        $this->assertTrue($target->fresh()->hasPermission('workspace.performance-talent-management.view'));
        $this->assertFalse($target->fresh()->hasPermission('workspace.hc-od-career-management.view'));

        $workspaceViewPermissionId = Permission::query()->where('key', 'workspace.view')->value('id');
        DB::table('permission_role')
            ->where('role', UserRole::STAFF->value)
            ->where('permission_id', $workspaceViewPermissionId)
            ->delete();
        app(RolePermissionCache::class)->clear();
        $this->assertTrue($target->fresh()->hasPermission('workspace.view'));

        $this->actingAs($target->fresh())
            ->get(route('portal'))
            ->assertOk()
            ->assertSee('Performance &amp; Talent Management', escape: false)
            ->assertDontSee('HC OD &amp; Career Management', escape: false);
        $this->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('performance-talent.dashboard');
        $this->get(route('performance-talent.dashboard'))->assertOk();

        $this->actingAs($superAdmin)
            ->put(route('konfigurasi-user.update', $target), $this->updatePayload($target, [
                'workspace_access_submitted' => '1',
                'workspace_access' => [],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($target->fresh())
            ->get(route('performance-talent.dashboard'))
            ->assertForbidden();
        $this->assertFalse($target->fresh()->hasPermission('workspace.view'));
    }

    public function test_non_super_admin_cannot_submit_workspace_access_manually(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);
        $this->grantPermissionsToRole(UserRole::ADMIN, ['users.update']);

        $this->actingAs($admin)
            ->put(route('konfigurasi-user.update', $target), $this->updatePayload($target, [
                'workspace_access_submitted' => '1',
                'workspace_access' => ['performance-talent-management'],
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('permission_user', 0);
    }

    public function test_unknown_inactive_and_non_assignable_workspace_keys_are_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        foreach (['workspace-palsu', 'hc-od-career-management', 'learning-development'] as $index => $workspaceKey) {
            Config::set('portal.units.0.workspaces.0.active', $index !== 1);
            Config::set('portal.units.0.workspaces.2.assignable', $index !== 2);

            $this->actingAs($superAdmin)
                ->from(route('konfigurasi-user'))
                ->put(route('konfigurasi-user.update', $target), $this->updatePayload($target, [
                    'workspace_access_submitted' => '1',
                    'workspace_access' => [$workspaceKey],
                ]))
                ->assertRedirect(route('konfigurasi-user'))
                ->assertSessionHasErrors('workspace_access.0');

            Config::set('portal.units.0.workspaces.0.active', true);
            Config::set('portal.units.0.workspaces.2.assignable', true);
        }

        $this->assertDatabaseCount('permission_user', 0);
    }

    public function test_modal_reflects_saved_access_and_super_admin_access_is_immutable(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($superAdmin)
            ->put(route('konfigurasi-user.update', $target), $this->updatePayload($target, [
                'workspace_access_submitted' => '1',
                'workspace_access' => ['performance-talent-management'],
            ]))
            ->assertSessionHasNoErrors();

        $html = $this->get(route('konfigurasi-user'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/id="workspace-access-'.$target->id.'-performance-talent-management"[^>]*checked/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="workspace-access-'.$target->id.'-hc-od-career-management"[^>]*checked/',
            $html,
        );

        $this->put(route('konfigurasi-user.update', $superAdmin), $this->updatePayload($superAdmin, [
            'workspace_access_submitted' => '1',
            'workspace_access' => [],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('permission_user', ['user_id' => $superAdmin->id]);
        $this->actingAs($superAdmin->fresh())
            ->get(route('performance-talent.dashboard'))
            ->assertOk();

        $superAdminHtml = $this->get(route('konfigurasi-user'))->assertOk()
            ->assertSee('Super Admin otomatis memiliki akses ke seluruh workspace.')
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="workspace-access-'.$superAdmin->id.'-performance-talent-management"[^>]*checked[^>]*disabled/',
            $superAdminHtml,
        );
    }

    public function test_new_assignable_workspace_appears_unchecked_for_a_configured_user(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($superAdmin)
            ->put(route('konfigurasi-user.update', $target), $this->updatePayload($target, [
                'workspace_access_submitted' => '1',
                'workspace_access' => ['hc-od-career-management'],
            ]))
            ->assertSessionHasNoErrors();

        $workspaces = config('portal.units.0.workspaces');
        $workspaces[] = [
            'slug' => 'workspace-baru',
            'name' => 'Workspace Baru',
            'permission' => 'reports.view',
            'active' => true,
            'assignable' => true,
        ];
        Config::set('portal.units.0.workspaces', $workspaces);

        $html = $this->get(route('konfigurasi-user'))->assertOk()
            ->assertSee('Workspace Baru')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="workspace-access-'.$target->id.'-workspace-baru"[^>]*checked/',
            $html,
        );
    }

    public function test_user_list_eager_loads_workspace_access_without_n_plus_one_queries(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        User::factory()->count(12)->create(['role' => UserRole::STAFF->value]);
        $permissionUserQueries = 0;

        DB::listen(function ($query) use (&$permissionUserQueries): void {
            if (str_contains($query->sql, 'permission_user')) {
                $permissionUserQueries++;
            }
        });

        $this->actingAs($superAdmin)
            ->get(route('konfigurasi-user'))
            ->assertOk();

        $this->assertSame(1, $permissionUserQueries);
    }

    /** @param array<string, mixed> $overrides */
    private function updatePayload(User $user, array $overrides = []): array
    {
        return [
            '_form' => 'edit-user-'.$user->id,
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            ...$overrides,
        ];
    }

    /** @param array<int, string> $permissionKeys */
    private function grantPermissionsToRole(UserRole $role, array $permissionKeys): void
    {
        $now = now();
        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id', 'key');

        DB::table('permission_role')->insertOrIgnore(array_map(
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
}
