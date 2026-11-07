<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_analysis_filter_returns_to_analysis_with_a_visible_error(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->from(route('dashboard'))->get(route('formasi', ['search' => ['invalid']]))
            ->assertRedirect(route('formasi'))->assertSessionHasErrors('search');

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('formasi'))->assertSee('Pencarian harus berupa teks.');
    }

    public function test_unconfirmed_requirement_and_gap_filters_are_rejected_with_an_explanation(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->get(route('formasi', ['confirmation' => 'unconfirmed', 'gap_status' => 'vacant']))
            ->assertRedirect(route('formasi'))->assertSessionHasErrors('gap_status');

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('formasi'))->assertSee('Status gap hanya berlaku untuk kebutuhan yang sudah ditetapkan.');
    }

    public function test_conflicting_organization_filters_are_rejected_instead_of_silently_showing_empty_results(): void
    {
        $first = Department::create(['code' => 'FIRST', 'name' => 'First']);
        $second = Department::create(['code' => 'SECOND', 'name' => 'Second']);
        $unit = $second->units()->create(['code' => 'UNIT', 'name' => 'Unit']);

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->get(route('formasi', ['department_id' => $first->id, 'unit_id' => $unit->id]))
            ->assertRedirect(route('formasi'))->assertSessionHasErrors('unit_id');
    }

    public function test_invalid_structure_filter_returns_to_structure_with_a_visible_error(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->from(route('dashboard'))->get(route('formasi.structure', ['department_id' => 'invalid']))
            ->assertRedirect(route('formasi.structure'))->assertSessionHasErrors('department_id');

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('formasi.structure'))->assertSee('Department yang dipilih tidak valid.');
    }

    public function test_deleting_a_position_does_not_silently_unmap_its_employees(): void
    {
        $position = $this->position();
        $employee = $position->employees()->create(['sap' => 'LINKED']);

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->from(route('organization.positions.index'))->delete(route('organization.positions.destroy', $position))
            ->assertRedirect(route('organization.positions.index'))->assertSessionHasErrors('position');

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'position_id' => $position->id]);
    }

    public function test_omitting_parent_during_cross_department_move_does_not_keep_an_invalid_parent(): void
    {
        $position = $this->position();
        $parent = $position->unit->positions()->create(['code' => 'PARENT', 'name' => 'Parent', 'job_level' => Position::JOB_LEVEL_MANAGER]);
        $position->update(['parent_position_id' => $parent->id]);
        $department = Department::create(['code' => 'OTHER', 'name' => 'Other']);
        $unit = $department->units()->create(['code' => 'OTHER', 'name' => 'Other']);

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->from(route('organization.positions.edit', $position))
            ->put(route('organization.positions.update', $position), [
                'department_id' => $department->id, 'unit_id' => $unit->id,
                'code' => $position->code, 'name' => $position->name, 'active' => '1',
            ])->assertSessionHasErrors('parent_position_id');

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'unit_id' => $position->unit_id, 'parent_position_id' => $parent->id]);
    }

    public function test_deleting_a_position_used_by_wla_returns_an_actionable_error(): void
    {
        $position = $this->position();
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $schedule = WorkSchedule::create([
            'code' => 'DAY', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => 7, 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => 2026, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ]);
        $assessment = WlaAssessment::create([
            'assessment_code' => 'WLA-FORMATION', 'period' => 2026, 'department_id' => $position->unit->department_id,
            'unit_id' => $position->unit_id, 'position_id' => $position->id, 'work_schedule_id' => $schedule->id,
            'work_calendar_id' => $calendar->id, 'efficiency_factor' => '0.9', 'created_by' => $actor->id,
        ]);

        $this->actingAs($actor)->from(route('organization.positions.index'))
            ->delete(route('organization.positions.destroy', $position))
            ->assertRedirect(route('organization.positions.index'))
            ->assertSessionHasErrors(['position' => 'Position tidak dapat dihapus selama masih digunakan pada WLA.']);

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('wla_assessments', ['id' => $assessment->id, 'position_id' => $position->id]);
    }

    public function test_formation_master_navigation_stays_inside_the_module_for_hc_od(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]));

        foreach (['organization.index', 'organization.positions.index', 'organization.units.index'] as $route) {
            $response = $this->get(route($route));
            $response->assertOk()->assertSee('aria-label="Navigasi Formasi"', false)
                ->assertDontSee('Kembali ke Formasi');
            $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Navigasi Formasi"'));
            $this->assertMatchesRegularExpression('/href="'.preg_quote(route('formasi.master'), '/').'"\s+aria-current="page"/', $response->getContent());
        }

        $this->withSession(['active_workspace' => 'performance-talent-management'])
            ->get(route('organization.index'))->assertOk()
            ->assertDontSee('aria-label="Navigasi Formasi"', false)
            ->assertSee('title="Master Organization"', false);
    }

    public function test_empty_pagination_after_confirming_a_filtered_position_recovers_and_keeps_feedback(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]));
        $this->withSession(['status' => 'Kebutuhan formasi berhasil ditetapkan.'])
            ->get(route('formasi', ['confirmation' => 'unconfirmed', 'page' => 2]))
            ->assertRedirect(route('formasi', ['confirmation' => 'unconfirmed', 'page' => 1]));

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('formasi', ['confirmation' => 'unconfirmed', 'page' => 1]))
            ->assertOk()->assertSee('Kebutuhan formasi berhasil ditetapkan.');
    }

    private function position(): Position
    {
        $department = Department::create(['code' => 'DEPT', 'name' => 'Department']);
        $unit = $department->units()->create(['code' => 'UNIT', 'name' => 'Unit']);

        return $unit->positions()->create(['code' => 'POSITION', 'name' => 'Position']);
    }
}
