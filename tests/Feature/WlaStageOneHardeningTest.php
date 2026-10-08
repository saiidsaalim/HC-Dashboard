<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WlaStageOneHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $assessmentSequence = 0;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_calendar_update_recalculates_all_drafts_but_not_other_statuses(): void
    {
        $data = $this->context();
        $first = $this->assessment($data);
        $second = $this->assessment($data);
        $archived = $this->assessment($data);

        foreach ([$first, $second] as $assessment) {
            $this->addValidActivity($assessment);
            app(WlaCalculationService::class)->recalculate($assessment);
        }

        DB::table('wla_assessments')->where('id', $archived->id)->update([
            'status' => 'archived',
            'working_days' => 999,
            'working_hours_year' => '999.00',
        ]);

        $this->actingAs($data['user'])
            ->put(route('work-calendars.update', $data['calendar']), $this->calendarPayload([
                'annual_leave' => 13,
            ]))
            ->assertRedirect(route('work-calendars.index'));

        foreach ([$first, $second] as $assessment) {
            $assessment->refresh();
            $this->assertSame(225, $assessment->working_days);
            $this->assertSame('225.0000', $assessment->total_annual_workload_hours);
        }

        $this->assertSame(999, DB::table('wla_assessments')->where('id', $archived->id)->value('working_days'));
    }

    public function test_schedule_update_recalculates_all_related_drafts(): void
    {
        $data = $this->context();
        $first = $this->assessment($data);
        $second = $this->assessment($data);

        foreach ([$first, $second] as $assessment) {
            $this->addValidActivity($assessment);
            app(WlaCalculationService::class)->recalculate($assessment);
        }

        $this->actingAs($data['user'])
            ->put(route('work-schedules.update', $data['schedule']), $this->schedulePayload([
                'calculation_type' => WorkScheduleCalculationType::Shift123->value,
            ]))
            ->assertRedirect(route('work-schedules.index'));

        foreach ([$first, $second] as $assessment) {
            $assessment->refresh();
            $this->assertSame(347, $assessment->working_days);
            $this->assertSame('2602.50', $assessment->working_hours_year);
            $this->assertSame('347.0000', $assessment->total_annual_workload_hours);
        }
    }

    public function test_failed_recalculation_rolls_back_master_and_all_snapshots(): void
    {
        $data = $this->context();
        $validAssessment = $this->assessment($data);
        $this->addValidActivity($validAssessment);
        app(WlaCalculationService::class)->recalculate($validAssessment);

        $invalidAssessment = $this->assessment($data);
        $invalidAssessment->update(['efficiency_factor' => '0.0000']);
        $this->addValidActivity($invalidAssessment);

        $this->actingAs($data['user'])
            ->from(route('work-schedules.edit', $data['schedule']))
            ->put(route('work-schedules.update', $data['schedule']), $this->schedulePayload([
                'calculation_type' => WorkScheduleCalculationType::Shift123->value,
            ]))
            ->assertRedirect(route('work-schedules.edit', $data['schedule']))
            ->assertSessionHasErrors('efficiency_factor');

        $this->assertSame(WorkScheduleCalculationType::Dayshift, $data['schedule']->fresh()->calculation_type);
        $this->assertSame(226, $validAssessment->fresh()->working_days);
        $this->assertNull($invalidAssessment->fresh()->working_days);
    }

    public function test_calendar_year_change_that_breaks_assessment_period_is_rejected(): void
    {
        $data = $this->context();
        $this->assessment($data);

        $this->actingAs($data['user'])
            ->from(route('work-calendars.edit', $data['calendar']))
            ->put(route('work-calendars.update', $data['calendar']), $this->calendarPayload(['year' => 2024]))
            ->assertSessionHasErrors('year');

        $this->assertSame(2025, $data['calendar']->fresh()->year);
    }

    public function test_used_masters_are_rejected_with_friendly_validation_messages(): void
    {
        $data = $this->context();
        $this->assessment($data);

        $this->actingAs($data['user'])
            ->from(route('work-calendars.index'))
            ->followingRedirects()
            ->delete(route('work-calendars.destroy', $data['calendar']))
            ->assertOk()
            ->assertSee('Kalender kerja tidak dapat dihapus karena masih digunakan oleh WLA.');
        $this->assertDatabaseHas('work_calendars', ['id' => $data['calendar']->id]);

        $this->from(route('work-schedules.index'))
            ->followingRedirects()
            ->delete(route('work-schedules.destroy', $data['schedule']))
            ->assertOk()
            ->assertSee('Jadwal kerja tidak dapat dihapus karena masih digunakan oleh WLA.');
        $this->assertDatabaseHas('work_schedules', ['id' => $data['schedule']->id]);
    }

    public function test_unused_masters_can_still_be_deleted(): void
    {
        $data = $this->context();

        $this->actingAs($data['user'])->delete(route('work-calendars.destroy', $data['calendar']))
            ->assertRedirect(route('work-calendars.index'));
        $this->delete(route('work-schedules.destroy', $data['schedule']))
            ->assertRedirect(route('work-schedules.index'));

        $this->assertDatabaseMissing('work_calendars', ['id' => $data['calendar']->id]);
        $this->assertDatabaseMissing('work_schedules', ['id' => $data['schedule']->id]);
    }

    public function test_builtin_null_schedule_uses_fallback_but_custom_null_schedule_is_not_selectable(): void
    {
        $data = $this->context(['calculation_type' => null]);
        $this->assertSame(WorkScheduleCalculationType::Dayshift, $data['schedule']->calculationTypeForWla());
        $assessment = $this->assessment($data);
        $this->addValidActivity($assessment);
        $this->assertSame(226, app(WlaCalculationService::class)->recalculate($assessment)->working_days);

        $customSchedule = WorkSchedule::create([
            'code' => 'CUSTOM', 'name' => 'Custom', 'schedule_type' => 'Custom',
            'calculation_type' => null, 'working_hours_per_day' => '8.00',
            'working_days_per_week' => 5, 'active' => true,
        ]);

        $this->actingAs($data['user'])->get(route('work-schedules.index'))
            ->assertOk()
            ->assertSee('Custom')
            ->assertSee('Perlu klasifikasi');
        $this->get(route('wla.create'))
            ->assertOk()
            ->assertSee('Perlu klasifikasi')
            ->assertSee('disabled', false);

        $this->from(route('wla.create'))
            ->post(route('wla.store'), $this->assessmentPayload($data, [
                'work_schedule_id' => $customSchedule->id,
            ]))
            ->assertSessionHasErrors('work_schedule_id');
    }

    public function test_legacy_assessment_with_custom_null_schedule_opens_without_zero_results(): void
    {
        $data = $this->context([
            'code' => 'CUSTOM',
            'name' => 'Custom',
            'calculation_type' => null,
        ]);
        $assessment = $this->assessment($data);

        $this->actingAs($data['user'])->get(route('wla.show', $assessment))
            ->assertOk()
            ->assertSee('Belum dapat dihitung')
            ->assertSee('Jadwal kerja belum diklasifikasikan')
            ->assertDontSee('0,00');
    }

    public function test_legacy_activity_marks_fte_and_recommendation_as_incomplete(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $assessment->activities()->create($this->legacyActivityPayload());

        $this->actingAs($data['user'])->get(route('wla.show', $assessment))
            ->assertOk()
            ->assertSee('Subtotal Aktivitas Valid')
            ->assertSee('Belum lengkap', false)
            ->assertSee('Perlu review');
    }

    public function test_activity_controls_are_read_only_when_policy_denies_update(): void
    {
        $data = $this->context();
        $assessment = $this->assessment($data);
        $activity = $this->addValidActivity($assessment);
        $member = User::factory()->create(['role' => UserRole::MEMBER->value]);

        $this->actingAs($member)
            ->withViewErrors([])
            ->view('wla._activities', [
                'wla' => $assessment->load('activities'),
                'calculationState' => ['has_activities_needing_review' => false],
            ])
            ->assertSee($activity->activity_name)
            ->assertSee('Read-only')
            ->assertDontSee('name="activity_name"', false)
            ->assertDontSee('Simpan')
            ->assertDontSee('Hapus')
            ->assertDontSee('Tambah Aktivitas');
    }

    /** @return array{user: User, department: Department, unit: Unit, position: Position, schedule: WorkSchedule, calendar: WorkCalendar} */
    private function context(array $scheduleOverrides = []): array
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'D'.fake()->unique()->numerify('###'), 'name' => 'Operations', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U'.fake()->unique()->numerify('###'), 'name' => 'Production', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P'.fake()->unique()->numerify('###'), 'name' => 'Operator', 'active' => true]);
        $schedule = WorkSchedule::create(array_replace([
            'code' => 'DAYSHIFT', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => true,
        ], $scheduleOverrides));
        $calendar = WorkCalendar::create($this->calendarPayload());

        return compact('user', 'department', 'unit', 'position', 'schedule', 'calendar');
    }

    private function assessment(array $data): WlaAssessment
    {
        $this->assessmentSequence++;

        return WlaAssessment::create([
            ...$this->assessmentPayload($data),
            'assessment_code' => sprintf('WLA-2025-%06d', $this->assessmentSequence),
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $data['user']->id,
        ]);
    }

    private function assessmentPayload(array $data, array $overrides = []): array
    {
        return array_replace([
            'period' => 2025,
            'department_id' => $data['department']->id,
            'unit_id' => $data['unit']->id,
            'position_id' => $data['position']->id,
            'work_schedule_id' => $data['schedule']->id,
            'work_calendar_id' => $data['calendar']->id,
            'efficiency_factor' => '0.9000',
        ], $overrides);
    }

    private function addValidActivity(WlaAssessment $assessment): WlaActivity
    {
        return $assessment->activities()->create([
            'activity_name' => 'Daily check', 'frequency' => '1.00', 'period_unit' => WlaPeriodUnit::Day,
            'frequency_unit' => 'Day', 'volume' => '1.00', 'volume_unit' => 'Day',
            'time_allocated' => '1.00', 'time_unit' => 'Hour', 'time_allocated_hours' => '1.00',
            'sort_order' => 0,
        ]);
    }

    private function legacyActivityPayload(): array
    {
        return [
            'activity_name' => 'Legacy activity', 'frequency' => '1.00', 'frequency_unit' => 'Day',
            'volume' => '1.00', 'volume_unit' => 'Day', 'time_allocated' => '1.00',
            'time_unit' => 'Hour', 'sort_order' => 0,
        ];
    }

    private function calendarPayload(array $overrides = []): array
    {
        return array_replace([
            'year' => 2025, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ], $overrides);
    }

    private function schedulePayload(array $overrides = []): array
    {
        return array_replace([
            'code' => 'DAYSHIFT', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift->value,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5,
            'active' => true, 'description' => null,
        ], $overrides);
    }
}
