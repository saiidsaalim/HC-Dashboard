<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaFrequencyUnit;
use App\Enums\WlaTimeUnit;
use App\Enums\WlaVolumeUnit;
use App\Models\Department;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WlaActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_can_be_created_with_decimal_values_and_automatic_order(): void
    {
        [$user, $assessment] = $this->context();

        $this->actingAs($user)
            ->post(route('wla.activities.store', $assessment), $this->activityPayload())
            ->assertRedirect(route('wla.show', $assessment));

        $activity = $assessment->activities()->sole();
        $this->assertSame('2.50', $activity->frequency);
        $this->assertSame(WlaFrequencyUnit::Week, $activity->frequency_unit);
        $this->assertSame('3.25', $activity->volume);
        $this->assertSame(WlaVolumeUnit::Month, $activity->volume_unit);
        $this->assertSame('1.75', $activity->time_allocated);
        $this->assertSame(WlaTimeUnit::Hour, $activity->time_unit);
        $this->assertSame(2, $activity->sort_order);
    }

    public function test_activity_can_be_updated(): void
    {
        [$user, $assessment] = $this->context();
        $activity = $assessment->activities()->create($this->activityPayload(['sort_order' => 0]));

        $this->actingAs($user)
            ->put(route('wla.activities.update', [$assessment, $activity]), $this->activityPayload([
                'activity_name' => 'Updated activity',
                'frequency' => '4.00',
                'time_allocated' => '2.25',
                'sort_order' => 3,
            ]))->assertRedirect(route('wla.show', $assessment));

        $this->assertDatabaseHas('wla_activities', [
            'id' => $activity->id,
            'activity_name' => 'Updated activity',
            'frequency' => '4.00',
            'time_allocated' => '2.25',
            'sort_order' => 3,
        ]);
    }

    public function test_activity_can_be_deleted_from_a_draft(): void
    {
        [$user, $assessment] = $this->context();
        $activity = $assessment->activities()->create($this->activityPayload());

        $this->actingAs($user)
            ->delete(route('wla.activities.destroy', [$assessment, $activity]))
            ->assertRedirect(route('wla.show', $assessment));

        $this->assertDatabaseMissing('wla_activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('wla_assessments', ['id' => $assessment->id, 'status' => 'draft']);
    }

    public function test_activity_name_and_nonnegative_activity_values_are_validated(): void
    {
        [$user, $assessment] = $this->context();
        $payload = $this->activityPayload([
            'activity_name' => '',
            'frequency' => '-1',
            'volume' => '-1',
            'time_allocated' => '0',
        ]);

        $this->actingAs($user)
            ->from(route('wla.show', $assessment))
            ->post(route('wla.activities.store', $assessment), $payload)
            ->assertRedirect(route('wla.show', $assessment))
            ->assertSessionHasErrors(['activity_name', 'frequency', 'volume', 'time_allocated']);
    }

    public function test_activity_units_and_sort_order_are_validated(): void
    {
        [$user, $assessment] = $this->context();
        $payload = $this->activityPayload([
            'frequency_unit' => 'Quarter',
            'volume_unit' => 'Quarter',
            'time_unit' => 'Minute',
            'sort_order' => '1.5',
        ]);

        $this->actingAs($user)
            ->from(route('wla.show', $assessment))
            ->post(route('wla.activities.store', $assessment), $payload)
            ->assertRedirect(route('wla.show', $assessment))
            ->assertSessionHasErrors(['frequency_unit', 'volume_unit', 'time_unit', 'sort_order']);
    }

    public function test_activity_order_increments_and_nested_binding_prevents_cross_assessment_updates(): void
    {
        [$user, $assessment] = $this->context();
        $activity = $assessment->activities()->create($this->activityPayload(['sort_order' => 0]));
        $payload = $this->activityPayload();
        unset($payload['sort_order']);

        $this->actingAs($user)->post(route('wla.activities.store', $assessment), $payload)->assertRedirect();
        $this->assertSame([0, 1], $assessment->activities()->orderBy('sort_order')->pluck('sort_order')->all());

        $otherAssessment = WlaAssessment::query()->create([
            'assessment_code' => 'WLA-2027-000002',
            'period' => 2027,
            'department_id' => $assessment->department_id,
            'unit_id' => $assessment->unit_id,
            'position_id' => $assessment->position_id,
            'work_schedule_id' => $assessment->work_schedule_id,
            'work_calendar_id' => $assessment->work_calendar_id,
            'efficiency_factor' => '0.9000',
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $user->id,
        ]);
        $this->put(route('wla.activities.update', [$otherAssessment, $activity]), $this->activityPayload())
            ->assertNotFound();
    }

    public function test_non_management_role_cannot_mutate_activities(): void
    {
        [$user, $assessment] = $this->context();
        $activity = $assessment->activities()->create($this->activityPayload());
        $member = User::factory()->create(['role' => UserRole::MEMBER->value]);

        $this->actingAs($member)->post(route('wla.activities.store', $assessment), $this->activityPayload())->assertForbidden();
        $this->put(route('wla.activities.update', [$assessment, $activity]), $this->activityPayload())->assertForbidden();
        $this->delete(route('wla.activities.destroy', [$assessment, $activity]))->assertForbidden();
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
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => 2026, 'total_days' => 366, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ]);
        $assessment = WlaAssessment::query()->create([
            'assessment_code' => 'WLA-2026-000001', 'period' => 2026,
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
            'frequency_unit' => WlaFrequencyUnit::Week->value,
            'volume' => '3.25',
            'volume_unit' => WlaVolumeUnit::Month->value,
            'time_allocated' => '1.75',
            'time_unit' => WlaTimeUnit::Hour->value,
            'notes' => 'Shift check',
            'sort_order' => 2,
        ], $overrides);
    }
}
