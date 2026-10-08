<?php

namespace Database\Seeders;

use App\Enums\WorkScheduleCalculationType;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = [
            ['code' => 'DAYSHIFT', 'name' => 'Dayshift', 'schedule_type' => 'Dayshift', 'calculation_type' => WorkScheduleCalculationType::Dayshift, 'working_hours_per_day' => '7.00', 'working_days_per_week' => 5],
            ['code' => 'SHIFT_1', 'name' => 'Shift 1', 'schedule_type' => 'Shift 1/2/3', 'calculation_type' => WorkScheduleCalculationType::Shift123, 'working_hours_per_day' => '7.50', 'working_days_per_week' => 6],
            ['code' => 'SHIFT_2', 'name' => 'Shift 2', 'schedule_type' => 'Shift 1/2/3', 'calculation_type' => WorkScheduleCalculationType::Shift123, 'working_hours_per_day' => '6.50', 'working_days_per_week' => 6],
            ['code' => 'SHIFT_3', 'name' => 'Shift 3', 'schedule_type' => 'Shift 1/2/3', 'calculation_type' => WorkScheduleCalculationType::Shift123, 'working_hours_per_day' => '8.50', 'working_days_per_week' => 6],
            ['code' => 'SHIFT_7_7', 'name' => 'Shift 7/7', 'schedule_type' => 'Shift 7/7', 'calculation_type' => WorkScheduleCalculationType::Shift77, 'working_hours_per_day' => '10.00', 'working_days_per_week' => 4],
            ['code' => 'SHIFT_7_19', 'name' => 'Shift 7/19', 'schedule_type' => 'Shift 7/7', 'calculation_type' => WorkScheduleCalculationType::Shift77, 'working_hours_per_day' => '10.00', 'working_days_per_week' => 4],
            ['code' => 'SHIFT_19_7', 'name' => 'Shift 19/7', 'schedule_type' => 'Shift 7/7', 'calculation_type' => WorkScheduleCalculationType::Shift77, 'working_hours_per_day' => '10.00', 'working_days_per_week' => 4],
        ];

        foreach ($schedules as $schedule) {
            WorkSchedule::query()->updateOrCreate(
                ['code' => $schedule['code']],
                [...$schedule, 'active' => true],
            );
        }
    }
}
