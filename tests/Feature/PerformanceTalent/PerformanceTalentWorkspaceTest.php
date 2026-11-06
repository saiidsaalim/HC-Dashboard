<?php

namespace Tests\Feature\PerformanceTalent;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PerformanceTalent\CriticalPositionController;
use App\Http\Controllers\PerformanceTalent\PerformanceTalentController;
use App\Http\Controllers\PerformanceTalent\SuccessorController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
            'description' => 'Petakan talenta berdasarkan tingkat potential dan performance dalam 9-Box Talent Matrix.',
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
                    ->assertSee('Struktur organisasi, incumbent, coverage, dan successor selalu mengikuti data sumber terbaru.');

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

            if ($routeName === 'performance-talent.talent-pool.index') {
                $response
                    ->assertViewIs('performance-talent.show')
                    ->assertViewHas('featureView', 'performance-talent.features.talent-pool')
                    ->assertDontSee($page['description'])
                    ->assertSee('Pemetaan talenta')
                    ->assertDontSee('Fitur sedang dalam tahap pengembangan');

                continue;
            }

            $response
                ->assertSee($page['description'])
                ->assertSee('Fitur sedang dalam tahap pengembangan');
        }
    }

    public function test_talent_pool_renders_the_nine_box_matrix_in_the_required_order(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($superAdmin)
            ->get(route('performance-talent.talent-pool.index'))
            ->assertOk()
            ->assertSee('aria-label="Potential meningkat ke atas"', escape: false)
            ->assertSee('aria-label="Performance meningkat ke kanan"', escape: false)
            ->assertSee('talent-matrix-scroll', escape: false)
            ->assertSee('Geser horizontal untuk melihat seluruh matriks.')
            ->assertSeeInOrder([
                'data-box="07"',
                'data-box="08"',
                'data-box="09"',
                'data-box="04"',
                'data-box="05"',
                'data-box="06"',
                'data-box="01"',
                'data-box="02"',
                'data-box="03"',
            ], escape: false)
            ->assertSeeInOrder([
                'data-display-number="7"',
                'data-display-number="8"',
                'data-display-number="9"',
                'data-display-number="4"',
                'data-display-number="5"',
                'data-display-number="6"',
                'data-display-number="1"',
                'data-display-number="2"',
                'data-display-number="3"',
            ], escape: false)
            ->assertSeeInOrder([
                'Diam-Diam Jago',
                'Lagi Ngebut',
                'Rising Star',
                'Butuh Arah',
                'Tim Andalan',
                'Gaspol',
                'Perlu Booster',
                'Aman Terkendali',
                'Jagonya Eksekusi',
            ])
            ->assertSee('Pilih salah satu kotak')
            ->assertDontSee('desain_3_talent_matrix_3x3.png');

        $html = $response->getContent();

        $this->assertSame(9, substr_count($html, 'data-talent-box'));
        $this->assertSame(9, substr_count($html, 'Belum ada talenta'));
        $this->assertSame(9, substr_count($html, 'talent-matrix-card__illustration'));
        $this->assertMatchesRegularExpression('/data-box="07"[\s\S]*?data-potential="Tinggi"[\s\S]*?data-performance="Rendah"/', $html);
        $this->assertMatchesRegularExpression('/data-box="03"[\s\S]*?data-potential="Rendah"[\s\S]*?data-performance="Tinggi"/', $html);

        foreach ([
            '01_diam_diam_jago.png',
            '02_lagi_ngebut.png',
            '03_rising_star.png',
            '04_butuh_arah.png',
            '05_tim_andalan.png',
            '06_gaspol.png',
            '07_perlu_booster.png',
            '08_aman_terkendali.png',
            '09_jagonya_eksekusi.png',
        ] as $illustration) {
            $this->assertFileExists(public_path('images/talent-matrix/characters/'.$illustration));
        }

        $this->assertCount(9, glob(public_path('images/talent-matrix/icons/*.svg')) ?: []);
    }

    public function test_talent_pool_renders_supplied_talent_counts_and_details(): void
    {
        $html = view('performance-talent.features.talent-pool', [
            'talentMatrix' => [
                '09' => [
                    ['name' => 'Talent Satu', 'position' => 'Senior Analyst'],
                    ['name' => 'Talent Dua', 'position' => 'Lead Engineer'],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Total talent terpetakan', $html);
        $this->assertStringContainsString('2 talenta', $html);
        $this->assertStringContainsString('Talent Satu', $html);
        $this->assertStringContainsString('Senior Analyst', $html);
    }

    public function test_successor_view_renders_the_master_organization_hierarchy(): void
    {
        $department = Department::query()->create([
            'code' => 'SALES',
            'name' => 'Department of Sales',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'CORP',
            'name' => 'Unit of Corporate Sales',
            'active' => true,
        ]);
        $section = Section::query()->create([
            'unit_id' => $unit->id,
            'code' => 'KEY',
            'name' => 'Section of Key Account',
            'active' => true,
        ]);
        Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'GM-SALES',
            'name' => 'General Manager of Sales',
            'job_level' => 'General Manager',
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
        Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'SM-SALES',
            'name' => 'Senior Manager of Corporate Sales',
            'job_level' => 'Senior Manager',
            'formation_count' => 1,
            'sort_order' => 2,
            'active' => true,
        ]);
        Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'MGR-SALES',
            'name' => 'Manager of Key Account',
            'job_level' => 'Manager',
            'formation_count' => 1,
            'sort_order' => 3,
            'active' => true,
        ]);
        $position = Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'KEY-OFFICER',
            'name' => 'Key Account Officer',
            'job_level' => 'Staff',
            'formation_count' => 4,
            'sort_order' => 4,
            'active' => true,
        ]);
        foreach (range(1, 3) as $number) {
            Employee::query()->forceCreate([
                'sap' => 'SALES-'.$number,
                'name' => 'Sales Employee '.$number,
                'position_id' => $position->id,
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->get(route('performance-talent.successors.index', ['department' => $department->id]))
            ->assertOk()
            ->assertSee('Cari department...')
            ->assertSee('data-successor-department-picker', escape: false)
            ->assertSee('data-successor-department-card', escape: false)
            ->assertSee('Department of Sales')
            ->assertSeeInOrder([
                'General Manager of Sales',
                'Senior Manager of Corporate Sales',
                'Manager of Key Account',
                'Key Account Officer',
            ])
            ->assertDontSee('Unit of Corporate Sales')
            ->assertDontSee('Section of Key Account')
            ->assertSee('Formasi')
            ->assertSee('Terisi')
            ->assertSee('>3</span>', escape: false)
            ->assertSee('Data langsung dari Master Organization.')
            ->assertSee('successor-org-chart', escape: false);

        $this->assertFalse(Schema::hasTable('successor_structure_departments'));
        $this->assertFalse(Schema::hasTable('successor_structure_positions'));
    }

    public function test_critical_position_page_renders_the_database_table_structure(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($superAdmin)
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk();

        foreach (['No', 'Position', 'SAP Position ID', 'Level', 'Department/Direktorat', 'Criticality', 'Incumbent', 'Formasi', 'Coverage', 'Successor', 'Review Berikutnya', 'Aksi'] as $column) {
            $response->assertSee('>'.$column.'</th>', escape: false);
        }

        $response
            ->assertSee('Belum ada Critical Position')
            ->assertSee('colspan="12"', escape: false)
            ->assertSee('Level Jabatan')
            ->assertSee('min-w-[1780px]', escape: false)
            ->assertSee('overflow-x-auto', escape: false)
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
            ->assertSee('title="Master Organization"', escape: false)
            ->assertSee('href="'.route('organization.index').'"', escape: false)
            ->assertDontSee('title="Work Schedules"', escape: false)
            ->assertDontSee('title="Work Calendars"', escape: false);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'href="'.route('organization.index').'"'));

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

        $this->assertGreaterThan(
            $menuPositions['performance-talent.data-pegawai.index'],
            strpos($html, 'title="Master Organization"'),
        );
    }

    public function test_workspace_organization_menu_and_route_require_organization_permission(): void
    {
        $this->grantPermissionsToRole(UserRole::ADMIN, [self::PERMISSION]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, [
            'hc-od-career-management',
            'performance-talent-management',
        ]);

        $this->actingAs($admin)
            ->withSession(['active_workspace' => 'performance-talent-management'])
            ->get(route('performance-talent.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('organization.index').'"', escape: false);

        $this->get(route('organization.index'))->assertForbidden();

        $this->grantPermissionsToRole(UserRole::ADMIN, ['organization.view']);

        $this->get(route('performance-talent.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('organization.index').'"', escape: false);

        $this->get(route('organization.index'))->assertOk();
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

    public function test_succession_fields_are_visible_only_on_performance_talent_employee_detail(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::query()->create([
            'sap' => 'PTM-SUCCESSION-VISIBILITY',
            'talent_mapping' => 8,
            'succession_readiness' => 'ready in one year',
            'idp_percentage' => 75.5,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.data-pegawai.index'))
            ->assertOk()
            ->assertViewHas('employeeShowRoute', 'performance-talent.data-pegawai.show')
            ->assertSee(route('performance-talent.data-pegawai.show', $employee), escape: false)
            ->assertDontSee('Criticality')
            ->assertDontSee('Status Formasi')
            ->assertDontSee('Talent Mapping')
            ->assertDontSee('Succession Readiness')
            ->assertDontSee('IDP (%)')
            ->assertSee(route('performance-talent.data-pegawai.print'), escape: false)
            ->assertSee(route('performance-talent.data-pegawai.export'), escape: false);

        $this->get(route('performance-talent.data-pegawai.show', $employee))
            ->assertOk()
            ->assertViewIs('employees.show')
            ->assertViewHas('showPerformanceTalentFields', true)
            ->assertViewHas('employeeIndexRoute', 'performance-talent.data-pegawai.index')
            ->assertDontSee('Criticality')
            ->assertDontSee('Status Formasi')
            ->assertSee('Talent Mapping')
            ->assertSee('Succession Readiness')
            ->assertSee('IDP (%)')
            ->assertSee('Ready in one year')
            ->assertSee('75,50%');

        $this->get(route('data-pegawai'))
            ->assertOk()
            ->assertViewHas('employeeShowRoute', 'employees.show')
            ->assertDontSee('Criticality')
            ->assertDontSee('Status Formasi')
            ->assertDontSee('Talent Mapping')
            ->assertDontSee('Succession Readiness')
            ->assertDontSee('IDP (%)');

        $this->get(route('employees.show', $employee))
            ->assertOk()
            ->assertViewHas('showPerformanceTalentFields', false)
            ->assertViewHas('employeeIndexRoute', 'data-pegawai')
            ->assertDontSee('Criticality')
            ->assertDontSee('Status Formasi')
            ->assertDontSee('Talent Mapping')
            ->assertDontSee('Succession Readiness')
            ->assertDontSee('IDP (%)');
    }

    public function test_employee_detail_handles_null_values_and_long_content_safely(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::query()->create([
            'sap' => 'PTM-DETAIL-NULL',
            'personal_number' => 'Nama Pegawai Dengan Identitas Yang Sangat Panjang',
            'position' => 'Posisi dengan nama yang sangat panjang untuk memastikan konten tetap membungkus di dalam kartu profil pegawai',
            'txt_dept' => 'Departemen dengan nama yang sangat panjang untuk pengujian tampilan responsif',
            'email' => 'pegawai.dengan.alamat.email.yang.sangat.panjang@example.test',
            'alamat' => 'Alamat pegawai yang sangat panjang dan tetap harus berada di dalam kartu tanpa menyebabkan overflow horizontal pada halaman detail.',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.data-pegawai.show', $employee))
            ->assertOk()
            ->assertSee(route('performance-talent.data-pegawai.index'), escape: false)
            ->assertSee('<span aria-hidden="true">NP</span>', escape: false)
            ->assertSee('Nama Pegawai Dengan Identitas Yang Sangat Panjang')
            ->assertSee('pegawai.dengan.alamat.email.yang.sangat.panjang@example.test')
            ->assertSee('aria-valuenow="0"', escape: false)
            ->assertSee('style="width: 0%"', escape: false)
            ->assertSee('break-all', escape: false)
            ->assertSee('break-words', escape: false);
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
