<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WorkScheduleCalculationType;
use App\Exports\WlaFinalSpreadsheet;
use App\Models\Department;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WlaFinalSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class WlaFinalExportTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        parent::tearDown();
    }

    public function test_guest_and_non_management_roles_cannot_print_or_export(): void
    {
        $data = $this->context();
        $assessment = $this->finalAssessment($data);

        $this->get(route('wla.print', $assessment))->assertRedirect(route('login'));
        $this->get(route('wla.export-excel', $assessment))->assertRedirect(route('login'));

        foreach ([UserRole::STAFF, UserRole::MEMBER] as $role) {
            $user = User::factory()->create(['role' => $role->value]);
            $this->actingAs($user)->get(route('wla.print', $assessment))->assertForbidden();
            $this->get(route('wla.export-excel', $assessment))->assertForbidden();
        }
    }

    public function test_management_roles_can_print_and_export_final_wla(): void
    {
        foreach ([UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::MANAGER] as $role) {
            $data = $this->context($role);
            $assessment = $this->finalAssessment($data);

            $this->actingAs($data['user'])->get(route('wla.print', $assessment))
                ->assertOk()
                ->assertSee('ANALISIS BEBAN KERJA');

            $response = $this->get(route('wla.export-excel', $assessment));
            $response->assertOk()->assertDownload();
            $this->rememberDownload($response);
        }
    }

    public function test_draft_cannot_be_printed_or_exported_with_friendly_error(): void
    {
        $data = $this->context();
        $draft = $this->draftAssessment($data);

        $this->actingAs($data['user'])->from(route('wla.show', $draft))
            ->get(route('wla.print', $draft))
            ->assertRedirect(route('wla.show', $draft))
            ->assertSessionHasErrors([
                'wla_export' => 'Hanya WLA Final yang dapat dicetak atau diekspor.',
            ]);
        $this->from(route('wla.show', $draft))
            ->get(route('wla.export-excel', $draft))
            ->assertRedirect(route('wla.show', $draft))
            ->assertSessionHasErrors('wla_export');
    }

    public function test_print_uses_only_the_immutable_snapshot_and_escapes_text(): void
    {
        $data = $this->context();
        $snapshot = $this->snapshot($data, [[
            ...$this->activity(1),
            'activity_name' => '<script>alert("snapshot")</script>',
        ]]);
        $assessment = $this->finalAssessment($data, $snapshot);
        $data['department']->update(['name' => 'Live Department Changed']);
        $data['schedule']->update(['name' => 'Live Schedule Changed']);

        $this->actingAs($data['user'])->get(route('wla.print', $assessment))
            ->assertOk()
            ->assertSee('Snapshot Department')
            ->assertSee('Snapshot Schedule')
            ->assertDontSee('Live Department Changed')
            ->assertDontSee('Live Schedule Changed')
            ->assertSee('&lt;script&gt;alert(&quot;snapshot&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("snapshot")</script>', false)
            ->assertSee('1.234567')
            ->assertSee('2');
    }

    public function test_export_is_a_sanitized_xlsx_that_can_be_reopened(): void
    {
        $data = $this->context();
        $snapshot = $this->snapshot($data);
        $snapshot['position']['code'] = 'POS:/\\?*[] Very Long Position Code 123456789';
        $snapshot['assessment_code'] = 'WLA/FINAL:2025?01';
        $assessment = $this->finalAssessment($data, $snapshot);

        [$response, $workbook] = $this->exportWorkbook($data['user'], $assessment);
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->assertDownload('WLA-2025-POS-Very-Long-Position-Code-123456789-WLA-FINAL-2025-01.xlsx');
        $sheet = $workbook->getActiveSheet();

        $this->assertLessThanOrEqual(31, mb_strlen($sheet->getTitle()));
        $this->assertDoesNotMatchRegularExpression('/[*:\/\\?\[\]]/', $sheet->getTitle());
        $this->assertSame('D-SNAP - Snapshot Department', $sheet->getCell('B14')->getValue());
        $this->assertSame('Snapshot activity 1', $sheet->getCell('B40')->getValue());
        $this->assertSame('1.25', $sheet->getCell('C40')->getValue());
        $this->assertSame('37.5000', $sheet->getCell('M40')->getValue());
        $this->assertSame('456.7890 jam', $sheet->getCell('M61')->getValue());
        $this->assertSame('1.234567', $sheet->getCell('M62')->getValue());
        $this->assertSame('2', $sheet->getCell('M63')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('M62')->getDataType());
        $this->assertSame(1, $workbook->getSheetCount());
        $this->assertSame([], $sheet->getDataValidationCollection());
        $this->assertFalse($this->hasFormula($workbook));
    }

    public function test_export_prevents_formula_injection_and_uses_snapshot_instead_of_live_records(): void
    {
        $data = $this->context();
        $snapshot = $this->snapshot($data, [[
            ...$this->activity(1),
            'activity_name' => '=HYPERLINK("https://example.test","attack")',
        ]]);
        $assessment = $this->finalAssessment($data, $snapshot);
        $data['department']->update(['name' => 'Live Department']);
        $assessment->activities()->create([
            'activity_name' => 'Live activity must not appear', 'frequency' => '9.00',
            'frequency_unit' => 'Day', 'volume' => '1.00', 'volume_unit' => 'Day',
            'time_allocated' => '9.00', 'time_unit' => 'Hour', 'sort_order' => 0,
        ]);
        $calculation = Mockery::mock(WlaCalculationService::class);
        $calculation->shouldNotReceive('recalculate');
        $this->app->instance(WlaCalculationService::class, $calculation);

        [, $workbook] = $this->exportWorkbook($data['user'], $assessment);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('D-SNAP - Snapshot Department', $sheet->getCell('B14')->getValue());
        $this->assertSame("'=HYPERLINK(\"https://example.test\",\"attack\")", $sheet->getCell('B40')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B40')->getDataType());
        $this->assertStringNotContainsString(
            'Live activity must not appear',
            collect($sheet->toArray())->flatten()->implode(' '),
        );
    }

    public function test_more_than_template_capacity_copies_style_and_expands_print_area(): void
    {
        $data = $this->context();
        $activities = [];

        for ($number = 1; $number <= 25; $number++) {
            $activities[] = $this->activity($number);
        }

        $assessment = $this->finalAssessment($data, $this->snapshot($data, $activities));
        [, $workbook] = $this->exportWorkbook($data['user'], $assessment);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('Snapshot activity 25', $sheet->getCell('B64')->getValue());
        $this->assertTrue($sheet->getStyle('B64')->getAlignment()->getWrapText());
        $this->assertNotSame(Border::BORDER_NONE, $sheet->getStyle('B64')->getBorders()->getBottom()->getBorderStyle());
        $this->assertSame('A12:N71', $sheet->getPageSetup()->getPrintArea());
        $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $sheet->getPageSetup()->getOrientation());
        $this->assertSame(PageSetup::PAPERSIZE_A4, $sheet->getPageSetup()->getPaperSize());
        $this->assertSame(1, $sheet->getPageSetup()->getFitToWidth());
        $this->assertSame('pageLayout', $sheet->getSheetView()->getView());
        $this->assertSame('456.7890 jam', $sheet->getCell('M66')->getValue());
    }

    public function test_export_does_not_modify_template_or_final_assessment_and_deletes_temp_after_send(): void
    {
        $data = $this->context();
        $assessment = $this->finalAssessment($data);
        $template = storage_path('template/wla-template.xlsx');
        $templateHash = hash_file('sha256', $template);
        $before = $assessment->getAttributes();
        $this->actingAs($data['user'])->get(route('wla.print', $assessment))->assertOk();
        $response = $this->actingAs($data['user'])->get(route('wla.export-excel', $assessment));
        $binaryResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $binaryResponse);
        $temporaryPath = $binaryResponse->getFile()->getPathname();
        $this->assertFileExists($temporaryPath);

        ob_start();
        $binaryResponse->sendContent();
        ob_end_clean();

        $this->assertFileDoesNotExist($temporaryPath);
        $this->assertSame($templateHash, hash_file('sha256', $template));
        $this->assertSame($before, $assessment->fresh()->getAttributes());
    }

    public function test_invalid_snapshot_returns_a_friendly_error(): void
    {
        $data = $this->context();
        $invalid = $this->finalAssessment($data, ['assessment_code' => 'INCOMPLETE']);

        $this->actingAs($data['user'])->from(route('wla.show', $invalid))
            ->get(route('wla.print', $invalid))
            ->assertSessionHasErrors([
                'wla_export' => 'Struktur snapshot WLA Final tidak lengkap.',
            ]);
        $this->from(route('wla.show', $invalid))
            ->get(route('wla.export-excel', $invalid))
            ->assertSessionHasErrors('wla_export');

        $empty = $this->finalAssessment($data);
        $empty->forceFill(['final_snapshot' => null])->save();
        $this->from(route('wla.show', $empty))
            ->get(route('wla.print', $empty))
            ->assertSessionHasErrors([
                'wla_export' => 'Snapshot WLA Final tidak tersedia atau rusak.',
            ]);
    }

    public function test_missing_template_returns_a_friendly_error(): void
    {
        $data = $this->context();
        $valid = $this->finalAssessment($data);
        $spreadsheet = new WlaFinalSpreadsheet(
            app(WlaFinalSnapshotService::class),
            storage_path('template/missing-wla-template.xlsx'),
        );

        try {
            $spreadsheet->createTemporaryFile($valid);
            $this->fail('Ekspor dengan template hilang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Template Excel WLA tidak tersedia.'],
                $exception->errors()['wla_export'],
            );
        }
    }

    public function test_template_without_expected_sheet_returns_a_friendly_error(): void
    {
        $data = $this->context();
        $assessment = $this->finalAssessment($data);
        $temporaryBase = tempnam(sys_get_temp_dir(), 'wla_wrong_sheet_');
        $this->assertIsString($temporaryBase);
        $temporaryPath = $temporaryBase.'.xlsx';
        unlink($temporaryBase);
        $this->temporaryFiles[] = $temporaryPath;
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('Sheet Lain');
        (new Xlsx($workbook))->save($temporaryPath);
        $spreadsheet = new WlaFinalSpreadsheet(app(WlaFinalSnapshotService::class), $temporaryPath);

        try {
            $spreadsheet->createTemporaryFile($assessment);
            $this->fail('Ekspor tanpa sheet template seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Sheet template WLA tidak ditemukan.'],
                $exception->errors()['wla_export'],
            );
        }
    }

    public function test_print_and_export_buttons_are_only_shown_for_final_assessments(): void
    {
        $data = $this->context();
        $final = $this->finalAssessment($data);
        $draft = $this->draftAssessment($data);

        $this->actingAs($data['user'])->get(route('wla.show', $final))
            ->assertOk()
            ->assertSee('Cetak')
            ->assertSee('Export Excel')
            ->assertSee(route('wla.print', $final), false)
            ->assertSee(route('wla.export-excel', $final), false);
        $this->get(route('wla.show', $draft))
            ->assertOk()
            ->assertDontSee('Export Excel')
            ->assertDontSee(route('wla.print', $draft), false)
            ->assertDontSee(route('wla.export-excel', $draft), false);
    }

    /** @return array{user: User, department: Department, unit: mixed, position: mixed, schedule: WorkSchedule, calendar: WorkCalendar} */
    private function context(UserRole $role = UserRole::SUPER_ADMIN): array
    {
        $suffix = str_pad((string) (Department::query()->count() + 1), 3, '0', STR_PAD_LEFT);
        $user = User::factory()->create(['role' => $role->value]);
        $department = Department::create(['code' => "D{$suffix}", 'name' => 'Live Department', 'active' => true]);
        $unit = $department->units()->create(['code' => "U{$suffix}", 'name' => 'Live Unit', 'active' => true]);
        $position = $unit->positions()->create(['code' => "P{$suffix}", 'name' => 'Live Position', 'active' => true]);
        $schedule = WorkSchedule::create([
            'code' => "DAYSHIFT_{$suffix}", 'name' => 'Live Schedule', 'schedule_type' => 'Dayshift',
            'calculation_type' => WorkScheduleCalculationType::Dayshift,
            'working_hours_per_day' => '7.00', 'working_days_per_week' => 5, 'active' => true,
        ]);
        $calendarYear = 2024 + (int) $suffix;
        $calendar = WorkCalendar::create([
            'year' => $calendarYear, 'total_days' => 365, 'total_weeks' => 52, 'annual_leave' => 12,
            'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
            'sunday_days' => 52, 'active' => true,
        ]);

        return compact('user', 'department', 'unit', 'position', 'schedule', 'calendar');
    }

    private function draftAssessment(array $data): WlaAssessment
    {
        return WlaAssessment::create([
            'assessment_code' => 'DRAFT-'.(WlaAssessment::query()->count() + 1),
            'period' => 2025, 'department_id' => $data['department']->id,
            'unit_id' => $data['unit']->id, 'position_id' => $data['position']->id,
            'work_schedule_id' => $data['schedule']->id, 'work_calendar_id' => $data['calendar']->id,
            'efficiency_factor' => '0.9000', 'status' => WlaAssessmentStatus::Draft,
            'created_by' => $data['user']->id,
        ]);
    }

    /** @param array<string, mixed>|null $snapshot */
    private function finalAssessment(array $data, ?array $snapshot = null): WlaAssessment
    {
        $assessment = $this->draftAssessment($data);
        $assessment->forceFill([
            'status' => WlaAssessmentStatus::Final,
            'finalized_at' => now(),
            'finalized_by' => $data['user']->id,
            'finalization_key' => "2025:{$data['position']->id}:{$assessment->id}",
            'final_snapshot' => $snapshot ?? $this->snapshot($data),
        ])->save();

        return $assessment->refresh();
    }

    /** @param array<int, array<string, mixed>>|null $activities */
    private function snapshot(array $data, ?array $activities = null): array
    {
        return [
            'assessment_code' => 'WLA-FINAL/2025:01',
            'period' => 2025,
            'department' => ['id' => 101, 'code' => 'D-SNAP', 'name' => 'Snapshot Department'],
            'unit' => ['id' => 102, 'code' => 'U-SNAP', 'name' => 'Snapshot Unit'],
            'position' => ['id' => 103, 'code' => 'P-SNAP', 'name' => 'Snapshot Position'],
            'schedule' => [
                'id' => 104, 'code' => 'DAYSHIFT', 'name' => 'Snapshot Schedule',
                'calculation_type' => 'dayshift', 'calculation_type_label' => 'Dayshift',
                'wla_hours_per_day' => '7.00',
            ],
            'calendar' => [
                'id' => 105, 'year' => 2025, 'total_days' => 365, 'annual_leave' => 12,
                'national_holiday' => 17, 'common_leave' => 6, 'saturday_days' => 52,
                'sunday_days' => 52,
            ],
            'efficiency_factor' => '0.9000',
            'working_days' => 226,
            'annual_working_hours' => '1582.00',
            'effective_annual_working_hours' => '1423.80',
            'activities' => $activities ?? [$this->activity(1)],
            'total_annual_workload' => '456.7890',
            'fte' => '1.234567',
            'recommended_employees' => 2,
            'finalizer' => ['id' => $data['user']->id, 'name' => 'Snapshot Finalizer'],
            'finalized_at' => '2026-10-08T10:30:00+08:00',
        ];
    }

    /** @return array<string, mixed> */
    private function activity(int $number): array
    {
        return [
            'activity_name' => "Snapshot activity {$number}",
            'frequency' => '1.25',
            'period_unit' => 'Month',
            'time_allocated_hours' => '2.50',
            'annual_workload_hours' => '37.5000',
            'sort_order' => $number - 1,
            'notes' => null,
        ];
    }

    /** @return array{TestResponse, Spreadsheet} */
    private function exportWorkbook(User $user, WlaAssessment $assessment): array
    {
        $response = $this->actingAs($user)->get(route('wla.export-excel', $assessment));
        $response->assertOk()->assertDownload();
        $path = $this->rememberDownload($response);

        return [$response, IOFactory::load($path)];
    }

    private function rememberDownload(TestResponse $response): string
    {
        $binaryResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $binaryResponse);
        $path = $binaryResponse->getFile()->getPathname();
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function hasFormula(Spreadsheet $spreadsheet): bool
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                if ($sheet->getCell($coordinate)->isFormula()) {
                    return true;
                }
            }
        }

        return false;
    }
}
