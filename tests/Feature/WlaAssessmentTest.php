<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WorkCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class WlaAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_admin_can_create_draft_with_generated_code_and_calculated_snapshots(): void
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
        $this->assertSame(226, $assessment->working_days);
        $this->assertSame('1582.00', $assessment->working_hours_year);
        $this->assertSame('1423.80', $assessment->effective_working_hours);
        $this->assertSame('0.0000', $assessment->total_annual_workload_hours);
        $this->assertSame('0.000000', $assessment->fte);
        $this->assertSame(0, $assessment->recommended_employees);
    }

    public function test_assessment_index_create_show_and_edit_pages_render(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);

        $this->actingAs($data['user'])->get(route('wla'))->assertOk();
        $this->get(route('wla.create'))
            ->assertOk()
            ->assertSee('resetDepartment()')
            ->assertSee('resetUnit()')
            ->assertSee('Faktor mingguan WLA selalu menggunakan 52')
            ->assertSee('bukan sumber formula WLA');
        $this->get(route('wla.show', $assessment))->assertOk()->assertSee($assessment->assessment_code);
        $this->get(route('wla.edit', $assessment))->assertOk()->assertSee('Efficiency Factor');
    }

    public function test_foundation_preview_uses_calendar_service_for_an_unsnapshotted_legacy_record(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);

        $this->actingAs($data['user'])->get(route('wla.show', $assessment))
            ->assertOk()
            ->assertSee('226')
            ->assertSee('1.582,00')
            ->assertSee('1.423,80');

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
            'period' => 2026,
            'efficiency_factor' => '0.8250',
        ]))->assertRedirect(route('wla.show', $assessment));

        $this->assertDatabaseHas('wla_assessments', [
            'id' => $assessment->id,
            'period' => 2026,
            'department_id' => $other['department']->id,
            'efficiency_factor' => '0.8250',
            'updated_by' => $data['user']->id,
            'status' => 'draft',
        ]);
        $this->assertSame('1305.15', $assessment->fresh()->effective_working_hours);

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

    public function test_assessment_rejects_a_calendar_from_another_year(): void
    {
        $data = $this->context();

        $this->actingAs($data['user'])
            ->from(route('wla.create'))
            ->post(route('wla.store'), $this->payload($data, ['period' => 2025]))
            ->assertRedirect(route('wla.create'))
            ->assertSessionHasErrors('work_calendar_id');

        $this->assertDatabaseCount('wla_assessments', 0);
    }

    public function test_efficiency_factor_must_be_between_point_zero_zero_zero_one_and_one(): void
    {
        $data = $this->context();

        foreach (['-0.01', '0', '0.0000', '1.01'] as $efficiencyFactor) {
            $this->actingAs($data['user'])->from(route('wla.create'))
                ->post(route('wla.store'), $this->payload($data, ['efficiency_factor' => $efficiencyFactor]))
                ->assertRedirect(route('wla.create'))
                ->assertSessionHasErrors('efficiency_factor');
        }

        $assessment = $this->createAssessment($data);
        $this->from(route('wla.edit', $assessment))
            ->put(route('wla.update', $assessment), $this->payload($data, ['efficiency_factor' => '0']))
            ->assertRedirect(route('wla.edit', $assessment))
            ->assertSessionHasErrors('efficiency_factor');
        $this->assertSame('0.9000', $assessment->fresh()->efficiency_factor);
    }

    public function test_new_assessment_rejects_inactive_master_records(): void
    {
        $data = $this->context();
        $cases = [
            'department_id' => $data['department'],
            'unit_id' => $data['unit'],
            'position_id' => $data['position'],
            'work_schedule_id' => $data['schedule'],
            'work_calendar_id' => $data['calendar'],
        ];

        foreach ($cases as $field => $master) {
            $master->update(['active' => false]);

            $this->actingAs($data['user'])
                ->from(route('wla.create'))
                ->post(route('wla.store'), $this->payload($data))
                ->assertRedirect(route('wla.create'))
                ->assertSessionHasErrors($field);

            $master->update(['active' => true]);
        }

        $this->assertDatabaseCount('wla_assessments', 0);
    }

    public function test_current_inactive_masters_remain_editable_but_other_inactive_masters_are_rejected(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);

        foreach (['department', 'unit', 'position', 'schedule', 'calendar'] as $key) {
            $data[$key]->update(['active' => false]);
        }

        $this->actingAs($data['user'])
            ->get(route('wla.edit', $assessment))
            ->assertOk()
            ->assertSee('D01')
            ->assertSee('U01')
            ->assertSee('P01')
            ->assertSee('Dayshift')
            ->assertSee('2026')
            ->assertSee('(Inactive)');

        $this->put(route('wla.update', $assessment), $this->payload($data))
            ->assertRedirect(route('wla.show', $assessment));

        $other = $this->otherContext($data['user']);
        foreach (['department', 'unit', 'position'] as $key) {
            $other[$key]->update(['active' => false]);
        }

        $this->from(route('wla.edit', $assessment))
            ->put(route('wla.update', $assessment), $this->payload([...$data, ...$other]))
            ->assertSessionHasErrors(['department_id', 'unit_id', 'position_id']);

        $inactiveSchedule = WorkSchedule::create([
            'code' => 'INACTIVE', 'name' => 'Inactive Schedule', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => false,
        ]);
        $this->put(route('wla.update', $assessment), $this->payload($data, [
            'work_schedule_id' => $inactiveSchedule->id,
        ]))->assertSessionHasErrors('work_schedule_id');

        $inactiveCalendar = WorkCalendar::create([
            'year' => 2027, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => false,
        ]);
        $this->put(route('wla.update', $assessment), $this->payload($data, [
            'period' => 2027,
            'work_calendar_id' => $inactiveCalendar->id,
        ]))->assertSessionHasErrors('work_calendar_id');
    }

    public function test_failed_assessment_recalculation_rolls_back_update(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);
        $mock = Mockery::mock(WlaCalculationService::class, [app(WorkCalendarService::class)])->makePartial();
        $mock->shouldReceive('recalculate')
            ->once()
            ->andThrow(ValidationException::withMessages(['calculation' => 'Forced failure.']));
        $this->app->instance(WlaCalculationService::class, $mock);

        $this->actingAs($data['user'])
            ->from(route('wla.edit', $assessment))
            ->put(route('wla.update', $assessment), $this->payload($data, ['efficiency_factor' => '0.8000']))
            ->assertSessionHasErrors('calculation');

        $assessment->refresh();
        $this->assertSame('0.9000', $assessment->efficiency_factor);
        $this->assertNull($assessment->updated_by);
    }

    public function test_failed_assessment_delete_rolls_back_activities_and_assessment(): void
    {
        $data = $this->context();
        $assessment = $this->createAssessment($data);
        $activity = $assessment->activities()->create($this->activityPayload());
        WlaAssessment::deleting(function (): void {
            throw ValidationException::withMessages(['assessment' => 'Forced failure.']);
        });

        $this->actingAs($data['user'])
            ->from(route('wla.show', $assessment))
            ->delete(route('wla.destroy', $assessment))
            ->assertSessionHasErrors('assessment');

        $this->assertDatabaseHas('wla_assessments', ['id' => $assessment->id]);
        $this->assertDatabaseHas('wla_activities', ['id' => $activity->id]);
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
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
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
