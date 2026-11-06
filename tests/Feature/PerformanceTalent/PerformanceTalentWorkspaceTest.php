<?php

namespace Tests\Feature\PerformanceTalent;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PerformanceTalent\CriticalPositionController;
use App\Http\Controllers\PerformanceTalent\PerformanceTalentController;
use App\Http\Controllers\PerformanceTalent\SuccessorController;
use App\Models\Department;
use App\Models\Directorate;
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
                    ->assertSee('Data selalu mengikuti Master Organization, incumbent, dan Successor View.');

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
                    ->assertSee('Pemetaan karyawan berdasarkan potensi dan performa')
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

        foreach ([1, 5, 9] as $box) {
            Employee::query()->create([
                'sap' => 'TALENT-BOX-'.$box,
                'personal_number' => 'Talent Box '.$box,
                'talent_mapping' => $box,
            ]);
        }

        Employee::query()->create([
            'sap' => 'TALENT-UNMAPPED',
            'personal_number' => 'Talent Belum Dipetakan',
            'talent_mapping' => null,
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('performance-talent.talent-pool.index'))
            ->assertOk()
            ->assertViewHas('activeBox', 9)
            ->assertViewHas('summary', [
                'total' => 3,
                'high_potential' => 1,
                'high_performance' => 1,
            ])
            ->assertViewHas('unmappedCount', 1)
            ->assertViewHas('talentCounts', fn ($counts): bool => $counts->get(1) === 1
                && $counts->get(5) === 1
                && $counts->get(9) === 1)
            ->assertSee('aria-label="Potential meningkat ke atas"', escape: false)
            ->assertSee('aria-label="Performance meningkat ke kanan"', escape: false)
            ->assertSee('talent-pool-matrix-scroll', escape: false)
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
            ->assertSee('Talent Box 9')
            ->assertSee('Belum Dipetakan')
            ->assertDontSee('talent-matrix-card__illustration', escape: false);

        $html = $response->getContent();

        $this->assertSame(9, substr_count($html, 'data-talent-box'));
        $this->assertMatchesRegularExpression('/data-box="07"[\s\S]*?data-potential="Tinggi"[\s\S]*?data-performance="Rendah"/', $html);
        $this->assertMatchesRegularExpression('/data-box="03"[\s\S]*?data-potential="Rendah"[\s\S]*?data-performance="Tinggi"/', $html);
        $this->assertCount(9, glob(public_path('images/talent-matrix/icons/*.svg')) ?: []);
    }

    public function test_talent_pool_filters_searches_and_paginates_database_employees(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $directorate = Directorate::query()->create(['code' => 'TAL-DIR', 'name' => 'Talent Directorate', 'active' => true]);
        $department = Department::query()->create([
            'directorate_id' => $directorate->id,
            'code' => 'TAL-DEPT',
            'name' => 'Talent Department',
            'active' => true,
        ]);

        foreach (range(1, 11) as $number) {
            Employee::query()->create([
                'sap' => sprintf('TALENT-SAP-%02d', $number),
                'personal_number' => sprintf('Nama Talent %02d', $number),
                'talent_mapping' => 9,
                'directorate_id' => $directorate->id,
                'department_id' => $department->id,
            ]);
        }

        Employee::query()->create([
            'sap' => 'OUTSIDE-FILTER',
            'personal_number' => 'Talent Luar Filter',
            'talent_mapping' => 9,
        ]);

        $filterQuery = [
            'box' => 9,
            'category' => 'talent',
            'directorate_id' => $directorate->id,
            'department_id' => $department->id,
        ];

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.talent-pool.index', $filterQuery))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 11)
            ->assertViewHas('categoryDistribution', fn ($distribution): bool => $distribution->get('talent')['count'] === 11
                && $distribution->sum('count') === 11)
            ->assertViewHas('activeTalents', fn ($employees): bool => $employees->total() === 11
                && $employees->perPage() === 10
                && $employees->count() === 10)
            ->assertSee('Nama Talent 01')
            ->assertDontSee('Talent Luar Filter');

        $this->get(route('performance-talent.talent-pool.index', [...$filterQuery, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('activeTalents', fn ($employees): bool => $employees->currentPage() === 2
                && $employees->count() === 1)
            ->assertSee('directorate_id='.$directorate->id, escape: false)
            ->assertSee('department_id='.$department->id, escape: false)
            ->assertSee('category=talent', escape: false)
            ->assertSee('box=9', escape: false);

        $this->get(route('performance-talent.talent-pool.index', ['search' => 'TALENT-SAP-07', 'box' => 9]))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 1)
            ->assertSee('Nama Talent 07')
            ->assertDontSee('Nama Talent 08');
    }

    public function test_talent_pool_classifies_and_filters_employee_mapping_categories(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        foreach (range(1, 9) as $box) {
            Employee::query()->create([
                'sap' => 'CATEGORY-BOX-'.$box,
                'personal_number' => 'Pegawai Box '.$box,
                'talent_mapping' => $box,
            ]);
        }

        foreach (range(1, 2) as $number) {
            Employee::query()->create([
                'sap' => 'CATEGORY-NULL-'.$number,
                'personal_number' => 'Pegawai Belum Dipetakan '.$number,
                'talent_mapping' => null,
            ]);
        }

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.talent-pool.index'))
            ->assertOk()
            ->assertViewHas('totalEmployeeCount', 11)
            ->assertViewHas('invalidMappingCount', 0)
            ->assertViewHas('categoryDistribution', function ($distribution): bool {
                return $distribution->get('talent')['count'] === 4
                    && $distribution->get('non_talent')['count'] === 5
                    && $distribution->get('not_available')['count'] === 2
                    && $distribution->sum('count') === 11;
            })
            ->assertSee('Distribusi Talent')
            ->assertSee('Komposisi pemetaan pegawai');

        $this->get(route('performance-talent.talent-pool.index', ['category' => 'talent', 'box' => 1]))
            ->assertOk()
            ->assertViewHas('activeCategory', 'talent')
            ->assertViewHas('activeBox', 9)
            ->assertViewHas('activeTalents', fn ($employees): bool => $employees->total() === 1
                && $employees->sole()->talent_mapping === 9)
            ->assertSee('Pegawai Box 9')
            ->assertDontSee('Pegawai Box 1');

        $this->get(route('performance-talent.talent-pool.index', ['category' => 'non_talent', 'box' => 9]))
            ->assertOk()
            ->assertViewHas('activeCategory', 'non_talent')
            ->assertViewHas('activeBox', 7)
            ->assertViewHas('activeTalents', fn ($employees): bool => $employees->total() === 1
                && $employees->sole()->talent_mapping === 7)
            ->assertSee('Pegawai Box 7')
            ->assertDontSee('Pegawai Box 9');

        $this->get(route('performance-talent.talent-pool.index', ['category' => 'not_available']))
            ->assertOk()
            ->assertViewHas('activeCategory', 'not_available')
            ->assertViewHas('activeBox', null)
            ->assertViewHas('activeTalents', fn ($employees): bool => $employees->total() === 2
                && $employees->every(fn (Employee $employee): bool => $employee->talent_mapping === null))
            ->assertSee('Pegawai Belum Dipetakan 1')
            ->assertSee('Pegawai Belum Dipetakan 2')
            ->assertDontSee('Pegawai Box 9');

        $this->get(route('performance-talent.talent-pool.index', ['category' => 'tidak-valid']))
            ->assertOk()
            ->assertViewHas('activeCategory', 'all');
    }

    public function test_talent_pool_reports_invalid_mapping_and_handles_empty_percentages(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->get(route('performance-talent.talent-pool.index'))
            ->assertOk()
            ->assertViewHas('categoryDistribution', fn ($distribution): bool => $distribution->every(
                fn (array $category): bool => $category['count'] === 0 && $category['percentage'] === 0.0,
            ));

        Employee::query()->create([
            'sap' => 'INVALID-MAPPING',
            'personal_number' => 'Pegawai Mapping Tidak Valid',
            'talent_mapping' => 10,
        ]);

        $this->get(route('performance-talent.talent-pool.index'))
            ->assertOk()
            ->assertViewHas('totalEmployeeCount', 1)
            ->assertViewHas('invalidMappingCount', 1)
            ->assertViewHas('categoryDistribution', fn ($distribution): bool => $distribution->sum('count') === 0)
            ->assertSee('1 pegawai memiliki Talent Mapping tidak valid.');
    }

    public function test_managerial_category_uses_master_position_job_levels_for_every_talent_box(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $directorate = Directorate::query()->create(['code' => 'MGT-DIR', 'name' => 'Managerial Directorate', 'active' => true]);
        $department = Department::query()->create([
            'directorate_id' => $directorate->id,
            'code' => 'MGT-DEPT',
            'name' => 'Managerial Department',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'MGT-UNIT',
            'name' => 'Managerial Unit',
            'active' => true,
        ]);
        $managerialEmployees = [
            [Position::JOB_LEVEL_GENERAL_MANAGER, 9, 'General Manager Talent'],
            [Position::JOB_LEVEL_SENIOR_MANAGER, 5, 'Senior Manager Talent'],
            [Position::JOB_LEVEL_MANAGER, 1, 'Manager Talent'],
        ];

        foreach ($managerialEmployees as [$jobLevel, $talentMapping, $employeeName]) {
            $position = Position::query()->create([
                'unit_id' => $unit->id,
                'code' => 'MGT-'.$talentMapping,
                'name' => $jobLevel,
                'job_level' => $jobLevel,
                'active' => true,
            ]);
            Employee::query()->create([
                'sap' => 'MGT-SAP-'.$talentMapping,
                'personal_number' => $employeeName,
                'talent_mapping' => $talentMapping,
                'position_id' => $position->id,
            ]);
        }

        $staffPosition = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'MGT-STAFF',
            'name' => 'Staff Position',
            'job_level' => Position::JOB_LEVEL_STAFF,
            'active' => true,
        ]);
        Employee::query()->create([
            'sap' => 'MGT-STAFF-SAP',
            'personal_number' => 'Staff Bukan Managerial',
            'talent_mapping' => 9,
            'position_id' => $staffPosition->id,
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('performance-talent.talent-pool.index', ['category' => 'managerial']))
            ->assertOk()
            ->assertViewHas('activeCategory', 'managerial')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 3)
            ->assertViewHas('talentCounts', fn ($counts): bool => $counts->get(1) === 1
                && $counts->get(5) === 1
                && $counts->get(9) === 1)
            ->assertViewHas('categoryDistribution', fn ($distribution): bool => $distribution->get('talent')['count'] === 2
                && $distribution->get('non_talent')['count'] === 1
                && $distribution->sum('count') === 3)
            ->assertViewHas('activeTalents', fn ($employees): bool => $employees->total() === 1
                && $employees->sole()->personal_number === 'General Manager Talent')
            ->assertSee('Managerial')
            ->assertSee('General Manager Talent')
            ->assertDontSee('Staff Bukan Managerial');

        $this->assertSame(0, substr_count($response->getContent(), 'is-dimmed'));
    }

    public function test_talent_pool_hides_employee_identity_without_employee_view_permission(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->grantPermissionsToRole(UserRole::ADMIN, [self::PERMISSION]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, ['performance-talent-management']);
        $this->removePermissionFromRole(UserRole::ADMIN, 'employees.view');
        $employee = Employee::query()->create([
            'sap' => 'RESTRICTED-TALENT',
            'personal_number' => 'Nama Talent Rahasia',
            'talent_mapping' => 9,
        ]);

        $this->actingAs($admin)
            ->get(route('performance-talent.talent-pool.index'))
            ->assertOk()
            ->assertViewHas('canViewEmployees', false)
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 1)
            ->assertSee('Detail pegawai dibatasi')
            ->assertDontSee('Nama Talent Rahasia')
            ->assertDontSee(route('performance-talent.data-pegawai.show', $employee), escape: false);
    }

    public function test_talent_pool_query_count_does_not_grow_with_the_number_of_employees(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        Employee::query()->create([
            'sap' => 'QUERY-TALENT-01',
            'personal_number' => 'Query Talent 01',
            'talent_mapping' => 9,
        ]);

        $this->actingAs($superAdmin);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('performance-talent.talent-pool.index'))->assertOk();
        $singleEmployeeQueryCount = count(DB::getQueryLog());

        foreach (range(2, 25) as $number) {
            Employee::query()->create([
                'sap' => sprintf('QUERY-TALENT-%02d', $number),
                'personal_number' => sprintf('Query Talent %02d', $number),
                'talent_mapping' => ($number % 9) + 1,
            ]);
        }

        DB::flushQueryLog();
        $this->get(route('performance-talent.talent-pool.index'))->assertOk();
        $multipleEmployeeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            $singleEmployeeQueryCount + 1,
            $multipleEmployeeQueryCount,
            'Jumlah query Talent Pool bertambah mengikuti jumlah pegawai.',
        );
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

        foreach (['No', 'Position &amp; SAP ID', 'Organisasi', 'Criticality', 'Incumbent', 'Formasi &amp; Coverage', 'Successor', 'Review Berikutnya', 'Aksi'] as $column) {
            $response->assertSee('>'.$column.'</th>', escape: false);
        }

        $response
            ->assertSee('Belum ada Critical Position')
            ->assertSee('colspan="9"', escape: false)
            ->assertSee('aria-label="Level Jabatan"', escape: false)
            ->assertSee('min-w-[1320px]', escape: false)
            ->assertSee('overflow-x-auto', escape: false)
            ->assertSee('data-critical-position-summary', escape: false)
            ->assertSee('data-critical-position-kpi', escape: false)
            ->assertSee('data-critical-position-analytics', escape: false)
            ->assertSee('data-critical-position-filter', escape: false)
            ->assertSee('data-critical-position-list', escape: false)
            ->assertSee('data-criticality-chart', escape: false)
            ->assertSee('data-coverage-chart', escape: false)
            ->assertSee('data-successor-chart', escape: false)
            ->assertSee('xl:col-span-5 xl:justify-end', escape: false)
            ->assertDontSee('hidden items-center justify-center text-slate-400 xl:flex xl:col-span-1', escape: false)
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
