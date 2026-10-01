<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WorkCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_work_calendar_pages(): void
    {
        $calendar = WorkCalendar::create([
            'year' => 2031,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 10,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'active' => true,
        ]);

        $this->actingAs($this->superAdmin());

        $this->get(route('work-calendars.index'))->assertOk();
        $this->get(route('work-calendars.create'))->assertOk();
        $this->get(route('work-calendars.edit', $calendar))->assertOk();
    }

    public function test_super_admin_can_create_a_work_calendar(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('work-calendars.store'), [
                'year' => 2026,
                'total_days' => 365,
                'total_weeks' => 52,
                'annual_leave' => 12,
                'national_holiday' => 10,
                'common_leave' => 2,
                'saturday_days' => 52,
                'sunday_days' => 52,
                'notes' => 'Baseline 2026',
                'active' => true,
            ])->assertRedirect(route('work-calendars.index'));

        $this->assertDatabaseHas('work_calendars', [
            'year' => 2026,
            'total_days' => 365,
            'annual_leave' => 12,
            'national_holiday' => 10,
            'common_leave' => 2,
        ]);
    }

    public function test_super_admin_can_update_and_delete_a_work_calendar(): void
    {
        $calendar = WorkCalendar::create([
            'year' => 2027,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 9,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'notes' => 'Initial',
            'active' => true,
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('work-calendars.update', $calendar), [
                'year' => 2027,
                'total_days' => 366,
                'total_weeks' => 52,
                'annual_leave' => 13,
                'national_holiday' => 9,
                'common_leave' => 2,
                'saturday_days' => 52,
                'sunday_days' => 52,
                'notes' => 'Updated',
                'active' => true,
            ])->assertRedirect(route('work-calendars.index'));

        $this->assertDatabaseHas('work_calendars', ['id' => $calendar->id, 'notes' => 'Updated', 'total_days' => 366]);

        $this->actingAs($this->superAdmin())
            ->delete(route('work-calendars.destroy', $calendar))
            ->assertRedirect(route('work-calendars.index'));

        $this->assertDatabaseMissing('work_calendars', ['id' => $calendar->id]);
    }

    public function test_duplicate_year_is_rejected(): void
    {
        WorkCalendar::create([
            'year' => 2028,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 11,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'notes' => 'First',
            'active' => true,
        ]);

        $this->actingAs($this->superAdmin())
            ->from(route('work-calendars.create'))
            ->post(route('work-calendars.store'), [
                'year' => 2028,
                'total_days' => 365,
                'total_weeks' => 52,
                'annual_leave' => 12,
                'national_holiday' => 11,
                'common_leave' => 2,
                'saturday_days' => 52,
                'sunday_days' => 52,
                'notes' => 'Duplicate',
                'active' => true,
            ])->assertRedirect(route('work-calendars.create'))
            ->assertSessionHasErrors('year');
    }

    public function test_work_calendar_validation_rejects_invalid_values(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('work-calendars.create'))
            ->post(route('work-calendars.store'), [
                'year' => 2025,
                'total_days' => 0,
                'total_weeks' => 0,
                'annual_leave' => -1,
                'national_holiday' => -1,
                'common_leave' => -1,
                'saturday_days' => -1,
                'sunday_days' => -1,
                'notes' => '',
                'active' => true,
            ])->assertRedirect(route('work-calendars.create'))
            ->assertSessionHasErrors(['total_days', 'total_weeks', 'annual_leave', 'national_holiday', 'common_leave', 'saturday_days', 'sunday_days']);
    }

    public function test_non_super_admin_cannot_manage_work_calendar(): void
    {
        $calendar = WorkCalendar::create([
            'year' => 2029,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 10,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'notes' => 'Protected',
            'active' => true,
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->post(route('work-calendars.store'), [
            'year' => 2030,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 10,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'notes' => 'Blocked',
            'active' => true,
        ])->assertForbidden();

        $this->actingAs($admin)->put(route('work-calendars.update', $calendar), [
            'year' => 2029,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 13,
            'national_holiday' => 10,
            'common_leave' => 2,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'notes' => 'Blocked',
            'active' => true,
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('work-calendars.destroy', $calendar))->assertForbidden();
    }

    public function test_dayshift_calendar_formula_matches_excel_template_values(): void
    {
        $service = app(WorkCalendarService::class);
        $calendar = $this->calendarInput();
        $dayshift = $this->scheduleInput('DAYSHIFT', '7.00', 5);

        $workingDays = $service->calculateWorkingDays($calendar, $dayshift);
        $workingHoursYear = $service->calculateWorkingHoursPerYear($calendar, $dayshift);
        $effectiveWorkingHours = $service->calculateEffectiveWorkingHours($workingHoursYear, '0.90');

        $this->assertSame(227, $workingDays);
        $this->assertSame('1589.00', $workingHoursYear);
        $this->assertSame('1430.10', $effectiveWorkingHours);
        $this->assertSame('119.1750', $service->calculateEffectiveHoursPerMonth($effectiveWorkingHours));
        $this->assertSame('27.5019', $service->calculateEffectiveHoursPerWeek($effectiveWorkingHours, $calendar));
        $this->assertSame('6.3000', $service->calculateEffectiveHoursPerDay($effectiveWorkingHours, $calendar, $dayshift));
    }

    public function test_non_dayshift_uses_zero_conditional_holiday_and_weekend_values(): void
    {
        $service = app(WorkCalendarService::class);
        $calendar = $this->calendarInput();
        $shiftOne = $this->scheduleInput('SHIFT_1', '7.50', 6);

        $this->assertSame(348, $service->calculateWorkingDays($calendar, $shiftOne));
        $this->assertSame('2610.00', $service->calculateWorkingHoursPerYear($calendar, $shiftOne));
    }

    public function test_working_day_and_hour_calculations_keep_zero_floor(): void
    {
        $service = app(WorkCalendarService::class);
        $calendar = $this->calendarInput(['total_days' => 10]);
        $dayshift = $this->scheduleInput('DAYSHIFT', '7.00', 5);

        $this->assertSame(0, $service->calculateWorkingDays($calendar, $dayshift));
        $this->assertSame('0.00', $service->calculateWorkingHoursPerYear($calendar, $dayshift));
    }

    public function test_effective_working_hours_uses_the_supplied_efficiency_factor(): void
    {
        $service = app(WorkCalendarService::class);

        $this->assertSame('1430.10', $service->calculateEffectiveWorkingHours('1589.00', '0.90'));
        $this->assertSame('794.50', $service->calculateEffectiveWorkingHours('1589.00', '0.50'));
        $this->assertSame('0.00', $service->calculateEffectiveWorkingHours('1589.00', '0'));

        try {
            $service->calculateEffectiveWorkingHours('1589.00', '1.01');
            $this->fail('Efficiency factor above 1 should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('efficiency_factor', $exception->errors());
        }

        try {
            $service->calculateEffectiveWorkingHours('1589.00', '1.00001');
            $this->fail('Efficiency factor above 1 must not be rounded into the accepted range.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('efficiency_factor', $exception->errors());
        }

        try {
            $service->calculateEffectiveWorkingHours('1589.00', '-0.01');
            $this->fail('Negative efficiency factor should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('efficiency_factor', $exception->errors());
        }
    }

    public function test_service_rejects_missing_schedule_and_calendar(): void
    {
        $service = app(WorkCalendarService::class);
        $calendar = $this->calendarInput();
        $schedule = $this->scheduleInput('DAYSHIFT', '7.00', 5);

        try {
            $service->calculateWorkingDays($calendar, null);
            $this->fail('A missing schedule should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('work_schedule_id', $exception->errors());
        }

        try {
            $service->calculateWorkingHoursPerYear(null, $schedule);
            $this->fail('A missing calendar should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('work_calendar_id', $exception->errors());
        }
    }

    public function test_effective_hours_per_day_rejects_a_zero_working_day_denominator(): void
    {
        $service = app(WorkCalendarService::class);
        $calendar = $this->calendarInput(['total_days' => 10]);
        $dayshift = $this->scheduleInput('DAYSHIFT', '7.00', 5);

        try {
            $service->calculateEffectiveHoursPerDay('0.00', $calendar, $dayshift);
            $this->fail('A zero working-day denominator should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('working_days', $exception->errors());
        }
    }

    /** @param array<string, int> $overrides */
    private function calendarInput(array $overrides = []): WorkCalendar
    {
        return new WorkCalendar(array_replace([
            'year' => 2025,
            'total_days' => 366,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 17,
            'common_leave' => 6,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'active' => true,
        ], $overrides));
    }

    private function scheduleInput(string $code, string $hoursPerDay, int $daysPerWeek): WorkSchedule
    {
        return new WorkSchedule([
            'code' => $code,
            'name' => $code,
            'schedule_type' => $code,
            'working_hours_per_day' => $hoursPerDay,
            'working_days_per_week' => $daysPerWeek,
            'active' => true,
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
