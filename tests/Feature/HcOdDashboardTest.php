<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\FitProperActivity;
use App\Models\Permission;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HcOdDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 7)->setTime(10, 0));
    }

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_empty_dashboard_renders_zero_counts_and_six_months_without_illustrative_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Organisasi &amp; Karier', false)
            ->assertSee('Ringkasan tenaga kerja dan proses pengembangan karier')
            ->assertSee('data-hc-od-dashboard', false)
            ->assertDontSee('Data ilustrasi')
            ->assertDontSee('Talent Pool')
            ->assertDontSee('Critical Position')
            ->assertDontSee('Successor View')
            ->assertViewHas('totalEmployees', 0)
            ->assertViewHas('pendingTotal', 0);

        $this->assertSame(0, $response->viewData('wlaSummary')['running']);
        $this->assertSame(0, $response->viewData('fitProperSummary')['activeParticipants']);
        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], array_column($response->viewData('movementMonths'), 'key'));

        foreach ($response->viewData('movements') as $movement) {
            $this->assertSame([0, 0, 0, 0, 0, 0], $movement['values']);
            $this->assertSame(0, $movement['pending']);
        }
    }

    public function test_employee_wla_and_fit_proper_counts_use_their_actual_statuses_consistently(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        Employee::query()->create(['sap' => 'DASH-001']);
        Employee::query()->create(['sap' => 'DASH-002']);
        Employee::query()->create(['sap' => 'DASH-003']);
        $this->createAssessments($user, [WlaAssessmentStatus::Draft, WlaAssessmentStatus::Draft, WlaAssessmentStatus::Final]);
        $this->createActivity($user, 'Draft', 2);
        $this->createActivity($user, 'Berjalan', 1);
        $this->createActivity($user, 'Berjalan', 2);
        $this->createActivity($user, 'Selesai', 4);
        $moduleQueryCount = 0;
        DB::listen(function ($query) use (&$moduleQueryCount): void {
            if (preg_match('/\b(employees|wla_assessments|fit_proper_activities|fit_proper_participants|employee_mutations|employee_promotions|employee_demotions)\b/', $query->sql)) {
                $moduleQueryCount++;
            }
        });

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalEmployees', 3)
            ->assertSee('href="'.route('wla').'"', false)
            ->assertDontSee('Dalam Review');

        $wla = $response->viewData('wlaSummary');
        $this->assertSame(2, $wla['running']);
        $this->assertSame(3, $wla['total']);
        $this->assertSame(['draft' => 2, 'final' => 1], array_column($wla['statuses'], 'count', 'status'));
        $this->assertSame($wla['running'], array_column($wla['statuses'], 'count', 'status')['draft']);

        $fitProper = $response->viewData('fitProperSummary');
        $this->assertSame(3, $fitProper['activeParticipants']);
        $this->assertSame(4, $fitProper['total']);
        $this->assertSame(['Draft' => 1, 'Berjalan' => 2, 'Selesai' => 1], array_column($fitProper['statuses'], 'count', 'status'));
        $this->assertLessThanOrEqual(7, $moduleQueryCount);
    }

    public function test_career_chart_counts_only_final_effective_movements_and_pending_total_matches_rows(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::query()->create(['sap' => 'DASH-MOVEMENT']);

        foreach ([EmployeeMutation::class, EmployeePromotion::class, EmployeeDemotion::class] as $modelClass) {
            foreach (['2026-06-01', '2026-10-12', '2026-11-07', '2026-05-31', '2026-11-08'] as $date) {
                $this->createMovement($modelClass, $employee, 'Final', $date);
            }

            foreach (['Draft', 'Returned', 'Approved', 'Rejected', 'Cancelled'] as $status) {
                $this->createMovement($modelClass, $employee, $status, '2026-11-01');
            }
        }

        $this->createMovement(EmployeeMutation::class, $employee, 'Pending', '2026-11-01');
        $this->createMovement(EmployeeMutation::class, $employee, 'Pending', '2026-05-01');
        $this->createMovement(EmployeePromotion::class, $employee, 'Pending', '2026-11-01');
        $this->createMovement(EmployeePromotion::class, $employee, 'Waiting Approval', '2026-11-01');
        $this->createMovement(EmployeePromotion::class, $employee, 'Waiting Approval', '2026-12-01');
        $this->createMovement(EmployeePromotion::class, $employee, 'Waiting Approval', '2026-05-01');
        $this->createMovement(EmployeeDemotion::class, $employee, 'Pending', '2026-11-01');

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pendingTotal', 6)
            ->assertSee('data-career-chart', false);

        $movements = $response->viewData('movements');
        $this->assertSame(['Mutasi' => 2, 'Promosi' => 3, 'Demosi' => 1], array_column($movements, 'pending', 'label'));
        $this->assertSame($response->viewData('pendingTotal'), array_sum(array_column($movements, 'pending')));

        foreach ($movements as $movement) {
            $this->assertSame([1, 0, 0, 0, 1, 1], $movement['values']);
        }
    }

    public function test_fit_proper_summary_respects_creator_pic_examiner_and_view_all_scope(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $other = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $this->createActivity($admin, 'Berjalan', 1);
        $picActivity = $this->createActivity($other, 'Berjalan', 2);
        $picActivity->pics()->attach($admin);
        $examinerActivity = $this->createActivity($other, 'Berjalan', 3);
        $examinerActivity->examiners()->create(['user_id' => $admin->id, 'name' => $admin->name]);
        $this->createActivity($other, 'Berjalan', 10);
        $this->createActivity($admin, 'Draft', 4);

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $summary = $response->viewData('fitProperSummary');
        $this->assertSame(6, $summary['activeParticipants']);
        $this->assertSame(4, $summary['total']);
        $this->assertSame(['Draft' => 1, 'Berjalan' => 3, 'Selesai' => 0], array_column($summary['statuses'], 'count', 'status'));

        $this->grantRolePermissions(UserRole::ADMIN, ['fit-proper.view-all']);
        $summary = $this->get(route('dashboard'))->assertOk()->viewData('fitProperSummary');
        $this->assertSame(16, $summary['activeParticipants']);
        $this->assertSame(5, $summary['total']);
    }

    public function test_modules_without_view_permission_are_not_queried_or_sent_in_dashboard_payload(): void
    {
        $user = User::factory()->create(['role' => UserRole::MEMBER->value]);
        $this->replaceRolePermissions(UserRole::MEMBER, ['dashboard.view', 'workspace.hc-od-career-management.view']);
        $moduleQueries = [];
        DB::listen(function ($query) use (&$moduleQueries): void {
            if (preg_match('/\b(employees|wla_assessments|fit_proper_activities|fit_proper_participants|employee_mutations|employee_promotions|employee_demotions)\b/', $query->sql)) {
                $moduleQueries[] = $query->sql;
            }
        });

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada modul ringkasan yang dapat ditampilkan.')
            ->assertDontSee('data-dashboard-metric=', false)
            ->assertDontSee('data-career-chart', false)
            ->assertDontSee('data-wla-status', false)
            ->assertDontSee('data-fit-proper-status', false);

        $this->assertNull($response->viewData('totalEmployees'));
        $this->assertNull($response->viewData('wlaSummary'));
        $this->assertNull($response->viewData('fitProperSummary'));
        $this->assertNull($response->viewData('pendingTotal'));
        $this->assertSame([], $response->viewData('movements'));
        $this->assertSame([], $moduleQueries);
    }

    public function test_partial_movement_access_excludes_other_series_counts_and_links(): void
    {
        $user = User::factory()->create(['role' => UserRole::MEMBER->value]);
        $this->replaceRolePermissions(UserRole::MEMBER, ['dashboard.view', 'workspace.hc-od-career-management.view', 'mutations.view']);
        $employee = Employee::query()->create(['sap' => 'DASH-PARTIAL']);
        $this->createMovement(EmployeeMutation::class, $employee, 'Pending', '2026-11-01');
        $this->createMovement(EmployeePromotion::class, $employee, 'Waiting Approval', '2026-11-01');
        $this->createMovement(EmployeeDemotion::class, $employee, 'Pending', '2026-11-01');

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pendingTotal', 1)
            ->assertDontSee('href="'.route('promosi').'"', false)
            ->assertDontSee('href="'.route('demosi').'"', false)
            ->assertDontSee('Promosi menunggu persetujuan')
            ->assertDontSee('Demosi menunggu persetujuan');

        $this->assertSame(['Mutasi'], array_column($response->viewData('movements'), 'label'));
        $this->assertNull($response->viewData('totalEmployees'));
        $this->assertNull($response->viewData('wlaSummary'));
        $this->assertNull($response->viewData('fitProperSummary'));
    }

    public function test_workspace_switch_is_removed_only_from_hc_od_dashboard_and_portal_still_works(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($user)->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('HC OD &amp; Career Management', false)
            ->assertDontSee('Ganti Workspace');
        $this->get(route('portal'))->assertOk();
        $this->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('performance-talent.dashboard');
        $this->get(route('performance-talent.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('portal').'"', false)
            ->assertSee('Performance &amp; Talent Management', false)
            ->assertDontSee('data-hc-od-dashboard', false);
    }

    /** @param list<WlaAssessmentStatus> $statuses */
    private function createAssessments(User $user, array $statuses): void
    {
        $department = Department::query()->create(['code' => 'DASH-D', 'name' => 'Dashboard Department', 'active' => true]);
        $unit = $department->units()->create(['code' => 'DASH-U', 'name' => 'Dashboard Unit', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'DASH-P', 'name' => 'Dashboard Position', 'active' => true]);
        $schedule = WorkSchedule::query()->create([
            'code' => 'DASH-S', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => 7, 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendar = WorkCalendar::query()->create([
            'year' => 2026, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52, 'sunday_days' => 52, 'active' => true,
        ]);

        foreach ($statuses as $index => $status) {
            WlaAssessment::query()->create([
                'assessment_code' => 'DASH-WLA-'.$index, 'period' => 2026, 'department_id' => $department->id,
                'unit_id' => $unit->id, 'position_id' => $position->id, 'work_schedule_id' => $schedule->id,
                'work_calendar_id' => $calendar->id, 'efficiency_factor' => 0.9, 'status' => $status, 'created_by' => $user->id,
            ]);
        }
    }

    private function createActivity(User $creator, string $status, int $participantCount): FitProperActivity
    {
        $activity = FitProperActivity::query()->create([
            'name' => 'Dashboard activity '.Str::uuid(), 'target_position' => 'Analyst', 'scheduled_at' => '2026-11-07',
            'created_by' => $creator->id, 'standard' => [], 'status' => $status,
        ]);

        foreach (range(1, $participantCount) as $index) {
            $activity->participants()->create(['name' => 'Candidate '.$index, 'internal_identity' => (string) Str::uuid(), 'profile' => []]);
        }

        return $activity;
    }

    /** @param class-string<EmployeeMutation|EmployeePromotion|EmployeeDemotion> $modelClass */
    private function createMovement(string $modelClass, Employee $employee, string $status, string $effectiveDate): void
    {
        $modelClass::query()->create([
            'employee_id' => $employee->id, 'sap' => $employee->sap, 'nama' => 'Synthetic Employee',
            'departemen_lama' => 'Department A', 'departemen_baru' => 'Department B',
            'jabatan_lama' => 'Analyst A', 'jabatan_baru' => 'Analyst B', 'tmt' => $effectiveDate,
            'pg' => '10', 'level_lama' => 'Staff', 'level_baru' => 'Staff', 'jg_lama' => '10', 'jg_baru' => '10',
            'verification_status' => $status,
        ]);
    }

    /** @param list<string> $keys */
    private function replaceRolePermissions(UserRole $role, array $keys): void
    {
        DB::table('permission_role')->where('role', $role->value)->delete();
        $this->grantRolePermissions($role, $keys);
    }

    /** @param list<string> $keys */
    private function grantRolePermissions(UserRole $role, array $keys): void
    {
        foreach (Permission::query()->whereIn('key', $keys)->pluck('id') as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role' => $role->value, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        app(RolePermissionCache::class)->clear();
    }
}
