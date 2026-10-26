<?php

namespace Tests\Feature\PerformanceTalent;

use App\Enums\UserRole;
use App\Http\Controllers\PerformanceTalent\PerformanceTalentController;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerformanceTalentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION = 'workspace.performance-talent-management.view';

    /** @var array<string, array{title: string, description: string}> */
    private const PAGES = [
        'performance-talent.dashboard' => [
            'title' => 'Dashboard',
            'description' => 'Ringkasan Performance & Talent Management akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.critical-positions.index' => [
            'title' => 'Critical Position',
            'description' => 'Pengelolaan posisi kritis akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.successors.index' => [
            'title' => 'Successor View',
            'description' => 'Informasi kandidat penerus akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.talent-pool.index' => [
            'title' => 'Talent Pool',
            'description' => 'Pengelolaan talent pool akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.development-plans.index' => [
            'title' => 'Development Plan',
            'description' => 'Rencana pengembangan pegawai akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.reports.index' => [
            'title' => 'Report',
            'description' => 'Laporan Performance & Talent Management akan tersedia pada tahap pengembangan berikutnya.',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_super_admin_can_open_every_page_from_the_workspace(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('performance-talent.dashboard')
            ->assertSessionHas('active_workspace', 'performance-talent-management');

        foreach (self::PAGES as $routeName => $page) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($page['title'])
                ->assertSee($page['description'])
                ->assertSee('Fitur sedang dalam tahap pengembangan')
                ->assertSee('aria-label="Breadcrumb"', escape: false);
        }
    }

    public function test_admin_with_the_workspace_permission_can_open_every_page(): void
    {
        $this->grantPermissionToRole(UserRole::ADMIN);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('performance-talent.dashboard');

        foreach (array_keys(self::PAGES) as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_admin_without_the_workspace_permission_is_forbidden_from_every_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertForbidden();

        foreach (array_keys(self::PAGES) as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_are_forbidden_from_every_page(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role->value]);

        $this->actingAs($user)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertForbidden();

        foreach (array_keys(self::PAGES) as $routeName) {
            $this->get(route($routeName))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_every_page(): void
    {
        $this->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('login');

        foreach (array_keys(self::PAGES) as $routeName) {
            $this->get(route($routeName))->assertRedirectToRoute('login');
        }
    }

    public function test_authorized_workspace_sidebar_contains_every_submenu_with_correct_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('performance-talent.dashboard');

        $response = $this->get(route('performance-talent.dashboard'))->assertOk();

        $response->assertSee('Performance &amp; Talent Management', escape: false);

        foreach (self::PAGES as $routeName => $page) {
            $response
                ->assertSee('title="'.$page['title'].'"', escape: false)
                ->assertSee('href="'.route($routeName).'"', escape: false);
        }
    }

    public function test_workspace_menu_is_hidden_from_a_user_without_access(): void
    {
        $staff = User::factory()->create(['role' => UserRole::STAFF->value]);

        $response = $this->actingAs($staff)
            ->withSession(['active_workspace' => 'performance-talent-management'])
            ->get(route('dashboard'))
            ->assertOk();

        foreach (array_keys(self::PAGES) as $routeName) {
            $response->assertDontSee('href="'.route($routeName).'"', escape: false);
        }
    }

    public function test_each_workspace_page_has_the_correct_active_sidebar_state(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->withSession(['active_workspace' => 'performance-talent-management']);

        foreach (self::PAGES as $routeName => $page) {
            $response = $this->get(route($routeName))->assertOk();

            $this->assertActiveNavigation($response->getContent(), $page['title']);
        }
    }

    public function test_performance_talent_menu_is_not_rendered_in_another_workspace(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('active_workspace', 'hc-od-career-management');

        $response = $this->get(route('dashboard'))->assertOk();

        foreach (array_keys(self::PAGES) as $routeName) {
            $response->assertDontSee('href="'.route($routeName).'"', escape: false);
        }
    }

    public function test_workspace_routes_are_unique_and_use_the_expected_controller(): void
    {
        foreach (array_keys(self::PAGES) as $routeName) {
            $namedRoutes = collect(Route::getRoutes())
                ->filter(fn (RouteDefinition $route): bool => $route->getName() === $routeName);
            $route = $namedRoutes->sole();

            $this->assertSame(PerformanceTalentController::class.'@__invoke', $route->getActionName());
            $this->assertSame(1, collect(Route::getRoutes())->filter(
                fn (RouteDefinition $candidate): bool => $candidate->uri() === $route->uri()
                    && in_array('GET', $candidate->methods(), true),
            )->count());
        }
    }

    public function test_workspace_access_does_not_change_the_user_employee_relationship(): void
    {
        $employee = Employee::query()->create(['sap' => 'SAP-PTM-RELATION']);
        $superAdmin = User::factory()->create([
            'employee_id' => $employee->id,
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.dashboard'))
            ->assertOk();

        $this->assertTrue($superAdmin->fresh()->employee->is($employee));
    }

    /** @return array<string, array{UserRole}> */
    public static function unauthorizedRoles(): array
    {
        return [
            'manager' => [UserRole::MANAGER],
            'staff' => [UserRole::STAFF],
            'member' => [UserRole::MEMBER],
        ];
    }

    private function grantPermissionToRole(UserRole $role): void
    {
        DB::table('permission_role')->insert([
            'permission_id' => Permission::query()->where('key', self::PERMISSION)->valueOrFail('id'),
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RolePermissionCache::class)->clear();
    }

    private function assertActiveNavigation(string $html, string $title): void
    {
        $encodedTitle = htmlspecialchars($title, ENT_QUOTES);

        $this->assertMatchesRegularExpression(
            '/<a\s+href="[^"]+"\s+title="'.preg_quote($encodedTitle, '/').'"\s+aria-current="page"/',
            $html,
        );
    }
}
