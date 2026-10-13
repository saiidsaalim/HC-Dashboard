<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaActivityOrigin;
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
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
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
        $this->get(route('wla.participants.show', [$data['assessment'], $participant]))
            ->assertOk()
            ->assertSee('Gunakan sebagai Aktivitas')
            ->assertDontSee('Gunakan sebagai Aktivitas'.' Resmi');

        $this->post(route('wla.participants.import-approved', [$data['assessment'], $participant]))->assertRedirect();
        $this->assertDatabaseCount('wla_activities', 1);
        $officialActivity = $data['assessment']->activities()->sole();
        $this->assertSame(WlaActivityOrigin::Staff, $officialActivity->origin);
        $this->assertSame($data['staff']->id, $officialActivity->created_by);
        $this->assertNull($officialActivity->updated_by);
        $this->actingAs($data['manager'])->put(
            route('wla.activities.update', [$data['assessment'], $officialActivity]),
            $this->activityPayload([
                'frequency' => '3.00',
                'origin' => WlaActivityOrigin::Management->value,
                'created_by' => $data['manager']->id,
                'updated_by' => $data['staff']->id,
            ]),
        )->assertRedirect();
        $officialActivity->refresh();
        $this->assertSame(WlaActivityOrigin::Staff, $officialActivity->origin);
        $this->assertSame($data['staff']->id, $officialActivity->created_by);
        $this->assertSame($data['manager']->id, $officialActivity->updated_by);

        $html = $this->get(route('wla.show', $data['assessment']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<tr class="align-top bg-sky-50">.*?Membuat laporan.*?Dibuat oleh:.*?'.preg_quote($data['staff']->name, '/').'.*?\(Staff\).*?Terakhir diubah oleh:.*?'.preg_quote($data['manager']->name, '/').'.*?\(Manager\).*?<\/tr>/s',
            $html,
        );
        $this->assertStringContainsString('border-l-2 border-sky-200', $html);
        $this->assertStringNotContainsString('Dari'.' Staff', $html);
        $this->assertStringNotContainsString('Aktivitas'.' Resmi', $html);
        $this->assertNotSame('0.000000', $data['assessment']->fresh()->fte);
        $this->assertNotNull($participant->fresh()->applied_at);
    }

    public function test_super_admin_admin_and_manager_can_assign_staff_with_authenticated_assigner(): void
    {
        $data = $this->context();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $superAdminStaff = $this->staffEmployee($data, '2001');
        $adminStaff = $this->staffEmployee($data, '2002');
        $managerStaff = $this->staffEmployee($data, '2003');

        $this->actingAs($data['superAdmin'])
            ->post(route('wla.participants.store', $data['assessment']), [
                'employee_id' => $superAdminStaff['employee']->id,
                'assigned_by' => $admin->id,
            ])->assertRedirect();
        $this->actingAs($admin)
            ->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $adminStaff['employee']->id])
            ->assertRedirect();
        $this->actingAs($data['manager'])
            ->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $managerStaff['employee']->id])
            ->assertRedirect();

        $this->assertDatabaseHas('wla_participants', [
            'employee_id' => $superAdminStaff['employee']->id,
            'assigned_by' => $data['superAdmin']->id,
        ]);
        $this->assertDatabaseHas('wla_participants', [
            'employee_id' => $adminStaff['employee']->id,
            'assigned_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('wla_participants', [
            'employee_id' => $managerStaff['employee']->id,
            'assigned_by' => $data['manager']->id,
        ]);
    }

    public function test_staff_member_invalid_hierarchy_duplicate_and_final_assignment_are_rejected(): void
    {
        $data = $this->context();
        $member = User::factory()->create(['role' => UserRole::MEMBER->value]);
        $validStaff = $this->staffEmployee($data, '2100');

        $this->actingAs($data['staff'])
            ->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $validStaff['employee']->id])
            ->assertForbidden();
        $this->actingAs($member)
            ->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $validStaff['employee']->id])
            ->assertForbidden();

        $otherDepartment = Department::create(['code' => 'D02', 'name' => 'Other', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'U02', 'name' => 'Other Unit', 'active' => true]);
        $otherPosition = $otherUnit->positions()->create(['code' => 'P02', 'name' => 'Other Position', 'active' => true]);
        $crossDepartment = Employee::create([
            'sap' => '2201', 'personal_number' => 'Cross Department',
            'department_id' => $otherDepartment->id, 'unit_id' => $otherUnit->id, 'position_id' => $otherPosition->id,
        ]);
        User::factory()->create(['role' => UserRole::STAFF->value, 'employee_id' => $crossDepartment->id]);

        $otherSameDepartmentUnit = $data['department']->units()->create(['code' => 'U03', 'name' => 'Other Unit', 'active' => true]);
        $otherSameDepartmentPosition = $otherSameDepartmentUnit->positions()->create(['code' => 'P03', 'name' => 'Other Unit Position', 'active' => true]);
        $crossUnit = Employee::create([
            'sap' => '2202', 'personal_number' => 'Cross Unit',
            'department_id' => $data['department']->id, 'unit_id' => $otherSameDepartmentUnit->id,
            'position_id' => $otherSameDepartmentPosition->id,
        ]);
        User::factory()->create(['role' => UserRole::STAFF->value, 'employee_id' => $crossUnit->id]);

        $otherPosition = $data['unit']->positions()->create(['code' => 'P04', 'name' => 'Other Position', 'active' => true]);
        $crossPosition = Employee::create([
            'sap' => '2203', 'personal_number' => 'Cross Position',
            'department_id' => $data['department']->id, 'unit_id' => $data['unit']->id,
            'position_id' => $otherPosition->id,
        ]);
        User::factory()->create(['role' => UserRole::STAFF->value, 'employee_id' => $crossPosition->id]);

        $withoutStaffAccount = Employee::create([
            'sap' => '2204', 'personal_number' => 'No Staff Account',
            'department_id' => $data['department']->id, 'unit_id' => $data['unit']->id,
            'position_id' => $data['position']->id,
        ]);

        $this->actingAs($data['manager']);
        foreach ([$crossDepartment, $crossUnit, $crossPosition, $withoutStaffAccount] as $employee) {
            $this->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $employee->id])
                ->assertSessionHasErrors('employee_id');
        }

        $this->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $validStaff['employee']->id])
            ->assertRedirect();
        $this->post(route('wla.participants.store', $data['assessment']), ['employee_id' => $validStaff['employee']->id])
            ->assertSessionHasErrors('employee_id');

        $finalAssessment = $this->assessment($data, 'WLA-2027-FINAL', 2027);
        $finalAssessment->forceFill(['status' => WlaAssessmentStatus::Final])->save();
        $this->post(route('wla.participants.store', $finalAssessment), ['employee_id' => $validStaff['employee']->id])
            ->assertForbidden();
    }

    public function test_assignment_details_and_legacy_fallback_are_visible_to_management_and_owner(): void
    {
        $data = $this->context();
        $participant = $data['assessment']->participants()->create([
            'employee_id' => $data['employee']->id,
            'assigned_by' => $data['manager']->id,
            'status' => WlaParticipantStatus::Draft,
        ]);

        $this->actingAs($data['manager'])->get(route('wla.show', $data['assessment']))
            ->assertOk()
            ->assertSee('Ditugaskan oleh: '.$data['manager']->name.' (Manager)');
        $this->actingAs($data['staff'])->get(route('wla.staff.show', $participant))
            ->assertOk()
            ->assertSee('Ditugaskan oleh: '.$data['manager']->name.' (Manager)');

        $participant->update(['assigned_by' => null]);
        $this->get(route('wla.staff.show', $participant))
            ->assertOk()
            ->assertSee('Pemberi tugas tidak tercatat (data lama)');
    }

    public function test_assignment_reuses_the_single_existing_participant_route(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => $route->getName() === 'wla.participants.store');

        $this->assertCount(1, $routes);
        $this->assertSame('wla/{wla}/participants', $routes->first()->uri());
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
