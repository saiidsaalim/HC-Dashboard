<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_opening_root_is_redirected_to_login(): void
    {
        $this->get('/')
            ->assertRedirectToRoute('login');
    }

    public function test_guest_opening_portal_is_redirected_to_login_with_intended_url(): void
    {
        $portalUrl = route('portal');

        $this->get($portalUrl)
            ->assertRedirectToRoute('login')
            ->assertSessionHas('url.intended', $portalUrl);
    }

    public function test_authenticated_user_opening_root_is_redirected_to_portal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirectToRoute('portal');
    }

    public function test_super_admin_sees_all_authorized_workspace_cards(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($user)->get(route('portal'));

        $response
            ->assertOk()
            ->assertDontSee('>HC Portal<', false)
            ->assertDontSee('>Beranda<', false)
            ->assertSee('data-portal-logo', false)
            ->assertSee('Pilih Workspace')
            ->assertSee('HC Planning & Development')
            ->assertSee('HC OD & Career Management')
            ->assertSee('Performance & Talent Management')
            ->assertSee('Learning & Development')
            ->assertSee('Masuk Workspace')
            ->assertSee('data-portal-hero', false)
            ->assertSee('data-portal-header', false)
            ->assertSee('data-portal-details', false)
            ->assertSee('Satu portal untuk pekerjaan Human Capital')
            ->assertSee('href="'.route('portal.workspace', 'hc-od-career-management').'"', false)
            ->assertSee('href="'.route('portal.workspace', 'performance-talent-management').'"', false)
            ->assertSee(asset('images/tonasa-bg.jpg'), false)
            ->assertSee('Keluar')
            ->assertDontSee('Masuk ke Portal');

        $this->assertSame(1, substr_count($response->getContent(), asset('images/logo-tonasa.png')));
        $this->assertSame(1, substr_count($response->getContent(), asset('images/tonasa-bg.jpg')));
    }

    public function test_portal_renders_centered_workspace_cards_with_direct_links(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($user)->get(route('portal'));

        $response
            ->assertOk()
            ->assertSee('data-workspace-list', escape: false)
            ->assertDontSee('data-workspace-carousel', escape: false)
            ->assertDontSee('data-workspace-filter', escape: false)
            ->assertDontSee('data-carousel-previous', escape: false)
            ->assertDontSee('data-carousel-next', escape: false)
            ->assertSee('data-workspace-count="3"', escape: false)
            ->assertSee('data-workspace-slug="hc-od-career-management"', escape: false)
            ->assertSee('href="'.route('portal.workspace', 'hc-od-career-management').'"', escape: false)
            ->assertDontSee('>Aktif<', escape: false);

        $this->assertSame(3, substr_count($response->getContent(), 'data-workspace-card'));
        $this->assertSame(3, substr_count($response->getContent(), 'data-enter-workspace'));
    }

    public function test_portal_handles_zero_one_and_two_authorized_workspaces(): void
    {
        $limitedUser = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($limitedUser)
            ->get(route('portal'))
            ->assertOk()
            ->assertSee('Belum ada workspace yang dapat diakses.')
            ->assertDontSee('data-workspace-list', escape: false);

        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $workspaces = config('portal.units.0.workspaces');

        Config::set('portal.units.0.workspaces', array_slice($workspaces, 0, 1));
        $oneWorkspaceResponse = $this->actingAs($superAdmin)->get(route('portal'));

        $oneWorkspaceResponse
            ->assertOk()
            ->assertSee('data-workspace-count="1"', escape: false);
        $this->assertSame(1, substr_count($oneWorkspaceResponse->getContent(), 'data-workspace-card'));

        Config::set('portal.units.0.workspaces', array_slice($workspaces, 0, 2));
        $twoWorkspaceResponse = $this->get(route('portal'));

        $twoWorkspaceResponse
            ->assertOk()
            ->assertSee('data-workspace-count="2"', escape: false);
        $this->assertSame(2, substr_count($twoWorkspaceResponse->getContent(), 'data-workspace-card'));
    }

    public function test_limited_user_only_sees_and_opens_permitted_workspace(): void
    {
        $user = $this->userWithPermissions([
            'workspace.view',
            'workspace.performance-talent-management.view',
            'dashboard.view',
        ]);

        $this->actingAs($user)
            ->get(route('portal'))
            ->assertOk()
            ->assertSee('Performance & Talent Management')
            ->assertDontSee('HC OD & Career Management')
            ->assertDontSee('Learning & Development');

        $this->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertForbidden();

        $this->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('active_workspace', 'performance-talent-management');
    }

    public function test_guest_is_sent_to_login_when_opening_a_workspace(): void
    {
        $workspaceUrl = route('portal.workspace', 'hc-od-career-management');

        $this->get($workspaceUrl)
            ->assertRedirectToRoute('login')
            ->assertSessionHas('url.intended', $workspaceUrl);
    }

    public function test_authenticated_user_is_sent_to_dashboard_from_a_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('active_workspace', 'hc-od-career-management');
    }

    public function test_authenticated_user_can_open_learning_and_development_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('portal.workspace', 'learning-development'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('active_workspace', 'learning-development');
    }

    public function test_authenticated_user_gets_a_404_for_an_unknown_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('portal.workspace', 'unknown-workspace'))
            ->assertNotFound();
    }

    public function test_logout_returns_user_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirectToRoute('login');

        $this->assertGuest();
    }

    /** @param array<int, string> $permissionKeys */
    private function userWithPermissions(array $permissionKeys): User
    {
        $this->seed(PermissionSeeder::class);

        $role = UserRole::STAFF;
        DB::table('permission_role')->where('role', $role->value)->delete();
        $now = now();
        $rows = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->map(fn (int $permissionId): array => [
                'permission_id' => $permissionId,
                'role' => $role->value,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('permission_role')->insert($rows);

        return User::factory()->create(['role' => $role->value]);
    }
}
