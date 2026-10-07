<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaLegacyBackfillCategory;
use App\Enums\WlaPeriodUnit;
use App\Enums\WorkScheduleCalculationType;
use App\Models\Department;
use App\Models\User;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WlaLegacyBackfillService;
use App\Services\Workforce\WorkCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WlaBackfillLegacyCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_default_command_is_a_read_only_dry_run(): void
    {
        $context = $this->context(scheduleOverrides: ['calculation_type' => null]);
        $activity = $this->legacyActivity($context['assessment']);

        $this->artisan('wla:backfill-legacy')
            ->expectsOutputToContain('WLA Legacy Backfill Dry Run')
            ->expectsOutputToContain('Dry-run aktif')
            ->assertSuccessful();

        $this->assertNull($context['schedule']->fresh()->getRawOriginal('calculation_type'));
        $this->assertNull($activity->fresh()->period_unit);
        $this->assertSame('2.00', $activity->fresh()->frequency);
    }

    public function test_apply_requires_an_explicit_confirmation(): void
    {
        $context = $this->context(scheduleOverrides: ['calculation_type' => null]);
        $activity = $this->legacyActivity($context['assessment']);

        $this->artisan('wla:backfill-legacy', ['--apply' => true])
            ->expectsConfirmation('Terapkan backfill WLA untuk seluruh record ready?', 'no')
            ->expectsOutputToContain('Backfill dibatalkan')
            ->assertFailed();

        $this->assertNull($context['schedule']->fresh()->getRawOriginal('calculation_type'));
        $this->assertNull($activity->fresh()->period_unit);

        $this->artisan('wla:backfill-legacy', ['--apply' => true, '--no-interaction' => true])
            ->assertFailed();
        $this->assertNull($activity->fresh()->period_unit);
    }

    public function test_schedule_backfill_uses_only_exact_builtin_codes_and_reports_conflicts(): void
    {
        $expected = [
            'DAYSHIFT' => WorkScheduleCalculationType::Dayshift,
            'SHIFT_1' => WorkScheduleCalculationType::Shift123,
            'SHIFT_2' => WorkScheduleCalculationType::Shift123,
            'SHIFT_3' => WorkScheduleCalculationType::Shift123,
            'SHIFT_7_7' => WorkScheduleCalculationType::Shift77,
            'SHIFT_7_19' => WorkScheduleCalculationType::Shift77,
            'SHIFT_19_7' => WorkScheduleCalculationType::Shift77,
        ];
        $schedules = [];

        foreach ($expected as $code => $calculationType) {
            $schedules[$code] = $this->schedule([
                'code' => $code,
                'name' => $code,
                'calculation_type' => null,
            ]);
        }

        $custom = $this->schedule(['code' => 'CUSTOM_NULL', 'calculation_type' => null]);
        $classifiedCustom = $this->schedule([
            'code' => 'CUSTOM_DONE',
            'calculation_type' => WorkScheduleCalculationType::Shift123,
        ]);
        $this->applyCommand()->assertSuccessful();

        foreach ($expected as $code => $calculationType) {
            $this->assertSame($calculationType->value, $schedules[$code]->fresh()->getRawOriginal('calculation_type'));
        }

        $this->assertNull($custom->fresh()->getRawOriginal('calculation_type'));
        $this->assertSame(
            WorkScheduleCalculationType::Shift123->value,
            $classifiedCustom->fresh()->getRawOriginal('calculation_type'),
        );

        $conflict = $schedules['DAYSHIFT'];
        $conflict->update(['calculation_type' => WorkScheduleCalculationType::Shift77]);
        $this->applyCommand()->assertSuccessful();
        $this->assertSame(
            WorkScheduleCalculationType::Shift77->value,
            $conflict->fresh()->getRawOriginal('calculation_type'),
        );

        $report = app(WlaLegacyBackfillService::class)->audit();
        $this->assertSame(WlaLegacyBackfillCategory::NeedsReview, collect($report['schedules'])->firstWhere('id', $custom->id)['category']);
        $this->assertSame(WlaLegacyBackfillCategory::Conflict, collect($report['schedules'])->firstWhere('id', $conflict->id)['category']);
    }

    #[DataProvider('legacyPeriodProvider')]
    public function test_safe_legacy_activity_is_mapped_for_each_period(string $period): void
    {
        $context = $this->context();
        $activity = $this->legacyActivity($context['assessment'], [
            'frequency_unit' => $period,
            'volume_unit' => $period,
        ]);

        $this->applyCommand()->assertSuccessful();

        $activity->refresh();
        $this->assertSame('6.00', $activity->frequency);
        $this->assertSame(WlaPeriodUnit::from($period), $activity->period_unit);
        $this->assertSame('1.50', $activity->time_allocated_hours);
        $this->assertNotNull($activity->annual_workload_hours);
        $this->assertSame('3.00', $activity->volume);
        $this->assertSame($period, $activity->getRawOriginal('frequency_unit'));
        $this->assertSame($period, $activity->getRawOriginal('volume_unit'));
        $this->assertSame('1.50', $activity->time_allocated);
    }

    /** @return array<string, array{string}> */
    public static function legacyPeriodProvider(): array
    {
        return [
            'day' => ['Day'],
            'week' => ['Week'],
            'month' => ['Month'],
            'year' => ['Year'],
        ];
    }

    public function test_ambiguous_and_partial_activity_fields_are_not_changed(): void
    {
        $context = $this->context();
        $differentUnits = $this->legacyActivity($context['assessment'], [
            'frequency_unit' => 'Day',
            'volume_unit' => 'Month',
        ]);
        $wrongTimeUnit = $this->legacyActivity($context['assessment'], [
            'activity_name' => 'Minutes',
            'sort_order' => 1,
        ]);
        DB::table('wla_activities')->where('id', $wrongTimeUnit->id)->update(['time_unit' => 'Minute']);
        $partial = $this->legacyActivity($context['assessment'], [
            'activity_name' => 'Partial',
            'period_unit' => 'Week',
            'sort_order' => 2,
        ]);

        $this->applyCommand()->assertSuccessful();

        $this->assertNull($differentUnits->fresh()->period_unit);
        $this->assertNull($wrongTimeUnit->fresh()->period_unit);
        $this->assertSame(WlaPeriodUnit::Week, $partial->fresh()->period_unit);
        $this->assertNull($partial->fresh()->time_allocated_hours);

        $report = app(WlaLegacyBackfillService::class)->audit($context['assessment']->id);
        $categories = collect($report['activities'])->pluck('category', 'id');
        $this->assertSame(WlaLegacyBackfillCategory::NeedsReview, $categories[$differentUnits->id]);
        $this->assertSame(WlaLegacyBackfillCategory::NeedsReview, $categories[$wrongTimeUnit->id]);
        $this->assertSame(WlaLegacyBackfillCategory::Conflict, $categories[$partial->id]);
    }

    public function test_already_migrated_activity_is_not_changed(): void
    {
        $context = $this->context();
        $activity = $this->legacyActivity($context['assessment'], [
            'frequency' => '6.00',
            'period_unit' => 'Week',
            'time_allocated_hours' => '1.50',
            'annual_workload_hours' => '468.0000',
        ]);
        $before = $activity->fresh()->getRawOriginal();

        $this->applyCommand()->assertSuccessful();

        $this->assertSame($before, $activity->fresh()->getRawOriginal());
        $report = app(WlaLegacyBackfillService::class)->audit($context['assessment']->id);
        $this->assertSame(WlaLegacyBackfillCategory::AlreadyMigrated, $report['activities'][0]['category']);
        $this->artisan('wla:backfill-legacy', [
            '--assessment' => (string) $context['assessment']->id,
            '--fail-on-review' => true,
        ])->assertSuccessful();
    }

    public function test_invalid_numeric_values_and_overflow_are_not_changed(): void
    {
        $context = $this->context();
        $activities = [
            $this->legacyActivity($context['assessment'], ['activity_name' => 'Zero', 'frequency' => '0', 'sort_order' => 0]),
            $this->legacyActivity($context['assessment'], ['activity_name' => 'Negative', 'volume' => '-1', 'sort_order' => 1]),
            $this->legacyActivity($context['assessment'], ['activity_name' => 'Precision', 'time_allocated' => '1.001', 'sort_order' => 2]),
            $this->legacyActivity($context['assessment'], [
                'activity_name' => 'Overflow',
                'frequency' => '99999999.99',
                'volume' => '9999999999.99',
                'sort_order' => 3,
            ]),
        ];

        $this->applyCommand()->assertSuccessful();

        foreach ($activities as $activity) {
            $this->assertNull($activity->fresh()->period_unit);
        }

        $report = app(WlaLegacyBackfillService::class)->audit($context['assessment']->id);
        $this->assertSame(4, $report['summary']['activity_invalid_or_conflict']);
    }

    public function test_calendar_mismatch_and_unclassified_schedule_skip_assessments(): void
    {
        $mismatch = $this->context(calendarOverrides: ['year' => 2024]);
        $mismatchActivity = $this->legacyActivity($mismatch['assessment']);
        $unclassified = $this->context(scheduleOverrides: [
            'code' => 'CUSTOM_NULL',
            'calculation_type' => null,
        ]);
        $unclassifiedActivity = $this->legacyActivity($unclassified['assessment']);

        $this->applyCommand()->assertSuccessful();

        $this->assertNull($mismatchActivity->fresh()->period_unit);
        $this->assertNull($unclassifiedActivity->fresh()->period_unit);
        $report = app(WlaLegacyBackfillService::class)->audit();
        $assessmentCategories = collect($report['assessments'])->pluck('category', 'id');
        $this->assertSame(WlaLegacyBackfillCategory::Invalid, $assessmentCategories[$mismatch['assessment']->id]);
        $this->assertSame(WlaLegacyBackfillCategory::NeedsReview, $assessmentCategories[$unclassified['assessment']->id]);
    }

    public function test_one_ambiguous_activity_blocks_the_whole_assessment(): void
    {
        $context = $this->context();
        $ready = $this->legacyActivity($context['assessment']);
        $ambiguous = $this->legacyActivity($context['assessment'], [
            'activity_name' => 'Ambiguous',
            'volume_unit' => 'Month',
            'sort_order' => 1,
        ]);

        $this->applyCommand()->assertSuccessful();

        $this->assertNull($ready->fresh()->period_unit);
        $this->assertNull($ambiguous->fresh()->period_unit);
    }

    public function test_recalculation_failure_rolls_back_every_activity_in_the_assessment(): void
    {
        $context = $this->context();
        $first = $this->legacyActivity($context['assessment']);
        $second = $this->legacyActivity($context['assessment'], ['activity_name' => 'Second', 'sort_order' => 1]);
        $mock = Mockery::mock(WlaCalculationService::class, [app(WorkCalendarService::class)])->makePartial();
        $mock->shouldReceive('recalculate')
            ->once()
            ->andThrow(ValidationException::withMessages(['calculation' => 'Forced failure.']));
        $this->app->instance(WlaCalculationService::class, $mock);

        $this->applyCommand()->assertSuccessful();

        $this->assertNull($first->fresh()->period_unit);
        $this->assertNull($second->fresh()->period_unit);
        $this->assertSame('2.00', $first->fresh()->frequency);
    }

    public function test_safe_assessment_recalculates_once_and_second_apply_is_idempotent(): void
    {
        $context = $this->context();
        $activity = $this->legacyActivity($context['assessment']);
        $mock = Mockery::mock(WlaCalculationService::class, [app(WorkCalendarService::class)])->makePartial();
        $mock->shouldReceive('recalculate')->once()->passthru();
        $this->app->instance(WlaCalculationService::class, $mock);

        $this->applyCommand()->assertSuccessful();
        $firstState = [
            'activity' => $activity->fresh()->getRawOriginal(),
            'assessment' => $context['assessment']->fresh()->getRawOriginal(),
        ];

        $this->app->forgetInstance(WlaCalculationService::class);
        $this->app->forgetInstance(WlaLegacyBackfillService::class);
        $this->applyCommand()->assertSuccessful();

        $this->assertSame($firstState['activity'], $activity->fresh()->getRawOriginal());
        $this->assertSame($firstState['assessment'], $context['assessment']->fresh()->getRawOriginal());
    }

    public function test_assessment_option_limits_processing_to_one_assessment(): void
    {
        $first = $this->context();
        $firstActivity = $this->legacyActivity($first['assessment']);
        $second = $this->context(calendarOverrides: ['year' => 2026], assessmentPeriod: 2026);
        $secondActivity = $this->legacyActivity($second['assessment']);

        $this->applyCommand(['--assessment' => (string) $first['assessment']->id])->assertSuccessful();

        $this->assertNotNull($firstActivity->fresh()->period_unit);
        $this->assertNull($secondActivity->fresh()->period_unit);
    }

    public function test_fail_on_review_returns_failure_and_dry_run_matches_apply_classification(): void
    {
        $context = $this->context();
        $this->legacyActivity($context['assessment'], ['volume_unit' => 'Month']);
        $service = app(WlaLegacyBackfillService::class);
        $dryRun = $service->audit();

        $this->artisan('wla:backfill-legacy', ['--fail-on-review' => true])->assertFailed();
        $apply = $service->apply();

        $this->assertSame(
            collect($dryRun['activities'])->pluck('category', 'id')->all(),
            collect($apply['activities'])->pluck('category', 'id')->all(),
        );
    }

    public function test_csv_report_is_only_created_when_requested(): void
    {
        $context = $this->context();
        $this->legacyActivity($context['assessment']);
        $directory = sys_get_temp_dir().'/wla-backfill-'.uniqid();
        $path = $directory.'/report.csv';

        $this->artisan('wla:backfill-legacy')->assertSuccessful();
        $this->assertFileDoesNotExist($path);

        $this->artisan('wla:backfill-legacy', ['--report' => $path])
            ->expectsOutputToContain('Report CSV dibuat')
            ->assertSuccessful();
        $this->assertFileExists($path);
        $this->assertStringContainsString('record_type,record_id,assessment_id,category,reason', file_get_contents($path));

        unlink($path);
        rmdir($directory);
    }

    public function test_command_stops_when_stage_one_columns_are_missing(): void
    {
        config([
            'database.default' => 'wla_missing_stage_one',
            'database.connections.wla_missing_stage_one' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('wla_missing_stage_one');

        $this->artisan('wla:backfill-legacy')
            ->expectsOutput('Migration WLA tahap 1 belum dijalankan.')
            ->assertFailed();
    }

    /** @return array<string, mixed> */
    private function context(
        array $scheduleOverrides = [],
        array $calendarOverrides = [],
        int $assessmentPeriod = 2025,
    ): array {
        $this->sequence++;
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create([
            'code' => 'D'.$this->sequence,
            'name' => 'Department '.$this->sequence,
            'active' => true,
        ]);
        $unit = $department->units()->create([
            'code' => 'U'.$this->sequence,
            'name' => 'Unit '.$this->sequence,
            'active' => true,
        ]);
        $position = $unit->positions()->create([
            'code' => 'P'.$this->sequence,
            'name' => 'Position '.$this->sequence,
            'active' => true,
        ]);
        $schedule = $this->schedule($scheduleOverrides);
        $calendar = WorkCalendar::create(array_replace([
            'year' => 2025,
            'total_days' => 365,
            'total_weeks' => 52,
            'annual_leave' => 12,
            'national_holiday' => 17,
            'common_leave' => 6,
            'saturday_days' => 52,
            'sunday_days' => 52,
            'active' => true,
        ], $calendarOverrides));
        $assessment = WlaAssessment::create([
            'assessment_code' => sprintf('WLA-%04d-%06d', $assessmentPeriod, $this->sequence),
            'period' => $assessmentPeriod,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'work_schedule_id' => $schedule->id,
            'work_calendar_id' => $calendar->id,
            'efficiency_factor' => '0.9000',
            'status' => WlaAssessmentStatus::Draft,
            'created_by' => $user->id,
        ]);

        return compact('user', 'department', 'unit', 'position', 'schedule', 'calendar', 'assessment');
    }

    private function schedule(array $overrides = []): WorkSchedule
    {
        $this->sequence++;
        $code = $overrides['code'] ?? 'CUSTOM_'.$this->sequence;

        if (WorkSchedule::query()->where('code', $code)->exists()) {
            $code .= '_'.$this->sequence;
        }

        return WorkSchedule::create(array_replace([
            'code' => $code,
            'name' => 'Schedule '.$this->sequence,
            'schedule_type' => 'Legacy',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00',
            'working_days_per_week' => 5,
            'active' => true,
        ], $overrides, ['code' => $code]));
    }

    private function legacyActivity(WlaAssessment $assessment, array $overrides = []): WlaActivity
    {
        return $assessment->activities()->create(array_replace([
            'activity_name' => 'Legacy activity',
            'frequency' => '2.00',
            'frequency_unit' => 'Week',
            'volume' => '3.00',
            'volume_unit' => 'Week',
            'time_allocated' => '1.50',
            'time_unit' => 'Hour',
            'sort_order' => 0,
        ], $overrides));
    }

    private function applyCommand(array $options = []): PendingCommand
    {
        return $this->artisan('wla:backfill-legacy', ['--apply' => true, ...$options])
            ->expectsConfirmation('Terapkan backfill WLA untuk seluruh record ready?', 'yes');
    }
}
