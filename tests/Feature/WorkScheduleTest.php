<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Database\Seeders\WorkScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_super_admin_can_view_work_schedule_pages(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'VIEW',
            'name' => 'View Schedule',
            'schedule_type' => 'fixed',
            'working_hours_per_day' => 8,
            'working_days_per_week' => 5,
            'active' => true,
        ]);

        $this->actingAs($this->superAdmin());

        $this->get(route('work-schedules.index'))->assertOk();
        $this->get(route('work-schedules.create'))
            ->assertOk()
            ->assertSee('Jam harian formula WLA berasal dari Kelompok Kalkulasi WLA');
        $this->get(route('work-schedules.edit', $schedule))->assertOk();
    }

    public function test_work_schedule_delete_form_uses_reusable_sweet_alert_confirmation(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'DELETE-FORM',
            'name' => 'Delete Form Schedule',
            'schedule_type' => 'fixed',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => 8,
            'working_days_per_week' => 5,
            'active' => true,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('work-schedules.index'))
            ->assertOk()
            ->assertSee('action="'.route('work-schedules.destroy', $schedule).'"', false)
            ->assertSee('data-confirm-delete', false)
            ->assertSee('data-confirm-title="Hapus Work Schedule ini?"', false)
            ->assertSee('data-confirm-text="Work Schedule hanya dapat dihapus jika tidak sedang digunakan. Data yang dihapus tidak dapat dikembalikan."', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('onsubmit=', false);
    }

    public function test_super_admin_can_create_a_work_schedule(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('work-schedules.store'), [
                'code' => 'SHIFT_1',
                'name' => 'Shift 1',
                'schedule_type' => 'Shift 1/2/3',
                'calculation_type' => WorkScheduleCalculationType::Shift123->value,
                'working_hours_per_day' => '7.50',
                'working_days_per_week' => 6,
                'active' => true,
                'description' => 'Standard day shift',
            ])->assertRedirect(route('work-schedules.index'));

        $this->assertDatabaseHas('work_schedules', [
            'code' => 'SHIFT_1',
            'name' => 'Shift 1',
            'schedule_type' => 'Shift 1/2/3',
            'calculation_type' => WorkScheduleCalculationType::Shift123->value,
            'working_hours_per_day' => '7.50',
            'working_days_per_week' => 6,
        ]);

        $schedule = WorkSchedule::query()->where('code', 'SHIFT_1')->firstOrFail();
        $this->assertSame('7.50', $schedule->working_hours_per_day);
        $this->assertSame('45.00', $schedule->working_hours_per_week);
        $this->assertTrue(DB::table('work_schedules')->where('code', 'SHIFT_1')->whereRaw('working_hours_per_day * 100 = ?', [750])->exists());
    }

    public function test_work_schedule_seeder_matches_excel_and_is_idempotent(): void
    {
        $this->seed(WorkScheduleSeeder::class);
        $this->seed(WorkScheduleSeeder::class);

        $expectedSchedules = [
            ['code' => 'DAYSHIFT', 'name' => 'Dayshift', 'hours' => '7.00', 'days' => 5, 'weekly' => '35.00'],
            ['code' => 'SHIFT_1', 'name' => 'Shift 1', 'hours' => '7.50', 'days' => 6, 'weekly' => '45.00'],
            ['code' => 'SHIFT_2', 'name' => 'Shift 2', 'hours' => '6.50', 'days' => 6, 'weekly' => '39.00'],
            ['code' => 'SHIFT_3', 'name' => 'Shift 3', 'hours' => '8.50', 'days' => 6, 'weekly' => '51.00'],
            ['code' => 'SHIFT_7_7', 'name' => 'Shift 7/7', 'hours' => '10.00', 'days' => 4, 'weekly' => '40.00'],
            ['code' => 'SHIFT_7_19', 'name' => 'Shift 7/19', 'hours' => '10.00', 'days' => 4, 'weekly' => '40.00'],
            ['code' => 'SHIFT_19_7', 'name' => 'Shift 19/7', 'hours' => '10.00', 'days' => 4, 'weekly' => '40.00'],
        ];

        $this->assertDatabaseCount('work_schedules', count($expectedSchedules));

        foreach ($expectedSchedules as $expected) {
            $schedule = WorkSchedule::query()->where('code', $expected['code'])->firstOrFail();

            $this->assertSame($expected['name'], $schedule->name);
            $this->assertSame($expected['hours'], $schedule->working_hours_per_day);
            $this->assertSame($expected['days'], $schedule->working_days_per_week);
            $this->assertSame($expected['weekly'], $schedule->working_hours_per_week);
        }
    }

    public function test_super_admin_can_update_and_delete_a_work_schedule(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'SHIFT_1',
            'name' => 'Shift 1',
            'schedule_type' => 'shift',
            'working_hours_per_day' => 8,
            'working_days_per_week' => 6,
            'active' => true,
            'description' => 'Shift 1',
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('work-schedules.update', $schedule), [
                'code' => 'SHIFT_1',
                'name' => 'Shift 1 Updated',
                'schedule_type' => 'shift',
                'calculation_type' => WorkScheduleCalculationType::Shift123->value,
                'working_hours_per_day' => 7,
                'working_days_per_week' => 6,
                'active' => true,
                'description' => 'Updated',
            ])->assertRedirect(route('work-schedules.index'));

        $this->assertDatabaseHas('work_schedules', ['id' => $schedule->id, 'name' => 'Shift 1 Updated']);

        $this->actingAs($this->superAdmin())
            ->delete(route('work-schedules.destroy', $schedule))
            ->assertRedirect(route('work-schedules.index'));

        $this->assertDatabaseMissing('work_schedules', ['id' => $schedule->id]);
    }

    public function test_work_schedule_used_by_an_assessment_cannot_be_deleted(): void
    {
        $user = $this->superAdmin();
        $schedule = WorkSchedule::create([
            'code' => 'USED-SCHEDULE',
            'name' => 'Used Schedule',
            'schedule_type' => 'fixed',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => 8,
            'working_days_per_week' => 5,
            'active' => true,
        ]);
        $calendar = WorkCalendar::create([
            'year' => 2035,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 10,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'active' => true,
        ]);
        $this->createAssessment($schedule, $calendar, $user);

        $this->actingAs($user)
            ->from(route('work-schedules.index'))
            ->delete(route('work-schedules.destroy', $schedule))
            ->assertRedirect(route('work-schedules.index'))
            ->assertSessionHasErrors([
                'master' => 'Jadwal kerja tidak dapat dihapus karena masih digunakan oleh WLA.',
            ]);

        $this->assertDatabaseHas('work_schedules', ['id' => $schedule->id]);
    }

    public function test_non_super_admin_cannot_manage_work_schedule(): void
    {
        $schedule = WorkSchedule::create([
            'code' => 'SHIFT_2',
            'name' => 'Shift 2',
            'schedule_type' => 'shift',
            'working_hours_per_day' => 8,
            'working_days_per_week' => 5,
            'active' => true,
            'description' => 'Shift 2',
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->post(route('work-schedules.store'), [
            'code' => 'SHIFT_3',
            'name' => 'Shift 3',
            'schedule_type' => 'shift',
            'working_hours_per_day' => 8,
            'working_days_per_week' => 5,
            'active' => true,
            'description' => 'Shift 3',
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('work-schedules.update', $schedule), [
            'code' => 'SHIFT_2',
            'name' => 'Blocked Shift',
            'schedule_type' => 'shift',
            'working_hours_per_day' => 8,
            'working_days_per_week' => 5,
            'active' => true,
            'description' => 'Blocked',
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('work-schedules.destroy', $schedule))->assertForbidden();
    }

    public function test_work_schedule_validation_rejects_invalid_values(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('work-schedules.create'))
            ->post(route('work-schedules.store'), [
                'code' => '',
                'name' => '',
                'schedule_type' => '',
                'working_hours_per_day' => 0,
                'working_days_per_week' => 0,
                'active' => true,
            ])->assertRedirect(route('work-schedules.create'))
            ->assertSessionHasErrors(['code', 'name', 'schedule_type', 'working_hours_per_day', 'working_days_per_week']);
    }

    public function test_work_schedule_rejects_more_than_two_decimal_places(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('work-schedules.create'))
            ->post(route('work-schedules.store'), [
                'code' => 'BAD_PRECISION',
                'name' => 'Invalid Precision',
                'schedule_type' => 'shift',
                'calculation_type' => WorkScheduleCalculationType::Shift123->value,
                'working_hours_per_day' => '7.555',
                'working_days_per_week' => 6,
                'active' => true,
            ])->assertRedirect(route('work-schedules.create'))
            ->assertSessionHasErrors('working_hours_per_day');
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    private function createAssessment(WorkSchedule $schedule, WorkCalendar $calendar, User $user): WlaAssessment
    {
        $department = Department::create(['code' => 'D-SCHEDULE', 'name' => 'Schedule Department', 'active' => true]);
        $unit = $department->units()->create(['code' => 'U-SCHEDULE', 'name' => 'Schedule Unit', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'P-SCHEDULE', 'name' => 'Schedule Position', 'active' => true]);

        return WlaAssessment::create([
            'assessment_code' => 'WLA-USED-SCHEDULE',
            'period' => $calendar->year,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'work_schedule_id' => $schedule->id,
            'work_calendar_id' => $calendar->id,
            'efficiency_factor' => '0.9000',
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $user->id,
        ]);
    }
}
