<?php

namespace Tests\Feature\PerformanceTalent;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PerformanceTalent\CriticalPositionController;
use App\Http\Controllers\PerformanceTalent\PerformanceTalentController;
use App\Http\Controllers\PerformanceTalent\SuccessorController;
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
            'description' => 'Pilih department untuk melihat struktur organisasi dan data formasinya.',
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
        'performance-talent.data-pegawai.index' => [
            'title' => 'Data Pegawai',
            'description' => 'Kelola data utama dan informasi pegawai.',
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
            $response = $this->get(route($routeName))
                ->assertOk()
                ->assertSee($page['title'])
                ->assertSee('aria-label="Breadcrumb"', escape: false);

            if ($routeName === 'performance-talent.critical-positions.index') {
                $response
                    ->assertSee('Daftar Critical Position')
                    ->assertSee('Pantau posisi kritis, tingkat prioritas, dan status keterisian dalam satu tampilan.');

                continue;
            }

            if ($routeName === 'performance-talent.data-pegawai.index') {
                $response
                    ->assertViewIs('performance-talent.show')
                    ->assertViewHas('featureView', 'performance-talent.features.data-pegawai')
                    ->assertSee('Daftar pegawai')
                    ->assertDontSee('Fitur sedang dalam tahap pengembangan');

                continue;
            }

            if ($routeName === 'performance-talent.successors.index') {
                $response
                    ->assertViewIs('performance-talent.show')
                    ->assertViewHas('featureView', 'performance-talent.successors.index')
                    ->assertSee($page['description'])
                    ->assertSee('Pilih Department')
                    ->assertDontSee('Fitur sedang dalam tahap pengembangan');

                continue;
            }

            $response
                ->assertSee($page['description'])
                ->assertSee('Fitur sedang dalam tahap pengembangan');
        }
    }

    public function test_successor_view_renders_the_twelve_requested_departments_in_order(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $departments = [
            'Market Planning & Development',
            'Sales',
            'Internal Audit',
            'Communication Legal & GA',
            'Finance & Accounting',
            'HC & GRC',
            'Production Planning & Control',
            'Mining & Power Plant',
            'Clinker & Cement Production',
            'Infrastructure',
            'Maintenance',
            'Project Management & Maint. Support',
        ];

        $response = $this->actingAs($superAdmin)
            ->get(route('performance-talent.successors.index'))
            ->assertOk()
            ->assertSee('Cari department...')
            ->assertSee('Struktur Organisasi -')
            ->assertSee('Total Formasi:')
            ->assertSeeInOrder($departments)
            ->assertDontSee('Comunication Legal &amp; GA', escape: false)
            ->assertDontSee('Producting Planning &amp; Control', escape: false);

        foreach ($departments as $department) {
            $response->assertSee($department);
        }
    }

    public function test_successor_view_renders_the_market_planning_organization_structure(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.successors.index'))
            ->assertOk()
            ->assertSee('19 Formasi')
            ->assertSee('General Manager')
            ->assertSee('Product, Pricing &amp; Promotion Management', escape: false)
            ->assertSee('Market Intelligence, Channel &amp; Sales Force Management', escape: false)
            ->assertSee('Staff of Pricing Policy &amp; Revenue Management', escape: false)
            ->assertSee('Staff of Trade Marketing &amp; Promotion Management', escape: false)
            ->assertSee('Staff of Product Advocacy')
            ->assertSee('Staff of Market Intelligence, Sales Planning &amp; Evaluation', escape: false)
            ->assertSee('Staff of Channel &amp; Sales Force Management', escape: false)
            ->assertSee('Staff of Sales Process Management')
            ->assertSee('successor-org-chart', escape: false);
    }

    public function test_critical_position_page_renders_the_database_table_structure(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($superAdmin)
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk();

        foreach (['No', 'Position', 'Level', 'Department', 'Direktorat', 'Criticality', 'Incumbent', 'SAP', 'Status'] as $column) {
            $response->assertSee('>'.$column.'</th>', escape: false);
        }

        $response
            ->assertSee('Aksi')
            ->assertSee('Belum ada Critical Position')
            ->assertSee('colspan="10"', escape: false)
            ->assertDontSee('Level Jabatan')
            ->assertDontSee('job_level')
            ->assertSee('table-fixed', escape: false)
            ->assertSee('min-w-[1000px]', escape: false)
            ->assertSee('overflow-x-auto', escape: false)
            ->assertDontSee('min-w-[1480px]', escape: false)
            ->assertDontSee('DUMMY-001')
            ->assertDontSee('Fitur sedang dalam tahap pengembangan');
    }

    public function test_admin_with_the_required_workspace_and_feature_permissions_can_open_every_page(): void
    {
        $this->grantPermissionsToRole(UserRole::ADMIN, [
            self::PERMISSION,
            'critical-positions.view',
            'successors.view',
        ]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, [
            'hc-od-career-management',
            'performance-talent-management',
        ]);

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

        $response
            ->assertDontSee('title="Master Organization"', escape: false)
            ->assertDontSee('title="Work Schedules"', escape: false)
            ->assertDontSee('title="Work Calendars"', escape: false);

        $html = $response->getContent();
        $menuPositions = array_map(
            static fn (array $page): int|false => strpos($html, 'title="'.$page['title'].'"'),
            self::PAGES,
        );

        foreach (array_values($menuPositions) as $index => $position) {
            $this->assertNotFalse($position);

            if ($index > 0) {
                $this->assertGreaterThan(array_values($menuPositions)[$index - 1], $position);
            }
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

            $expectedController = match ($routeName) {
                'performance-talent.critical-positions.index' => CriticalPositionController::class.'@index',
                'performance-talent.successors.index' => SuccessorController::class.'@index',
                'performance-talent.data-pegawai.index' => EmployeeController::class.'@index',
                default => PerformanceTalentController::class.'@__invoke',
            };

            $this->assertSame($expectedController, $route->getActionName());
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

    public function test_workspace_employee_page_reuses_employee_data_search_and_pagination(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        foreach (range(1, 21) as $number) {
            Employee::query()->create([
                'sap' => sprintf('PTM-SAP-%02d', $number),
                'personal_number' => sprintf('PTM-PN-%02d', $number),
            ]);
        }

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.data-pegawai.index', ['search' => 'PTM-PN-07']))
            ->assertOk()
            ->assertViewIs('performance-talent.show')
            ->assertViewHas('employees', fn ($employees): bool => $employees->total() === 1
                && $employees->sole()->personal_number === 'PTM-PN-07')
            ->assertViewHas('employeeIndexRoute', 'performance-talent.data-pegawai.index')
            ->assertSee('PTM-PN-07')
            ->assertDontSee('PTM-PN-08')
            ->assertSee('action="'.route('performance-talent.data-pegawai.index').'"', escape: false);

        $this->get(route('performance-talent.data-pegawai.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('employees', fn ($employees): bool => $employees->perPage() === 20
                && $employees->total() === 21
                && $employees->currentPage() === 2
                && $employees->count() === 1);
    }

    public function test_employee_workspace_route_requires_both_workspace_and_employee_permissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)
            ->get(route('performance-talent.data-pegawai.index'))
            ->assertForbidden();

        $this->grantPermissionsToRole(UserRole::ADMIN, [self::PERMISSION]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, [
            'hc-od-career-management',
            'performance-talent-management',
        ]);
        $this->removePermissionFromRole(UserRole::ADMIN, 'employees.view');

        $this->get(route('performance-talent.data-pegawai.index'))
            ->assertForbidden();

        $this->grantPermissionsToRole(UserRole::ADMIN, ['employees.view']);

        $this->get(route('performance-talent.data-pegawai.index'))
            ->assertOk();
    }

    public function test_legacy_employee_page_and_shared_modules_remain_available(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('data-pegawai'))
            ->assertOk()
            ->assertViewIs('pages.data-pegawai')
            ->assertSee('Daftar pegawai');

        $this->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('performance-talent.dashboard');

        $legacyEmployeeResponse = $this->get(route('data-pegawai'))->assertOk();
        $this->assertActiveNavigation($legacyEmployeeResponse->getContent(), 'Data Pegawai');

        foreach (['organization.index', 'work-schedules.index', 'work-calendars.index'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('title="Master Organization"', escape: false)
            ->assertSee('title="Work Schedules"', escape: false)
            ->assertSee('title="Work Calendars"', escape: false);
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

    /** @param array<int, string> $permissionKeys */
    private function grantPermissionsToRole(UserRole $role, array $permissionKeys): void
    {
        $now = now();
        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id', 'key');

        DB::table('permission_role')->insert(array_map(
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

    private function removePermissionFromRole(UserRole $role, string $permissionKey): void
    {
        $permissionId = Permission::query()->where('key', $permissionKey)->value('id');

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->where('role', $role->value)
            ->delete();

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
