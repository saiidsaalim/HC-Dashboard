<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaParticipantStatus;
use App\Enums\WlaPeriodUnit;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WlaParticipant;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WlaStaffSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_only_be_linked_to_one_user(): void
    {
        $data = $this->context();
        $otherStaff = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($data['superAdmin'])->put(route('konfigurasi-user.update', $otherStaff), [
            'name' => $otherStaff->name,
            'email' => $otherStaff->email,
            'employee_id' => $data['employee']->id,
        ])->assertSessionHasErrors('employee_id');

        $this->assertSame($data['staff']->id, $data['employee']->fresh()->user->id);
        $this->assertSame($data['employee']->id, $data['staff']->fresh()->employee->id);

        $data['employee']->delete();
        $this->assertNotNull($data['staff']->fresh());
        $this->assertNull($data['staff']->fresh()->employee_id);
    }

    public function test_unlinked_staff_sees_explanation_and_cannot_open_another_submission(): void
    {
        $data = $this->context();
        $unlinked = User::factory()->create(['role' => UserRole::STAFF->value]);
        $participant = $this->participant($data);

        $this->actingAs($unlinked)->get(route('wla.staff.index'))
            ->assertOk()->assertSee('belum terhubung ke data Employee');
        $this->get(route('wla.staff.show', $participant))->assertForbidden();
    }

    public function test_staff_only_sees_own_tasks_and_idor_is_rejected(): void
    {
        $data = $this->context();
        $own = $this->participant($data);
        $otherAssessment = $this->assessment($data, 'WLA-2027-OTHER', 2027);
        $other = $this->staffEmployee($data, '2002');
        $otherParticipant = $otherAssessment->participants()->create([
            'employee_id' => $other['employee']->id,
            'status' => WlaParticipantStatus::Draft,
        ]);

        $this->actingAs($data['staff'])->get(route('wla.staff.index'))
            ->assertOk()
            ->assertSee($own->assessment->assessment_code)
            ->assertDontSee($otherAssessment->assessment_code);
        $this->get(route('wla.staff.show', $otherParticipant))->assertForbidden();
    }

    public function test_staff_can_create_update_and_delete_own_draft_activity_with_shared_formula(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);

        $this->actingAs($data['staff'])->post(route('wla.staff.activities.store', $participant), $this->activityPayload())
            ->assertRedirect(route('wla.staff.show', $participant));
        $activity = $participant->activities()->sole();
        $this->assertSame('678.0000', $activity->annual_workload_hours);

        $this->put(route('wla.staff.activities.update', [$participant, $activity]), $this->activityPayload([
            'frequency' => '1.00',
            'time_allocated_hours' => '1.00',
        ]))->assertRedirect(route('wla.staff.show', $participant));
        $this->assertSame('226.0000', $activity->fresh()->annual_workload_hours);

        $this->delete(route('wla.staff.activities.destroy', [$participant, $activity]))->assertRedirect();
        $this->assertDatabaseMissing('wla_participant_activities', ['id' => $activity->id]);
    }

    public function test_submit_return_resubmit_and_approve_enforce_editability(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);
        $this->actingAs($data['staff'])->post(route('wla.staff.activities.store', $participant), $this->activityPayload());
        $activity = $participant->activities()->sole();

        $this->post(route('wla.staff.submit', $participant))->assertRedirect();
        $this->assertSame(WlaParticipantStatus::Submitted, $participant->fresh()->status);
        $this->put(route('wla.staff.activities.update', [$participant, $activity]), $this->activityPayload())->assertForbidden();

        $this->actingAs($data['manager'])->post(route('wla.participants.return', [$data['assessment'], $participant]), [
            'review_note' => 'Perjelas frekuensi aktivitas.',
        ])->assertRedirect();
        $this->assertSame(WlaParticipantStatus::Returned, $participant->fresh()->status);
        $this->actingAs($data['staff'])->put(
            route('wla.staff.activities.update', [$participant, $activity]),
            $this->activityPayload(['frequency' => '3.00']),
        )->assertRedirect();

        $this->post(route('wla.staff.submit', $participant));
        $this->actingAs($data['manager'])->post(route('wla.participants.approve', [$data['assessment'], $participant]))
            ->assertRedirect();
        $this->assertSame(WlaParticipantStatus::Approved, $participant->fresh()->status);
        $this->actingAs($data['staff'])->delete(route('wla.staff.activities.destroy', [$participant, $activity]))
            ->assertForbidden();
    }

    public function test_manager_review_requires_note_and_cannot_approve_empty_submission(): void
    {
        $data = $this->context();
        $participant = $this->participant($data, WlaParticipantStatus::Submitted);

        $this->actingAs($data['manager'])->post(route('wla.participants.return', [$data['assessment'], $participant]), [])
            ->assertSessionHasErrors('review_note');
        $this->post(route('wla.participants.approve', [$data['assessment'], $participant]))
            ->assertSessionHasErrors('review');
        $this->assertSame(WlaParticipantStatus::Submitted, $participant->fresh()->status);
    }

    public function test_member_cannot_access_staff_submission_routes(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);
        $member = User::factory()->create(['role' => UserRole::MEMBER->value]);

        $this->actingAs($member)->get(route('wla.staff.index'))->assertForbidden();
        $this->get(route('wla.staff.show', $participant))->assertForbidden();
        $this->post(route('wla.staff.activities.store', $participant), $this->activityPayload())->assertForbidden();
    }

    public function test_final_assessment_makes_staff_submission_read_only(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);
        $this->actingAs($data['staff'])->post(route('wla.staff.activities.store', $participant), $this->activityPayload());
        $activity = $participant->activities()->sole();
        $data['assessment']->update(['status' => WlaAssessmentStatus::Final]);

        $this->get(route('wla.staff.show', $participant))->assertOk()->assertSee('read-only');
        $this->put(route('wla.staff.activities.update', [$participant, $activity]), $this->activityPayload())->assertForbidden();
        $this->post(route('wla.staff.submit', $participant))->assertForbidden();
    }

    public function test_staff_response_excludes_sensitive_results_while_manager_keeps_fte(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);
        $data['assessment']->update([
            'fte' => '9.999999',
            'recommended_employees' => 77,
            'final_snapshot' => ['secret' => 'FINAL-SNAPSHOT-SECRET'],
        ]);

        $this->actingAs($data['staff'])->get(route('wla.staff.show', $participant))
            ->assertOk()
            ->assertDontSee('9.999999')
            ->assertDontSee('FINAL-SNAPSHOT-SECRET')
            ->assertDontSee('Rekomendasi Pegawai')
            ->assertDontSee('Pegawai Aktual')
            ->assertDontSee('Gap Headcount');

        $this->actingAs($data['manager'])->get(route('wla.show', $data['assessment']))
            ->assertOk()->assertSee('9,999999');
    }

    public function test_participant_activity_only_changes_official_calculation_after_approved_import(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);
        $this->actingAs($data['staff'])->post(route('wla.staff.activities.store', $participant), $this->activityPayload());

        $this->assertSame('0.000000', $data['assessment']->fresh()->fte);
        $this->post(route('wla.staff.submit', $participant));
        $this->actingAs($data['manager'])->post(route('wla.participants.approve', [$data['assessment'], $participant]));
        $this->assertSame('0.000000', $data['assessment']->fresh()->fte);

        $this->post(route('wla.participants.import-approved', [$data['assessment'], $participant]))->assertRedirect();
        $this->assertDatabaseCount('wla_activities', 1);
        $this->assertNotSame('0.000000', $data['assessment']->fresh()->fte);
        $this->assertNotNull($participant->fresh()->applied_at);
    }

    public function test_only_super_admin_can_assign_valid_staff_from_assessment_hierarchy(): void
    {
        $data = $this->context();
        $otherDepartment = Department::create(['code' => 'D02', 'name' => 'Other', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'U02', 'name' => 'Other Unit', 'active' => true]);
        $otherPosition = $otherUnit->positions()->create(['code' => 'P02', 'name' => 'Other Position', 'active' => true]);
        $otherEmployee = Employee::create([
            'sap' => '9999', 'personal_number' => 'Other Staff',
            'department_id' => $otherDepartment->id, 'unit_id' => $otherUnit->id, 'position_id' => $otherPosition->id,
        ]);
        User::factory()->create(['role' => UserRole::STAFF->value, 'employee_id' => $otherEmployee->id]);

        $this->actingAs($data['superAdmin'])
            ->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $otherEmployee->id])
            ->assertSessionHasErrors('employee_id');
        $this->actingAs($data['manager'])
            ->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $data['employee']->id])
            ->assertForbidden();
    }

    public function test_staff_and_review_forms_use_reusable_sweetalert_attributes(): void
    {
        $data = $this->context();
        $participant = $this->participant($data);
        $this->actingAs($data['staff'])->post(route('wla.staff.activities.store', $participant), $this->activityPayload());

        $this->get(route('wla.staff.show', $participant))
            ->assertSee('data-confirm-submit', false)
            ->assertSee('data-confirm-title="Kirim WLA ke Manager?"', false)
            ->assertSee('data-confirm-title="Hapus aktivitas ini?"', false)
            ->assertDontSee('confirm(', false);

        $this->post(route('wla.staff.submit', $participant));
        $this->actingAs($data['manager'])->get(route('wla.participants.show', [$data['assessment'], $participant]))
            ->assertSee('data-confirm-title="Kembalikan pengisian ini?"', false)
            ->assertSee('data-confirm-title="Setujui pengisian WLA?"', false)
            ->assertDontSee('confirm(', false);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $staff = User::factory()->create(['role' => UserRole::STAFF->value]);
        $department = Department::create(['code' => 'D01', 'name' => 'Operations', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U01', 'name' => 'Production', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P01', 'name' => 'Operator', 'active' => true]);
        $schedule = WorkSchedule::create([
            'code' => 'DAYSHIFT', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => 2026, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ]);
        $employee = Employee::create([
            'sap' => '1001', 'personal_number' => 'Staff Satu',
            'department_id' => $department->id, 'unit_id' => $unit->id, 'position_id' => $position->id,
        ]);
        $staff->update(['employee_id' => $employee->id]);
        $data = compact('superAdmin', 'manager', 'staff', 'employee', 'department', 'unit', 'position', 'schedule', 'calendar');
        $assessment = $this->assessment($data);

        return [...$data, 'assessment' => $assessment];
    }

    /** @param array<string, mixed> $data */
    private function assessment(array $data, string $code = 'WLA-2026-000001', int $period = 2026): WlaAssessment
    {
        $calendar = $data['calendar'];
        if ($period !== $calendar->year) {
            $calendar = WorkCalendar::create([
                'year' => $period, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
                'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
                'sunday_days' => 52, 'active' => true,
            ]);
        }

        return WlaAssessment::create([
            'assessment_code' => $code, 'period' => $period,
            'department_id' => $data['department']->id, 'unit_id' => $data['unit']->id,
            'position_id' => $data['position']->id, 'work_schedule_id' => $data['schedule']->id,
            'work_calendar_id' => $calendar->id, 'efficiency_factor' => '0.9000',
            'status' => WlaAssessmentStatus::Draft, 'created_by' => $data['superAdmin']->id,
            'working_days' => 226, 'working_hours_year' => '1582.00', 'effective_working_hours' => '1423.80',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function participant(array $data, WlaParticipantStatus $status = WlaParticipantStatus::Draft): WlaParticipant
    {
        return $data['assessment']->participants()->create([
            'employee_id' => $data['employee']->id,
            'status' => $status,
            'submitted_at' => $status === WlaParticipantStatus::Submitted ? now() : null,
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array{user: User, employee: Employee}
     */
    private function staffEmployee(array $data, string $sap): array
    {
        $employee = Employee::create([
            'sap' => $sap, 'personal_number' => "Staff {$sap}",
            'department_id' => $data['department']->id, 'unit_id' => $data['unit']->id,
            'position_id' => $data['position']->id,
        ]);
        $user = User::factory()->create(['role' => UserRole::STAFF->value, 'employee_id' => $employee->id]);

        return compact('user', 'employee');
    }

    /** @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function activityPayload(array $overrides = []): array
    {
        return [
            'activity_name' => 'Membuat laporan',
            'frequency' => '2.00',
            'period_unit' => WlaPeriodUnit::Day->value,
            'time_allocated_hours' => '1.50',
            ...$overrides,
        ];
    }
}
