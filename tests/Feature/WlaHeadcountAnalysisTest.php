<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaHeadcountAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WlaHeadcountAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_analysis_uses_final_snapshots_and_current_employee_position_ids_for_all_gap_states(): void
    {
        $user = $this->user();
        $shortage = $this->organization('SHORT', 'Analyst');
        $balanced = $this->organization('BAL', 'Planner');
        $surplus = $this->organization('SUR', 'Operator');
        $empty = $this->organization('EMPTY', 'Vacant Role');
        $sameNameDifferentUnit = $this->organization('OTHER', 'Analyst');
        $draftOnly = $this->organization('DRAFT', 'Draft Position');

        $shortageFinal = $this->finalAssessment($shortage, 2026, '3.400000', 4, $user);
        $this->finalAssessment($balanced, 2026, '4.000000', 4, $user);
        $this->finalAssessment($surplus, 2026, '4.100000', 4, $user);
        $this->finalAssessment($empty, 2026, '4.000000', 4, $user);
        $this->draftAssessment($draftOnly, 2026, $user);

        $this->employees($shortage, 3);
        $this->employees($balanced, 4);
        $this->employees($surplus, 5);
        $this->employees($sameNameDifferentUnit, 2);

        $shortageFinal->forceFill([
            'fte' => '99.000000',
            'recommended_employees' => 99,
        ])->save();
        $shortage['position']->update(['name' => 'Live Position Renamed']);

        $analysis = $this->service()->analyze(['period' => 2026]);
        $rows = collect($analysis['rows']->items())->keyBy('position_id');

        $this->assertCount(4, $rows);
        $this->assertFalse($rows->contains('position_name', 'Draft Position'));
        $this->assertSame('3.400000', $rows[$shortage['position']->id]['fte']);
        $this->assertSame('Analyst', $rows[$shortage['position']->id]['position_name']);
        $this->assertSame(4, $rows[$shortage['position']->id]['recommended_employees']);
        $this->assertSame(3, $rows[$shortage['position']->id]['actual_employees']);
        $this->assertSame(1, $rows[$shortage['position']->id]['gap']);
        $this->assertSame('+1', $rows[$shortage['position']->id]['gap_display']);
        $this->assertSame('shortage', $rows[$shortage['position']->id]['status']);
        $this->assertSame('Kekurangan', $rows[$shortage['position']->id]['status_label']);

        $this->assertSame(4, $rows[$balanced['position']->id]['actual_employees']);
        $this->assertSame(0, $rows[$balanced['position']->id]['gap']);
        $this->assertSame('balanced', $rows[$balanced['position']->id]['status']);
        $this->assertSame('Seimbang', $rows[$balanced['position']->id]['status_label']);

        $this->assertSame(5, $rows[$surplus['position']->id]['actual_employees']);
        $this->assertSame(-1, $rows[$surplus['position']->id]['gap']);
        $this->assertSame('-1', $rows[$surplus['position']->id]['gap_display']);
        $this->assertSame('surplus', $rows[$surplus['position']->id]['status']);
        $this->assertSame('Kelebihan', $rows[$surplus['position']->id]['status_label']);

        $this->assertSame(0, $rows[$empty['position']->id]['actual_employees']);
        $this->assertSame(4, $rows[$empty['position']->id]['gap']);
        $this->assertSame(4, $analysis['summary']['positions_analyzed']);
        $this->assertSame(16, $analysis['summary']['total_recommended']);
        $this->assertSame(12, $analysis['summary']['total_actual']);
        $this->assertSame(5, $analysis['summary']['total_shortage']);
        $this->assertSame(1, $analysis['summary']['total_surplus']);
        $this->assertSame(1, $analysis['summary']['balanced_positions']);
    }

    public function test_incomplete_snapshots_are_unavailable_excluded_and_never_matched_by_name(): void
    {
        $user = $this->user();
        $organization = $this->organization('INCOMPLETE', 'Shared Name');
        $missingPosition = $this->finalAssessment($organization, 2026, '2.000000', 2, $user);
        $this->employees($organization, 3);
        $snapshot = $missingPosition->final_snapshot;
        unset($snapshot['position']['id']);
        $missingPosition->forceFill(['final_snapshot' => $snapshot])->save();

        $otherOrganization = $this->organization('INVALID-REC', 'Another Position');
        $invalidRecommendation = $this->finalAssessment($otherOrganization, 2026, '1.500000', 2, $user);
        $invalidSnapshot = $invalidRecommendation->final_snapshot;
        $invalidSnapshot['recommended_employees'] = 'not-an-integer';
        $invalidRecommendation->forceFill(['final_snapshot' => $invalidSnapshot])->save();

        $analysis = $this->service()->analyze(['period' => 2026]);
        $rows = collect($analysis['rows']->items());

        $this->assertCount(2, $rows);
        $rows->each(function (array $row): void {
            $this->assertSame('unavailable', $row['status']);
            $this->assertSame('Tidak tersedia', $row['status_label']);
            $this->assertSame('Data snapshot tidak lengkap', $row['unavailable_reason']);
            $this->assertNull($row['actual_employees']);
            $this->assertNull($row['gap']);
        });
        $this->assertSame(0, $analysis['summary']['positions_analyzed']);
        $this->assertSame(0, $analysis['summary']['total_recommended']);
        $this->assertSame(0, $analysis['summary']['total_actual']);
        $this->assertSame(0, $analysis['summary']['total_shortage']);
        $this->assertSame(0, $analysis['summary']['total_surplus']);
        $this->assertSame(2, $analysis['summary']['incomplete_snapshots']);
    }

    public function test_default_period_is_latest_and_explicit_period_filter_is_respected(): void
    {
        $user = $this->user();
        $old = $this->organization('OLD', 'Old Period Position');
        $latest = $this->organization('LATEST', 'Latest Period Position');
        $this->finalAssessment($old, 2025, '1.000000', 1, $user);
        $this->finalAssessment($latest, 2027, '1.000000', 1, $user);
        $this->actingAs($user);

        $defaultResponse = $this->get(route('wla.headcount-analysis'))
            ->assertOk()
            ->assertSee('Latest Period Position')
            ->assertDontSee('Old Period Position');
        $defaultResponse->assertViewHas('selectedPeriod', 2027);

        $periodResponse = $this->get(route('wla.headcount-analysis', ['period' => 2025]))
            ->assertOk()
            ->assertSee('Old Period Position')
            ->assertDontSee('Latest Period Position');
        $periodResponse->assertViewHas('selectedPeriod', 2025);
    }

    public function test_organization_and_gap_status_filters_apply_before_summary(): void
    {
        $user = $this->user();
        $first = $this->organization('FILTER-A', 'Short Position');
        $second = $this->organization('FILTER-B', 'Balanced Position');
        $third = $this->organization('FILTER-C', 'Surplus Position');
        $this->finalAssessment($first, 2026, '3.000000', 3, $user);
        $this->finalAssessment($second, 2026, '1.000000', 1, $user);
        $this->finalAssessment($third, 2026, '1.000000', 1, $user);
        $this->employees($first, 1);
        $this->employees($second, 1);
        $this->employees($third, 2);

        foreach ([
            ['department_id' => $first['department']->id],
            ['unit_id' => $first['unit']->id],
            ['position_id' => $first['position']->id],
            ['gap_status' => 'shortage'],
        ] as $filter) {
            $analysis = $this->service()->analyze(['period' => 2026, ...$filter]);
            $this->assertCount(1, $analysis['rows']->items());
            $this->assertSame($first['position']->id, $analysis['rows']->items()[0]['position_id']);
            $this->assertSame(1, $analysis['summary']['positions_analyzed']);
            $this->assertSame(3, $analysis['summary']['total_recommended']);
            $this->assertSame(1, $analysis['summary']['total_actual']);
            $this->assertSame(2, $analysis['summary']['total_shortage']);
        }

        $this->assertCount(1, $this->service()->analyze([
            'period' => 2026,
            'gap_status' => 'balanced',
        ])['rows']->items());
        $this->assertCount(1, $this->service()->analyze([
            'period' => 2026,
            'gap_status' => 'surplus',
        ])['rows']->items());
    }

    public function test_summary_does_not_double_count_actual_headcount_for_a_duplicate_position(): void
    {
        $user = $this->user();
        $organization = $this->organization('DUPLICATE', 'Unique Position');
        $this->finalAssessment($organization, 2026, '2.000000', 2, $user, 'duplicate-a');
        $this->finalAssessment($organization, 2026, '2.000000', 2, $user, 'duplicate-b');
        $this->employees($organization, 2);

        $analysis = $this->service()->analyze(['period' => 2026]);

        $this->assertCount(2, $analysis['rows']->items());
        $this->assertSame(1, $analysis['summary']['positions_analyzed']);
        $this->assertSame(2, $analysis['summary']['total_recommended']);
        $this->assertSame(2, $analysis['summary']['total_actual']);
        $this->assertSame(1, $analysis['summary']['balanced_positions']);
    }

    public function test_pagination_preserves_filters_and_dependent_dropdown_uses_existing_alpine_pattern(): void
    {
        $user = $this->user();
        $department = Department::create(['code' => 'D-PAGE', 'name' => 'Pagination Department', 'active' => true]);

        for ($index = 1; $index <= 16; $index++) {
            $unit = $department->units()->create([
                'code' => "U-PAGE-{$index}",
                'name' => "Pagination Unit {$index}",
                'active' => true,
            ]);
            $position = $unit->positions()->create([
                'code' => "P-PAGE-{$index}",
                'name' => sprintf('Pagination Position %02d', $index),
                'active' => true,
            ]);
            $this->finalAssessment(compact('department', 'unit', 'position'), 2026, '1.000000', 1, $user);
        }

        $this->actingAs($user)
            ->get(route('wla.headcount-analysis', [
                'period' => 2026,
                'department_id' => $department->id,
                'gap_status' => 'shortage',
            ]))
            ->assertOk()
            ->assertSee('units.filter(item => String(item.department_id) === String(departmentId))', false)
            ->assertSee('positions.filter(item => String(item.unit_id) === String(unitId))', false)
            ->assertSee('department_id='.$department->id, false)
            ->assertSee('gap_status=shortage', false)
            ->assertSee('page=2', false);
    }

    public function test_authorized_roles_can_access_and_unauthorized_roles_receive_no_analysis_data(): void
    {
        foreach ([UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::MANAGER] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)
                ->get(route('wla.headcount-analysis'))
                ->assertOk()
                ->assertSee('Analisis Headcount dan Gap');
            $this->get(route('wla'))
                ->assertOk()
                ->assertSee(route('wla.headcount-analysis'))
                ->assertSee('Analisis Headcount');
        }

        foreach ([UserRole::STAFF, UserRole::MEMBER] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)
                ->get(route('wla.headcount-analysis'))
                ->assertForbidden()
                ->assertDontSee('Pegawai Aktual Saat Ini')
                ->assertDontSee('FTE Final');
            $this->get(route('wla'))
                ->assertForbidden()
                ->assertDontSee('Analisis Headcount');
        }
    }

    public function test_analysis_uses_one_employee_aggregate_query_and_does_not_write_to_database(): void
    {
        $user = $this->user();
        $snapshotsBefore = [];

        for ($index = 1; $index <= 8; $index++) {
            $organization = $this->organization("QUERY-{$index}", "Query Position {$index}");
            $assessment = $this->finalAssessment($organization, 2026, '1.250000', 2, $user);
            $this->employees($organization, $index % 3);
            $snapshotsBefore[$assessment->id] = $assessment->getRawOriginal('final_snapshot');
        }

        $tableCountsBefore = [
            'wla_assessments' => WlaAssessment::query()->count(),
            'employees' => Employee::query()->count(),
            'departments' => Department::query()->count(),
        ];
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service()->analyze(['period' => 2026]);

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $employeeQueries = $queries->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'from "employees"'),
        );
        $this->assertCount(1, $employeeQueries);
        $this->assertLessThanOrEqual(3, $queries->count());
        $this->assertSame($tableCountsBefore['wla_assessments'], WlaAssessment::query()->count());
        $this->assertSame($tableCountsBefore['employees'], Employee::query()->count());
        $this->assertSame($tableCountsBefore['departments'], Department::query()->count());

        foreach ($snapshotsBefore as $assessmentId => $snapshot) {
            $this->assertSame($snapshot, WlaAssessment::query()->findOrFail($assessmentId)->getRawOriginal('final_snapshot'));
        }
    }

    public function test_empty_state_is_shown_when_no_final_assessment_exists(): void
    {
        $user = $this->user();
        $organization = $this->organization('EMPTY-STATE', 'Draft Only');
        $this->draftAssessment($organization, 2026, $user);

        $this->actingAs($user)
            ->get(route('wla.headcount-analysis'))
            ->assertOk()
            ->assertSee('Belum ada WLA Final')
            ->assertDontSee('Pegawai Aktual Saat Ini');
    }

    private function service(): WlaHeadcountAnalysisService
    {
        return app(WlaHeadcountAnalysisService::class);
    }

    private function user(UserRole $role = UserRole::SUPER_ADMIN): User
    {
        return User::factory()->create(['role' => $role->value]);
    }

    /** @return array{department: Department, unit: Unit, position: Position} */
    private function organization(string $suffix, string $positionName): array
    {
        $department = Department::create([
            'code' => "D-{$suffix}",
            'name' => "Department {$suffix}",
            'active' => true,
        ]);
        $unit = $department->units()->create([
            'code' => "U-{$suffix}",
            'name' => "Unit {$suffix}",
            'active' => true,
        ]);
        $position = $unit->positions()->create([
            'code' => "P-{$suffix}",
            'name' => $positionName,
            'active' => true,
        ]);

        return compact('department', 'unit', 'position');
    }

    /** @param array{department: Department, unit: Unit, position: Position} $organization */
    private function finalAssessment(
        array $organization,
        int $period,
        string $fte,
        int $recommendedEmployees,
        User $user,
        ?string $finalizationKey = null,
    ): WlaAssessment {
        $assessment = $this->draftAssessment($organization, $period, $user);
        $assessment->forceFill([
            'status' => WlaAssessmentStatus::Final,
            'finalized_at' => now(),
            'finalized_by' => $user->id,
            'finalization_key' => $finalizationKey ?? "{$period}:{$organization['position']->id}",
            'final_snapshot' => [
                'assessment_code' => $assessment->assessment_code,
                'period' => $period,
                'department' => [
                    'id' => $organization['department']->id,
                    'code' => $organization['department']->code,
                    'name' => $organization['department']->name,
                ],
                'unit' => [
                    'id' => $organization['unit']->id,
                    'code' => $organization['unit']->code,
                    'name' => $organization['unit']->name,
                ],
                'position' => [
                    'id' => $organization['position']->id,
                    'code' => $organization['position']->code,
                    'name' => $organization['position']->name,
                ],
                'fte' => $fte,
                'recommended_employees' => $recommendedEmployees,
            ],
        ])->save();

        return $assessment->refresh();
    }

    /** @param array{department: Department, unit: Unit, position: Position} $organization */
    private function draftAssessment(array $organization, int $period, User $user): WlaAssessment
    {
        $this->sequence++;
        $schedule = WorkSchedule::query()->firstOrCreate(
            ['code' => 'DAYSHIFT-HEADCOUNT'],
            [
                'name' => 'Dayshift Headcount',
                'schedule_type' => 'Dayshift',
                'calculation_type' => WorkScheduleCalculationType::Dayshift,
                'working_hours_per_day' => '7.00',
                'working_days_per_week' => 5,
                'active' => true,
            ],
        );
        $calendar = WorkCalendar::query()->firstOrCreate(
            ['year' => $period],
            [
                'total_days' => $period % 4 === 0 ? 366 : 365,
                'total_weeks' => 52,
                'annual_leave' => 12,
                'national_holiday' => 17,
                'common_leave' => 6,
                'saturday_days' => 52,
                'sunday_days' => 52,
                'active' => true,
            ],
        );

        return WlaAssessment::create([
            'assessment_code' => sprintf('WLA-HC-%04d-%06d', $period, $this->sequence),
            'period' => $period,
            'department_id' => $organization['department']->id,
            'unit_id' => $organization['unit']->id,
            'position_id' => $organization['position']->id,
            'work_schedule_id' => $schedule->id,
            'work_calendar_id' => $calendar->id,
            'efficiency_factor' => '0.9000',
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $user->id,
        ]);
    }

    /** @param array{department: Department, unit: Unit, position: Position} $organization */
    private function employees(array $organization, int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $this->sequence++;
            Employee::create([
                'sap' => sprintf('HC-%08d', $this->sequence),
                'department_id' => $organization['department']->id,
                'unit_id' => $organization['unit']->id,
                'position_id' => $organization['position']->id,
            ]);
        }
    }
}
