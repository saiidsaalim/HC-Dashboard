<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaPeriodUnit;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\User;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WlaActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_create_activity_recalculates_total_fte_and_recommendation(): void
    {
        [$user, $assessment] = $this->context();

        $this->actingAs($user)
            ->post(route('wla.activities.store', $assessment), $this->activityPayload())
            ->assertRedirect(route('wla.show', $assessment));

        $activity = $assessment->activities()->sole();
        $assessment->refresh();

        $this->assertSame('2.50', $activity->frequency);
        $this->assertSame(WlaPeriodUnit::Week, $activity->period_unit);
        $this->assertSame('1.75', $activity->time_allocated_hours);
        $this->assertSame('227.5000', $activity->annual_workload_hours);
        $this->assertSame('227.5000', $assessment->total_annual_workload_hours);
        $this->assertSame('0.159784', $assessment->fte);
        $this->assertSame(1, $assessment->recommended_employees);
    }

    public function test_multiple_activities_are_totalled_and_update_recalculates(): void
    {
        [$user, $assessment] = $this->context();

        $this->actingAs($user)->post(
            route('wla.activities.store', $assessment),
            $this->activityPayload(['frequency' => '1.00', 'time_allocated_hours' => '1.00']),
        )->assertRedirect();
        $this->post(
            route('wla.activities.store', $assessment),
            $this->activityPayload(['activity_name' => 'Monthly report', 'period_unit' => 'Month']),
        )->assertRedirect();

        $activities = $assessment->activities()->orderBy('id')->get();
        $this->assertSame('52.0000', $activities[0]->annual_workload_hours);
        $this->assertSame('52.5000', $activities[1]->annual_workload_hours);
        $this->assertSame('104.5000', $assessment->fresh()->total_annual_workload_hours);

        $this->put(
            route('wla.activities.update', [$assessment, $activities[0]]),
            $this->activityPayload(['frequency' => '2.00', 'time_allocated_hours' => '1.00']),
        )->assertRedirect();

        $this->assertSame('156.5000', $assessment->fresh()->total_annual_workload_hours);
    }

    public function test_delete_activity_recalculates_assessment(): void
    {
        [$user, $assessment] = $this->context();
        $this->actingAs($user)->post(route('wla.activities.store', $assessment), $this->activityPayload());
        $activity = $assessment->activities()->sole();

        $this->delete(route('wla.activities.destroy', [$assessment, $activity]))
            ->assertRedirect(route('wla.show', $assessment));

        $assessment->refresh();
        $this->assertSame('0.0000', $assessment->total_annual_workload_hours);
        $this->assertSame('0.000000', $assessment->fte);
        $this->assertSame(0, $assessment->recommended_employees);
    }

    public function test_legacy_activity_is_excluded_and_shows_incomplete_warning(): void
    {
        [$user, $assessment] = $this->context();
        $assessment->activities()->create($this->legacyActivityPayload());

        $response = $this->actingAs($user)->get(route('wla.show', $assessment));

        $response->assertOk()
            ->assertSee('Perlu review')
            ->assertSee('Hasil FTE belum lengkap');
        $this->assertSame('0.0000', $assessment->fresh()->total_annual_workload_hours);
    }

    public function test_activity_form_uses_new_structure_and_omits_legacy_fields(): void
    {
        [$user, $assessment] = $this->context();

        $this->actingAs($user)->get(route('wla.show', $assessment))
            ->assertOk()
            ->assertSee('name="activity_name"', false)
            ->assertSee('name="frequency"', false)
            ->assertSee('name="period_unit"', false)
            ->assertSee('name="time_allocated_hours"', false)
            ->assertDontSee('name="frequency_unit"', false)
            ->assertDontSee('name="volume"', false)
            ->assertDontSee('name="volume_unit"', false)
            ->assertDontSee('name="time_unit"', false);
    }

    public function test_new_activity_values_are_required_and_positive(): void
    {
        [$user, $assessment] = $this->context();

        $this->actingAs($user)
            ->from(route('wla.show', $assessment))
            ->post(route('wla.activities.store', $assessment), [
                'activity_name' => '',
                'frequency' => '0',
                'period_unit' => 'Quarter',
                'time_allocated_hours' => '-1',
            ])
            ->assertSessionHasErrors(['activity_name', 'frequency', 'period_unit', 'time_allocated_hours']);
    }

    public function test_non_management_role_cannot_mutate_activities(): void
    {
        [$user, $assessment] = $this->context();
        $this->actingAs($user)->post(route('wla.activities.store', $assessment), $this->activityPayload());
        $activity = $assessment->activities()->sole();
        $member = User::factory()->create(['role' => UserRole::MEMBER->value]);

        $this->actingAs($member)->post(route('wla.activities.store', $assessment), $this->activityPayload())->assertForbidden();
        $this->put(route('wla.activities.update', [$assessment, $activity]), $this->activityPayload())->assertForbidden();
        $this->delete(route('wla.activities.destroy', [$assessment, $activity]))->assertForbidden();
    }

    public function test_sort_order_is_assigned_after_the_assessment_is_locked(): void
    {
        [$user, $assessment] = $this->context();
        $assessment->activities()->create($this->legacyActivityPayload(['sort_order' => 5]));
        $payload = $this->activityPayload();
        unset($payload['sort_order']);

        $this->actingAs($user)->post(route('wla.activities.store', $assessment), $payload)->assertRedirect();

        $this->assertSame([5, 6], $assessment->activities()->orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_calculation_failure_rolls_back_create_update_and_delete(): void
    {
        [$user, $assessment] = $this->context();
        $activity = $assessment->activities()->create([
            ...$this->legacyActivityPayload(),
            'period_unit' => WlaPeriodUnit::Week,
            'time_allocated_hours' => '1.00',
        ]);
        $assessment->update(['efficiency_factor' => '0.0000']);

        $this->actingAs($user)
            ->post(route('wla.activities.store', $assessment), $this->activityPayload(['activity_name' => 'Rolled back create']))
            ->assertSessionHasErrors('effective_working_hours');
        $this->assertDatabaseMissing('wla_activities', ['activity_name' => 'Rolled back create']);

        $this->put(route('wla.activities.update', [$assessment, $activity]), $this->activityPayload([
            'activity_name' => 'Rolled back update',
        ]))->assertSessionHasErrors('effective_working_hours');
        $this->assertSame('Legacy activity', $activity->fresh()->activity_name);

        $this->delete(route('wla.activities.destroy', [$assessment, $activity]))
            ->assertSessionHasErrors('effective_working_hours');
        $this->assertDatabaseHas('wla_activities', ['id' => $activity->id]);
    }

    public function test_numeric_boundaries_and_non_finite_values_are_validated(): void
    {
        [$user, $assessment] = $this->context();

        $this->actingAs($user)->post(route('wla.activities.store', $assessment), $this->activityPayload([
            'frequency' => WlaActivity::MAX_INPUT_VALUE,
            'time_allocated_hours' => WlaActivity::MAX_INPUT_VALUE,
            'period_unit' => WlaPeriodUnit::Year->value,
            'sort_order' => WlaActivity::MAX_SORT_ORDER,
        ]))->assertRedirect();

        foreach (['1000000.00', 'NaN', 'INF', '-1', '0', '1.001'] as $invalidValue) {
            $this->post(route('wla.activities.store', $assessment), $this->activityPayload([
                'frequency' => $invalidValue,
            ]))->assertSessionHasErrors('frequency');
        }

        $this->post(route('wla.activities.store', $assessment), $this->activityPayload([
            'sort_order' => '4294967296',
        ]))->assertSessionHasErrors('sort_order');

        $this->post(route('wla.activities.store', $assessment), $this->activityPayload([
            'activity_name' => 'Automatic order overflow',
        ]))->assertSessionHasErrors('sort_order');
        $this->assertDatabaseMissing('wla_activities', ['activity_name' => 'Automatic order overflow']);
    }

    public function test_aggregate_storage_overflow_rolls_back_the_last_activity(): void
    {
        [$user, $assessment] = $this->context();
        $payload = $this->activityPayload([
            'frequency' => WlaActivity::MAX_INPUT_VALUE,
            'time_allocated_hours' => WlaActivity::MAX_INPUT_VALUE,
            'period_unit' => WlaPeriodUnit::Year->value,
        ]);

        for ($index = 1; $index <= 6; $index++) {
            $this->actingAs($user)->post(route('wla.activities.store', $assessment), [
                ...$payload,
                'activity_name' => "Maximum {$index}",
            ])->assertRedirect();
        }

        $this->post(route('wla.activities.store', $assessment), [
            ...$payload,
            'activity_name' => 'Overflow activity',
        ])->assertSessionHasErrors('calculation');

        $this->assertDatabaseCount('wla_activities', 6);
        $this->assertDatabaseMissing('wla_activities', ['activity_name' => 'Overflow activity']);
    }

    /** @return array{0: User, 1: WlaAssessment} */
    private function context(): array
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'D01', 'name' => 'Operations', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U01', 'name' => 'Production', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P01', 'name' => 'Operator', 'active' => true]);
        $schedule = WorkSchedule::create([
            'code' => 'DAYSHIFT', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => 2025, 'total_days' => 1, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ]);
        $assessment = WlaAssessment::query()->create([
            'assessment_code' => 'WLA-2025-000001', 'period' => 2025,
            'department_id' => $department->id, 'unit_id' => $unit->id, 'position_id' => $position->id,
            'work_schedule_id' => $schedule->id, 'work_calendar_id' => $calendar->id,
            'efficiency_factor' => '0.9000', 'status' => WlaAssessmentStatus::Draft, 'created_by' => $user->id,
        ]);

        return [$user, $assessment];
    }

    private function activityPayload(array $overrides = []): array
    {
        return array_replace([
            'activity_name' => 'Inspect equipment',
            'frequency' => '2.50',
            'period_unit' => WlaPeriodUnit::Week->value,
            'time_allocated_hours' => '1.75',
        ], $overrides);
    }

    private function legacyActivityPayload(array $overrides = []): array
    {
        return array_replace([
            'activity_name' => 'Legacy activity', 'frequency' => '2.00', 'frequency_unit' => 'Day',
            'volume' => '3.00', 'volume_unit' => 'Month', 'time_allocated' => '1.50',
            'time_unit' => 'Hour', 'sort_order' => 0,
        ], $overrides);
    }
}
