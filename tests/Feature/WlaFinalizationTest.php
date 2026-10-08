<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaLegacyBackfillCategory;
use App\Enums\WlaPeriodUnit;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WlaLegacyBackfillService;
use App\Services\Workforce\WorkCalendarService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class WlaFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_status_enum_supports_draft_and_final(): void
    {
        $this->assertSame('draft', WlaAssessmentStatus::Draft->value);
        $this->assertSame('final', WlaAssessmentStatus::Final->value);
    }

    public function test_management_roles_can_finalize_a_complete_draft(): void
    {
        foreach ([UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::MANAGER] as $role) {
            $data = $this->context($role);
            $assessment = $this->assessment($data);
            $this->activity($assessment);

            $this->actingAs($data['user'])
                ->post(route('wla.finalize', $assessment))
                ->assertRedirect(route('wla.show', $assessment));

            $assessment->refresh();
            $this->assertSame(WlaAssessmentStatus::Final, $assessment->status);
            $this->assertSame($data['user']->id, $assessment->finalized_by);
        }
    }

    public function test_staff_and_member_cannot_finalize(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $this->activity($assessment);

        foreach ([UserRole::STAFF, UserRole::MEMBER] as $role) {
            $user = User::factory()->create(['role' => $role->value]);
            $this->actingAs($user)->post(route('wla.finalize', $assessment))->assertForbidden();
        }

        $this->assertSame(WlaAssessmentStatus::Draft, $assessment->fresh()->status);
    }

    public function test_finalization_requires_at_least_one_complete_activity(): void
    {
        $data = $this->context();
        $empty = $this->assessment($data);

        $this->actingAs($data['user'])
            ->from(route('wla.show', $empty))
            ->post(route('wla.finalize', $empty))
            ->assertSessionHasErrors('finalization');

        $legacy = $this->assessment($data);
        $legacy->activities()->create($this->legacyActivityPayload());
        $this->from(route('wla.show', $legacy))
            ->post(route('wla.finalize', $legacy))
            ->assertSessionHasErrors('finalization');

        $this->assertSame(WlaAssessmentStatus::Draft, $empty->fresh()->status);
        $this->assertSame(WlaAssessmentStatus::Draft, $legacy->fresh()->status);
    }

    public function test_inactive_or_unclassified_master_blocks_finalization(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $this->activity($assessment);
        $data['schedule']->update(['active' => false]);

        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment))
            ->assertSessionHasErrors('finalization');

        $data['schedule']->update([
            'active' => true,
            'code' => 'CUSTOM-'.$this->sequence,
            'calculation_type' => null,
        ]);
        $this->post(route('wla.finalize', $assessment))->assertSessionHasErrors('finalization');

        $data['schedule']->update(['calculation_type' => WorkScheduleCalculationType::Dayshift]);
        $data['calendar']->update(['active' => false]);
        $this->post(route('wla.finalize', $assessment))->assertSessionHasErrors('finalization');

        $this->assertSame(WlaAssessmentStatus::Draft, $assessment->fresh()->status);
    }

    public function test_finalization_recalculates_and_stores_metadata_key_and_complete_decimal_snapshot(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $activity = $this->activity($assessment, [
            'frequency' => '2.50', 'period_unit' => WlaPeriodUnit::Week,
            'frequency_unit' => 'Week', 'volume_unit' => 'Week',
            'time_allocated' => '1.25', 'time_allocated_hours' => '1.25',
            'notes' => 'Snapshot note',
        ]);

        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment))->assertRedirect();

        $assessment->refresh();
        $snapshot = $assessment->final_snapshot;
        $this->assertNotNull($assessment->finalized_at);
        $this->assertSame($data['user']->id, $assessment->finalized_by);
        $this->assertSame("{$assessment->period}:{$assessment->position_id}", $assessment->finalization_key);
        $this->assertSame($assessment->assessment_code, $snapshot['assessment_code']);
        $this->assertSame($data['department']->name, $snapshot['department']['name']);
        $this->assertSame($data['unit']->name, $snapshot['unit']['name']);
        $this->assertSame($data['position']->name, $snapshot['position']['name']);
        $this->assertSame('7.00', $snapshot['schedule']['wla_hours_per_day']);
        $this->assertSame($assessment->period, $snapshot['calendar']['year']);
        $this->assertSame('Snapshot note', $snapshot['activities'][0]['notes']);
        $this->assertSame($activity->sort_order, $snapshot['activities'][0]['sort_order']);

        foreach ([
            $snapshot['efficiency_factor'], $snapshot['annual_working_hours'],
            $snapshot['effective_annual_working_hours'], $snapshot['activities'][0]['frequency'],
            $snapshot['activities'][0]['time_allocated_hours'],
            $snapshot['activities'][0]['annual_workload_hours'],
            $snapshot['total_annual_workload'], $snapshot['fte'],
        ] as $decimal) {
            $this->assertIsString($decimal);
        }

        $this->assertSame('162.5000', $assessment->total_annual_workload_hours);
        $this->assertSame('162.5000', $snapshot['total_annual_workload']);
    }

    public function test_recalculation_failure_rolls_back_finalization_and_activity_snapshot(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $activity = $this->activity($assessment);
        $mock = Mockery::mock(WlaCalculationService::class, [app(WorkCalendarService::class)])->makePartial();
        $mock->shouldReceive('recalculate')->once()->andThrow(
            ValidationException::withMessages(['calculation' => 'Forced failure.']),
        );
        $this->app->instance(WlaCalculationService::class, $mock);

        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment))
            ->assertSessionHasErrors('calculation');

        $assessment->refresh();
        $this->assertSame(WlaAssessmentStatus::Draft, $assessment->status);
        $this->assertNull($assessment->finalized_at);
        $this->assertNull($assessment->final_snapshot);
        $this->assertNull($activity->fresh()->annual_workload_hours);
    }

    public function test_only_one_final_is_allowed_while_multiple_drafts_are_allowed(): void
    {
        $data = $this->context();
        $first = $this->assessment($data);
        $second = $this->assessment($data);
        $this->activity($first);
        $this->activity($second);

        $this->assertDatabaseCount('wla_assessments', 2);
        $this->assertNull($first->finalization_key);
        $this->assertNull($second->finalization_key);
        $this->actingAs($data['user'])->post(route('wla.finalize', $first))->assertRedirect();
        $this->from(route('wla.show', $second))->post(route('wla.finalize', $second))
            ->assertSessionHasErrors([
                'finalization' => 'Posisi ini sudah memiliki WLA Final pada periode tersebut.',
            ]);

        $this->assertSame(WlaAssessmentStatus::Final, $first->fresh()->status);
        $this->assertSame(WlaAssessmentStatus::Draft, $second->fresh()->status);
    }

    public function test_unique_finalization_key_violation_is_a_friendly_validation_error(): void
    {
        $data = $this->context();
        $blocker = $this->assessment($data);
        $target = $this->assessment($data);
        $this->activity($target);
        $blocker->forceFill(['finalization_key' => "{$target->period}:{$target->position_id}"])->save();

        $this->actingAs($data['user'])->from(route('wla.show', $target))
            ->post(route('wla.finalize', $target))
            ->assertSessionHasErrors([
                'finalization' => 'Posisi ini sudah memiliki WLA Final pada periode tersebut.',
            ]);

        $this->assertSame(WlaAssessmentStatus::Draft, $target->fresh()->status);
        $this->assertNull($target->fresh()->finalized_at);
    }

    public function test_final_assessment_and_activities_are_immutable_through_routes(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $activity = $this->activity($assessment);
        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment))->assertRedirect();
        $originalActivity = $activity->fresh()->toArray();

        $this->put(route('wla.update', $assessment), $this->assessmentPayload($data, ['efficiency_factor' => '0.8000']))
            ->assertForbidden();
        $this->delete(route('wla.destroy', $assessment))->assertForbidden();
        $this->post(route('wla.activities.store', $assessment), $this->activityRequestPayload())->assertForbidden();
        $this->put(route('wla.activities.update', [$assessment, $activity]), $this->activityRequestPayload([
            'activity_name' => 'Changed',
        ]))->assertForbidden();
        $this->delete(route('wla.activities.destroy', [$assessment, $activity]))->assertForbidden();

        $this->assertSame($originalActivity, $activity->fresh()->toArray());
        $this->assertDatabaseCount('wla_activities', 1);
    }

    public function test_final_page_uses_snapshot_and_hides_all_mutation_controls(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $this->activity($assessment);
        $oldDepartment = $data['department']->name;
        $oldSchedule = $data['schedule']->name;
        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment));
        $snapshotCode = $assessment->fresh()->final_snapshot['assessment_code'];
        $data['department']->update(['name' => 'Renamed department']);
        $data['schedule']->update(['name' => 'Renamed schedule']);
        DB::table('wla_assessments')->where('id', $assessment->id)->update(['assessment_code' => 'CHANGED-CODE']);

        $this->get(route('wla.show', $assessment))
            ->assertOk()->assertSee($snapshotCode)->assertSee($oldDepartment)->assertSee($oldSchedule)
            ->assertDontSee('CHANGED-CODE')
            ->assertDontSee('Renamed department')->assertDontSee('Renamed schedule')
            ->assertSee('Hasil WLA Final sudah dibekukan')
            ->assertDontSee('Edit Draft')->assertDontSee('Delete Draft')
            ->assertDontSee('Finalisasi WLA')->assertDontSee('Tambah Aktivitas')
            ->assertDontSee('name="activity_name"', false);
    }

    public function test_finalize_button_follows_policy_and_second_finalization_is_rejected(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $this->activity($assessment);

        $this->actingAs($data['user'])->get(route('wla.show', $assessment))
            ->assertOk()->assertSee('Finalisasi WLA');
        $this->post(route('wla.finalize', $assessment))->assertRedirect();
        $snapshot = $assessment->fresh()->getRawOriginal('final_snapshot');
        $this->post(route('wla.finalize', $assessment))->assertForbidden();
        $this->assertSame($snapshot, $assessment->fresh()->getRawOriginal('final_snapshot'));
    }

    public function test_master_updates_do_not_change_final_calculations_or_snapshot(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $this->activity($assessment);
        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment));
        $fields = [
            'working_days', 'working_hours_year', 'effective_working_hours',
            'total_annual_workload_hours', 'fte', 'recommended_employees', 'final_snapshot',
        ];
        $before = $assessment->fresh()->only($fields);

        $this->put(route('work-schedules.update', $data['schedule']), $this->schedulePayload($data['schedule'], [
            'name' => 'Updated schedule',
            'calculation_type' => WorkScheduleCalculationType::Shift123->value,
        ]))->assertRedirect(route('work-schedules.index'));
        $this->put(route('work-calendars.update', $data['calendar']), $this->calendarPayload($data['calendar'], [
            'annual_leave' => 13,
        ]))->assertRedirect(route('work-calendars.index'));

        $this->assertSame($before, $assessment->fresh()->only($fields));
    }

    public function test_backfill_reports_complete_final_as_migrated_and_never_changes_it(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $activity = $this->activity($assessment);
        $this->actingAs($data['user'])->post(route('wla.finalize', $assessment));
        $snapshot = $assessment->fresh()->getRawOriginal('final_snapshot');
        $activityAttributes = $activity->fresh()->getAttributes();

        $audit = app(WlaLegacyBackfillService::class)->audit($assessment->id);
        $apply = app(WlaLegacyBackfillService::class)->apply($assessment->id);

        $this->assertSame(WlaLegacyBackfillCategory::AlreadyMigrated, $audit['assessments'][0]['category']);
        $this->assertSame(0, $apply['summary']['assessment_backfilled']);
        $this->assertSame($snapshot, $assessment->fresh()->getRawOriginal('final_snapshot'));
        $this->assertSame($activityAttributes, $activity->fresh()->getAttributes());
    }

    public function test_incomplete_final_is_a_conflict_and_is_not_backfilled(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $activity = $assessment->activities()->create($this->legacyActivityPayload());
        $assessment->forceFill([
            'status' => WlaAssessmentStatus::Final, 'finalized_at' => now(),
            'finalized_by' => $data['user']->id,
            'finalization_key' => "{$assessment->period}:{$assessment->position_id}",
            'final_snapshot' => null,
        ])->save();

        $report = app(WlaLegacyBackfillService::class)->apply($assessment->id);

        $this->assertSame(WlaLegacyBackfillCategory::Conflict, $report['assessments'][0]['category']);
        $this->assertNull($activity->fresh()->period_unit);
        $this->assertNull($assessment->fresh()->final_snapshot);
    }

    public function test_migration_rollback_preserves_records_and_downgrades_final_status(): void
    {
        $originalDefault = config('database.default');
        $connection = array_replace(config('database.connections.sqlite'), ['database' => ':memory:']);
        config([
            'database.default' => 'wla_rollback',
            'database.connections.wla_rollback' => $connection,
        ]);
        DB::purge('wla_rollback');
        $migration = require database_path('migrations/2026_10_08_013041_add_finalization_fields_to_wla_assessments_table.php');

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('wla_assessments', function (Blueprint $table): void {
                $table->id();
                $table->string('status', 20)->default('draft');
            });
            Schema::create('wla_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('wla_assessment_id')->constrained()->restrictOnDelete();
            });
            $migration->up();
            DB::table('users')->insert(['id' => 1]);
            DB::table('wla_assessments')->insert([
                'id' => 1, 'status' => 'final', 'finalized_by' => 1,
                'finalization_key' => '2025:1', 'final_snapshot' => '{}',
            ]);
            DB::table('wla_activities')->insert(['id' => 1, 'wla_assessment_id' => 1]);

            $migration->down();
            $this->assertSame('draft', DB::table('wla_assessments')->where('id', 1)->value('status'));
            $this->assertSame(1, DB::table('wla_activities')->where('id', 1)->count());
            $this->assertFalse(Schema::hasColumn('wla_assessments', 'final_snapshot'));
        } finally {
            DB::disconnect('wla_rollback');
            config(['database.default' => $originalDefault]);
        }
    }

    /** @return array{user: User, department: Department, unit: Unit, position: Position, schedule: WorkSchedule, calendar: WorkCalendar, period: int} */
    private function context(UserRole $role = UserRole::SUPER_ADMIN): array
    {
        $this->sequence++;
        $suffix = str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT);
        $period = 2020 + $this->sequence;
        $user = User::factory()->create(['role' => $role->value]);
        $department = Department::create(['code' => "D{$suffix}", 'name' => "Department {$suffix}", 'active' => true]);
        $unit = $department->units()->create(['code' => "U{$suffix}", 'name' => "Unit {$suffix}", 'active' => true]);
        $position = $unit->positions()->create(['code' => "P{$suffix}", 'name' => "Position {$suffix}", 'active' => true]);
        $schedule = WorkSchedule::create([
            'code' => "DAYSHIFT_{$suffix}", 'name' => "Schedule {$suffix}", 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => $period, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ]);

        return compact('user', 'department', 'unit', 'position', 'schedule', 'calendar', 'period');
    }

    private function assessment(array $data): WlaAssessment
    {
        return WlaAssessment::create([
            ...$this->assessmentPayload($data),
            'assessment_code' => sprintf('WLA-%d-%06d', $data['period'], WlaAssessment::query()->count() + 1),
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $data['user']->id,
        ]);
    }

    private function assessmentPayload(array $data, array $overrides = []): array
    {
        return array_replace([
            'period' => $data['period'], 'department_id' => $data['department']->id,
            'unit_id' => $data['unit']->id, 'position_id' => $data['position']->id,
            'work_schedule_id' => $data['schedule']->id, 'work_calendar_id' => $data['calendar']->id,
            'efficiency_factor' => '0.9000',
        ], $overrides);
    }

    private function activity(WlaAssessment $assessment, array $overrides = []): WlaActivity
    {
        return $assessment->activities()->create(array_replace([
            'activity_name' => 'Daily check', 'frequency' => '1.00', 'period_unit' => WlaPeriodUnit::Day,
            'frequency_unit' => 'Day', 'volume' => '1.00', 'volume_unit' => 'Day',
            'time_allocated' => '1.00', 'time_unit' => 'Hour', 'time_allocated_hours' => '1.00',
            'sort_order' => 0, 'notes' => null,
        ], $overrides));
    }

    private function legacyActivityPayload(): array
    {
        return [
            'activity_name' => 'Legacy activity', 'frequency' => '1.00', 'frequency_unit' => 'Day',
            'volume' => '1.00', 'volume_unit' => 'Day', 'time_allocated' => '1.00',
            'time_unit' => 'Hour', 'sort_order' => 0,
        ];
    }

    private function activityRequestPayload(array $overrides = []): array
    {
        return array_replace([
            'activity_name' => 'Daily check', 'frequency' => '1.00',
            'period_unit' => WlaPeriodUnit::Day->value, 'time_allocated_hours' => '1.00',
        ], $overrides);
    }

    private function schedulePayload(WorkSchedule $schedule, array $overrides = []): array
    {
        return array_replace([
            'code' => $schedule->code, 'name' => $schedule->name, 'schedule_type' => $schedule->schedule_type,
            'calculation_type' => $schedule->calculation_type->value,
            'working_hours_per_day' => $schedule->working_hours_per_day,
            'working_days_per_week' => $schedule->working_days_per_week,
            'active' => $schedule->active, 'description' => $schedule->description,
        ], $overrides);
    }

    private function calendarPayload(WorkCalendar $calendar, array $overrides = []): array
    {
        return array_replace([
            'year' => $calendar->year, 'annual_leave' => $calendar->annual_leave,
            'national_holiday' => $calendar->national_holiday, 'common_leave' => $calendar->common_leave,
            'saturday_days' => $calendar->saturday_days, 'sunday_days' => $calendar->sunday_days,
            'active' => $calendar->active, 'notes' => $calendar->notes,
        ], $overrides);
    }
}
