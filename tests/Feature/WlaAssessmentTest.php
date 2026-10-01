<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WlaAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_draft_with_generated_code_and_nullable_snapshots(): void
    {
        $data = $this->context();
        $payload = $this->payload($data);
        unset($payload['efficiency_factor']);
        $payload['assessment_code'] = 'CLIENT-CODE';
        $payload['status'] = 'approved';
        $payload['created_by'] = 999;

        $response = $this->actingAs($data['user'])->post(route('wla.store'), $payload);
        $assessment = WlaAssessment::query()->sole();

        $response->assertRedirect(route('wla.show', $assessment));
        $this->assertMatchesRegularExpression('/^WLA-2026-\d{6,}$/', $assessment->assessment_code);
        $this->assertNotSame('CLIENT-CODE', $assessment->assessment_code);
        $this->assertSame(WlaAssessmentStatus::Draft, $assessment->status);
        $this->assertSame('0.9000', $assessment->efficiency_factor);
        $this->assertEquals($data['user']->id, $assessment->created_by);
        $this->assertNull($assessment->updated_by);
        $this->assertNull($assessment->working_days);
        $this->assertNull($assessment->working_hours_year);
        $this->assertNull($assessment->effective_working_hours);
    }

    public function test_assessment_index_create_show_and_edit_pages_render(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);

        $this->actingAs($data['user'])->get(route('wla'))->assertOk();
        $this->get(route('wla.create'))->assertOk()->assertSee('resetDepartment()')->assertSee('resetUnit()');
        $this->get(route('wla.show', $assessment))->assertOk()->assertSee($assessment->assessment_code);
        $this->get(route('wla.edit', $assessment))->assertOk()->assertSee('Efficiency Factor');
    }

    public function test_foundation_preview_uses_existing_calendar_service_without_persisting_snapshot(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);

        $this->actingAs($data['user'])->get(route('wla.show', $assessment))
            ->assertOk()
            ->assertSee('227')
            ->assertSee('1589.00')
            ->assertSee('1430.10');

        $this->assertNull($assessment->fresh()->working_days);
        $this->assertNull($assessment->fresh()->working_hours_year);
        $this->assertNull($assessment->fresh()->effective_working_hours);
    }

    public function test_draft_can_be_updated_and_deleted_with_activities(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);
        $activity = $assessment->activities()->create($this->activityPayload());
        $other = $this->otherContext($data['user']);
        $updateContext = [...$data, ...$other];

        $this->actingAs($data['user'])->put(route('wla.update', $assessment), $this->payload($updateContext, [
            'period' => 2027,
            'efficiency_factor' => '0.8250',
        ]))->assertRedirect(route('wla.show', $assessment));

        $this->assertDatabaseHas('wla_assessments', [
            'id' => $assessment->id,
            'period' => 2027,
            'department_id' => $other['department']->id,
            'efficiency_factor' => '0.8250',
            'updated_by' => $data['user']->id,
            'status' => 'draft',
        ]);

        $this->delete(route('wla.destroy', $assessment))->assertRedirect(route('wla'));
        $this->assertDatabaseMissing('wla_activities', ['id' => $activity->id]);
        $this->assertDatabaseMissing('wla_assessments', ['id' => $assessment->id]);
    }

    public function test_non_management_role_cannot_manage_assessment(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);
        $member = User::factory()->create(['role' => UserRole::MEMBER->value]);

        $this->actingAs($member)->get(route('wla'))->assertForbidden();
        $this->get(route('wla.create'))->assertForbidden();
        $this->get(route('wla.show', $assessment))->assertForbidden();
        $this->put(route('wla.update', $assessment), $this->payload($data))->assertForbidden();
        $this->delete(route('wla.destroy', $assessment))->assertForbidden();
    }

    public function test_assessment_validation_rejects_missing_records_and_invalid_hierarchy(): void
    {
        $data = $this->context();
        $payload = $this->payload($data);

        foreach ([
            ['field' => 'department_id', 'value' => 999],
            ['field' => 'unit_id', 'value' => 999],
            ['field' => 'position_id', 'value' => 999],
            ['field' => 'work_schedule_id', 'value' => 999],
            ['field' => 'work_calendar_id', 'value' => 999],
        ] as $case) {
            $invalidPayload = [...$payload, $case['field'] => $case['value']];
            $this->actingAs($data['user'])->from(route('wla.create'))
                ->post(route('wla.store'), $invalidPayload)
                ->assertRedirect(route('wla.create'))
                ->assertSessionHasErrors($case['field']);
        }

        $other = $this->otherContext($data['user']);
        $mismatchedPayload = $this->payload($data, ['unit_id' => $other['unit']->id]);
        $this->post(route('wla.store'), $mismatchedPayload)->assertSessionHasErrors('unit_id');

        $mismatchedPosition = $this->payload($data, ['position_id' => $other['position']->id]);
        $this->post(route('wla.store'), $mismatchedPosition)->assertSessionHasErrors('position_id');

        $this->assertDatabaseCount('wla_assessments', 0);
    }

    public function test_efficiency_factor_must_be_between_zero_and_one(): void
    {
        $data = $this->context();

        foreach (['-0.01', '1.01'] as $efficiencyFactor) {
            $this->actingAs($data['user'])->from(route('wla.create'))
                ->post(route('wla.store'), $this->payload($data, ['efficiency_factor' => $efficiencyFactor]))
                ->assertRedirect(route('wla.create'))
                ->assertSessionHasErrors('efficiency_factor');
        }
    }

    public function test_multiple_drafts_for_same_period_and_position_are_allowed(): void
    {
        $data = $this->context();
        $payload = $this->payload($data);

        $this->actingAs($data['user'])->post(route('wla.store'), $payload)->assertRedirect();
        $this->post(route('wla.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('wla_assessments', 2);
        $this->assertSame(2, WlaAssessment::query()->distinct('assessment_code')->count('assessment_code'));
    }

    public function test_all_master_relationships_and_activity_collection_are_available(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);
        $activity = $assessment->activities()->create($this->activityPayload());

        $this->assertSame($data['department']->id, $assessment->department->id);
        $this->assertSame($data['unit']->id, $assessment->unit->id);
        $this->assertSame($data['position']->id, $assessment->position->id);
        $this->assertSame($data['schedule']->id, $assessment->workSchedule->id);
        $this->assertSame($data['calendar']->id, $assessment->workCalendar->id);
        $this->assertSame($data['user']->id, $assessment->creator->id);
        $this->assertSame($assessment->id, $activity->assessment->id);
        $this->assertTrue($data['department']->wlaAssessments->contains('id', $assessment->id));
        $this->assertTrue($data['unit']->wlaAssessments->contains('id', $assessment->id));
        $this->assertTrue($data['position']->wlaAssessments->contains('id', $assessment->id));
        $this->assertSame(1, $assessment->activities->count());
    }

    /** @return array{user: User, department: Department, unit: Unit, position: Position, schedule: WorkSchedule, calendar: WorkCalendar} */
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

        return compact('user', 'department', 'unit', 'position', 'schedule', 'calendar');
    }

    /** @return array{department: Department, unit: Unit, position: Position} */
    private function otherContext(User $user): array
    {
        $department = Department::create(['code' => 'D02', 'name' => 'Maintenance', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U02', 'name' => 'Reliability', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P02', 'name' => 'Analyst', 'active' => true]);

        return compact('department', 'unit', 'position');
    }

    private function payload(array $data, array $overrides = []): array
    {
        return array_replace([
            'period' => 2026,
            'department_id' => $data['department']->id,
            'unit_id' => $data['unit']->id,
            'position_id' => $data['position']->id,
            'work_schedule_id' => $data['schedule']->id,
            'work_calendar_id' => $data['calendar']->id,
            'efficiency_factor' => '0.9000',
        ], $overrides);
    }

    private function createAssessment(array $data): WlaAssessment
    {
        return WlaAssessment::query()->create([
            ...$this->payload($data),
            'assessment_code' => 'WLA-2026-000001',
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $data['user']->id,
        ]);
    }

    private function activityPayload(array $overrides = []): array
    {
        return array_replace([
            'activity_name' => 'Inspect equipment',
            'frequency' => '2.00',
            'frequency_unit' => 'Day',
            'volume' => '1.00',
            'volume_unit' => 'Day',
            'time_allocated' => '1.50',
            'time_unit' => 'Hour',
            'sort_order' => 0,
        ], $overrides);
    }
}
