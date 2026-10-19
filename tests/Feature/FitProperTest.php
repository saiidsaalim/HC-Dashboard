<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FitProperActivity;
use App\Models\FitProperExaminer;
use App\Models\FitProperParticipant;
use App\Models\FitProperScore;
use App\Models\FitProperSetting;
use App\Models\User;
use App\Services\FitProperCalculationService;
use App\Services\FitProperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FitProperTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'must_change_password' => false]);
    }

    private function service(): FitProperService
    {
        return app(FitProperService::class);
    }

    private function activity(?User $admin = null): FitProperActivity
    {
        $admin ??= $this->admin();
        $a = $this->service()->create(['name' => 'Kegiatan uji', 'target_position' => 'Kepala Unit', 'scheduled_at' => '2026-10-19'], $admin);
        $this->service()->assignPics($a, [$admin->id], $admin);

        return $a;
    }

    private function participant(FitProperActivity $a, string $name = 'Kandidat Fiktif', float $kpi = 4, float $assessment = 3): FitProperParticipant
    {
        return $this->service()->participant($a, ['name' => $name, 'kpi_year_1' => $kpi, 'kpi_year_2' => $kpi, 'assessment' => $assessment]);
    }

    private function scores(FitProperActivity $a, float $value = 4): array
    {
        return ['scores' => $a->participants()->get()->mapWithKeys(fn ($p) => [$p->id => array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), $value)])->all()];
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_manual_participant_crud_cv_and_all_manager_pages(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('fit-proper.store'), ['name' => 'Uji manual', 'target_position' => 'Kepala', 'scheduled_at' => '2026-10-19', 'status' => 'Selesai', 'is_simulation' => true])->assertRedirect();
        $a = FitProperActivity::sole();
        $this->assertSame('Draft', $a->status);
        $this->assertTrue($a->is_simulation);
        $employeeCount = Employee::count();
        $this->post(route('fit-proper.participants.store', $a), ['name' => 'Manual Fiktif', 'birth_date' => '1990-01-01', 'current_jg' => 12, 'target_jg' => 10, 'kpi_year_1' => 0, 'kpi_year_2' => 0, 'assessment' => 0, 'cv' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])->assertRedirect();
        $p = $a->participants()->sole();
        $this->assertSame($a->id, $p->fit_proper_activity_id);
        $this->assertSame($a->id, $p->activity->id);
        $this->assertFalse(Schema::hasColumn('fit_proper_participants', 'activity_id'));
        $this->assertNull($p->employee_id);
        $this->assertSame($employeeCount, Employee::count());
        $this->assertSame(0.0, $p->kpi());
        $this->assertSame(2, $p->profile['gap_jg']);
        $this->assertNotNull($p->age());
        $identity = $p->internal_identity;
        $this->get(route('fit-proper'))->assertOk()->assertSee('Uji manual');
        $this->get(route('fit-proper.show', $a))->assertOk()->assertSee('Manual Fiktif');
        $this->get(route('fit-proper.participants.edit', [$a, $p]))->assertOk();
        $this->get(route('fit-proper.cv', [$a, $p]))->assertOk();
        $this->get(route('fit-proper.print', $a))->assertOk()->assertSee('Sementara');
        $this->get(route('fit-proper.excel', $a))->assertOk()->assertDownload();
        $this->put(route('fit-proper.participants.update', [$a, $p]), ['name' => 'Manual diedit', 'kpi_year_1' => 0, 'kpi_year_2' => null])->assertRedirect();
        $this->assertSame($identity, $p->fresh()->internal_identity);
        $this->assertNull($p->fresh()->kpi());
        $this->delete(route('fit-proper.participants.delete', [$a, $p]))->assertRedirect();
        $this->assertDatabaseMissing('fit_proper_participants', ['id' => $p->id]);
        $this->delete(route('fit-proper.destroy', $a))->assertRedirect();
    }

    public function test_employee_search_snapshot_and_duplicate_prevention_for_pic(): void
    {
        $a = $this->activity();
        $pic = User::factory()->create(['role' => 'Staff']);
        $this->service()->assignPics($a, [$pic->id], null);
        $id = DB::table('employees')->insertGetId(['name' => 'Pegawai Sumber', 'sap' => 'FP-TEST', 'personal_number' => '123456', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($pic)->get(route('fit-proper.employees', ['activity' => $a->id, 'q' => 'FP-TEST']))->assertOk()->assertJsonFragment(['id' => $id]);
        $payload = ['employee_id' => $id];
        $this->post(route('fit-proper.participants.store', $a), $payload)->assertRedirect();
        $p = $a->participants()->sole();
        $this->assertSame('Pegawai Sumber', $p->name);
        $this->post(route('fit-proper.participants.store', $a), $payload)->assertSessionHasErrors('employee_id');
        DB::table('employees')->where('id', $id)->update(['name' => 'Nama berubah']);
        $this->assertSame('Pegawai Sumber', $p->fresh()->name);
    }

    public function test_access_is_scoped_for_pic_examiner_and_unassigned_user(): void
    {
        $admin = $this->admin();
        $a = $this->activity($admin);
        $other = $this->activity($admin);
        $pic = User::factory()->create(['role' => 'Staff']);
        $examiner = User::factory()->create(['role' => 'Member']);
        $outsider = User::factory()->create(['role' => 'Staff']);
        $this->service()->assignPics($a, [$pic->id], $admin);
        $this->participant($a);
        $e = $this->service()->examiner($a, ['user_id' => $examiner->id]);
        $this->actingAs($outsider)->get(route('fit-proper.show', $a))->assertForbidden();
        $this->get(route('fit-proper'))->assertOk()->assertDontSee('Kegiatan uji');
        $this->actingAs($pic)->get(route('fit-proper.show', $a))->assertOk();
        $this->get(route('fit-proper.show', $other))->assertForbidden();
        $this->post(route('fit-proper.store'), [])->assertForbidden();
        $this->put(route('fit-proper.pics', $a), ['users' => [$pic->id]])->assertForbidden();
        $this->actingAs($examiner)->get(route('fit-proper.show', $a))->assertRedirect(route('fit-proper.assess', [$a, $e]));
        $this->get(route('fit-proper.assess', [$a, $e]))->assertOk();
        $this->get(route('fit-proper.print', $a))->assertForbidden();
        $this->post(route('fit-proper.participants.store', $a), ['name' => 'Tidak boleh'])->assertForbidden();
        $this->actingAs($pic)->get(route('fit-proper.assess', [$a, $e]))->assertForbidden();
        $this->post(route('fit-proper.transition', $a), ['status' => 'Selesai'])->assertForbidden();
    }

    public function test_tokens_invalid_expired_revoked_reissued_and_finalized(): void
    {
        $a = $this->activity();
        $p = $this->participant($a);
        $e = $this->service()->examiner($a, ['name' => 'Manual']);
        $token = $this->service()->token($a, $e, null);
        $this->assertSame(hash('sha256', $token), $e->fresh()->getRawOriginal('token_hash'));
        $this->get(route('fit-proper.link', $token))->assertNotFound();
        $this->service()->transition($a, 'Berjalan');
        $this->get(route('fit-proper.link', 'wrong'))->assertNotFound();
        $this->get(route('fit-proper.link', $token))->assertOk();
        $this->post(route('fit-proper.link.submit', $token), $this->scores($a) + ['intent' => 'draft'])->assertRedirect();
        $e->update(['expires_at' => now()->subMinute()]);
        $this->get(route('fit-proper.link', $token))->assertNotFound();
        $token = $this->service()->token($a, $e, null);
        $e->update(['revoked_at' => now()]);
        $this->get(route('fit-proper.link', $token))->assertNotFound();
        $new = $this->service()->token($a, $e, null);
        $this->get(route('fit-proper.link', $token))->assertNotFound();
        $other = $this->activity();
        $foreign = $this->participant($other);
        $this->get(route('fit-proper.link.cv', [$new, $foreign]))->assertNotFound();
        $this->post(route('fit-proper.link.submit', $new), $this->scores($a) + ['intent' => 'submit'])->assertRedirect();
        $this->service()->transition($a, 'Selesai');
        $this->get(route('fit-proper.link', $new))->assertNotFound();
        $this->post(route('fit-proper.link.submit', $new), $this->scores($a))->assertNotFound();
    }

    public function test_score_validation_partial_draft_zero_and_double_submit(): void
    {
        $a = $this->activity();
        $p = $this->participant($a);
        $e = $this->service()->examiner($a, ['name' => 'Penguji']);
        $this->service()->transition($a, 'Berjalan');
        $this->service()->saveScores($e, ['scores' => [$p->id => ['adaptability' => 0]]], false);
        $this->expectValidation(fn () => $this->service()->saveScores($e, ['scores' => [$p->id => ['adaptability' => 0]]], true));
        $this->assertNull($e->fresh()->submitted_at);
        foreach ([-1, 5.1, 'abc'] as $invalid) {
            $data = $this->scores($a);
            $data['scores'][$p->id]['conflict'] = $invalid;
            $this->expectValidation(fn () => $this->service()->saveScores($e, $data, false));
        }
        $data = $this->scores($a, 0);
        $data['scores'][99999] = [];
        $this->expectValidation(fn () => $this->service()->saveScores($e, $data, true));
        $this->service()->saveScores($e, $this->scores($a, 0), true);
        $this->assertNotNull($e->fresh()->submitted_at);
        $this->expectValidation(fn () => $this->service()->saveScores($e, $this->scores($a, 5), true));
        $recap = $this->service()->calculation->recap($a);
        $this->assertTrue($recap['zero_max']);
        $this->assertFalse($recap['complete']);
        $this->assertNull($recap['rows'][0]['total']);
        $this->expectValidation(fn () => $this->service()->transition($a, 'Selesai'));
    }

    public function test_formula_matches_manual_example_and_guidance_boundaries(): void
    {
        $calc = $this->service()->calculation;
        $values = ['adaptability' => 5, 'authenticity' => 4, 'contributing' => 3, 'learning' => 4, 'diversity' => 4, 'job_mastery' => 3, 'improvement' => 5, 'anti_bribery' => 3, 'anti_gratuity' => 4, 'conflict' => 5];
        $result = $calc->examinerAverage($values);
        $this->assertSame(4.0, $result['core']);
        $this->assertSame(4, $result['eligibility']);
        $this->assertSame(4.0, $result['average']);
        $this->assertSame('Tidak Disarankan', $calc->examinerAverage(array_fill_keys(array_keys($values), 2.69))['guidance']);
        $this->assertSame('Disarankan Dengan Pertimbangan', $calc->examinerAverage(array_fill_keys(array_keys($values), 2.7))['guidance']);
        $this->assertSame('Disarankan Dengan Pertimbangan', $calc->examinerAverage(array_fill_keys(array_keys($values), 3))['guidance']);
        $this->assertSame('Disarankan', $calc->examinerAverage(array_fill_keys(array_keys($values), 3.01))['guidance']);
        unset($values['adaptability']);
        $this->assertNull($calc->examinerAverage($values));
        $a = $this->activity();
        $p = $this->participant($a);
        $low = $this->participant($a, 'Nilai rendah', 2, 1);
        $e = $this->service()->examiner($a, ['name' => 'A']);
        $e2 = $this->service()->examiner($a, ['name' => 'B']);
        $this->service()->transition($a, 'Berjalan');
        $data = $this->scores($a, 4);
        $data['scores'][$low->id] = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 2);
        $this->service()->saveScores($e, $data, true);
        $temporary = $calc->recap($a);
        $this->assertFalse($temporary['complete']);
        $this->assertNull($temporary['rows'][0]['raw']);
        $this->assertNull($temporary['rows'][0]['rank_total']);
        $this->service()->saveScores($e2, $data, true);
        $recap = $calc->recap($a);
        $this->assertTrue($recap['complete']);
        $this->assertEqualsWithDelta(88, $recap['rows'][0]['total'], 0.000001);
        $this->assertEqualsWithDelta(0.5, $recap['rows'][1]['fp'], 0.000001);
        $this->assertEqualsWithDelta(39, $recap['rows'][1]['total'], 0.000001);
        $this->assertTrue($recap['rows'][0]['passed']);
        $this->assertFalse($recap['rows'][1]['passed']);
    }

    public function test_ties_use_competition_ranking_and_passing_grade_inclusive(): void
    {
        $a = $this->activity();
        $this->participant($a, 'A', 2.5, 2);
        $this->participant($a, 'B', 2.5, 2);
        $low = $this->participant($a, 'C', 1, 1);
        $e = $this->service()->examiner($a, ['name' => 'A']);
        $this->service()->transition($a, 'Berjalan');
        $data = $this->scores($a, 4);
        $data['scores'][$low->id] = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 2);
        $this->service()->saveScores($e, $data, true);
        $rows = $this->service()->calculation->recap($a)['rows'];
        $this->assertSame([1, 1, 3], array_column($rows, 'rank_total'));
        $this->assertSame([1, 1, 3], array_column($rows, 'rank_fp'));
        $this->assertEquals(75, $rows[0]['total']);
        $this->assertTrue($rows[0]['passed']);
    }

    public function test_standards_snapshot_validation_and_new_activities_only(): void
    {
        $a = $this->activity();
        $standard = FitProperSetting::DEFAULT_STANDARD;
        $standard['passing_grade'] = 90;
        $this->actingAs($this->admin())->put(route('fit-proper.settings'), $standard)->assertRedirect();
        $this->assertSame(75, $a->fresh()->standard['passing_grade']);
        $this->assertSame(90, $this->activity()->standard['passing_grade']);
        foreach (['weight_kpi' => -0.1, 'weight_fp' => 0.7, 'max_kpi' => 0, 'passing_grade' => 101] as $key => $value) {
            $invalid = $standard;
            $invalid[$key] = $value;
            $this->put(route('fit-proper.settings'), $invalid)->assertSessionHasErrors();
        }
    }

    public function test_freeze_return_to_draft_removes_submission_and_deleted_scores(): void
    {
        $a = $this->activity();
        $p = $this->participant($a);
        $e = $this->service()->examiner($a, ['name' => 'A']);
        $token = $this->service()->token($a, $e, null);
        $this->service()->transition($a, 'Berjalan');
        $this->expectValidation(fn () => $this->participant($a, 'Illegal'));
        $this->expectValidation(fn () => $this->service()->examiner($a, ['name' => 'Illegal']));
        $this->expectValidation(fn () => $this->service()->assignPics($a, [], null));
        $this->service()->saveScores($e, $this->scores($a), true);
        $this->service()->transition($a, 'Draft');
        $this->assertNull($e->fresh()->submitted_at);
        $this->assertNull($e->fresh()->getRawOriginal('token_hash'));
        $this->actingAs($this->admin())->delete(route('fit-proper.examiners.delete', [$a, $e]))->assertRedirect();
        $this->assertSame(0, FitProperScore::count());
    }

    public function test_finalization_is_atomic_snapshots_all_results_and_locks_every_mutation(): void
    {
        $admin = $this->admin();
        $a = $this->activity($admin);
        $p = $this->participant($a);
        $e = $this->service()->examiner($a, ['name' => 'A']);
        $this->expectValidation(fn () => $this->service()->transition($a, 'Selesai'));
        $this->service()->transition($a, 'Berjalan');
        $this->expectValidation(fn () => $this->service()->transition($a, 'Selesai'));
        $this->assertSame('Berjalan', $a->fresh()->status);
        $this->assertNull($a->fresh()->final_snapshot);
        $this->service()->saveScores($e, $this->scores($a), true);
        $this->service()->transition($a, 'Selesai', $admin);
        $a->refresh();
        $this->assertSame('Selesai', $a->status);
        $this->assertSame('A', $a->final_snapshot['rows'][0]['details'][0]['examiner_name']);
        $snapshot = $a->final_snapshot;
        $this->actingAs($admin);
        foreach ([
            ['PUT', route('fit-proper.update', $a), ['name' => 'Changed', 'target_position' => 'X', 'scheduled_at' => '2026-10-19']],
            ['PUT', route('fit-proper.participants.update', [$a, $p]), ['name' => 'Changed']],
            ['DELETE', route('fit-proper.participants.delete', [$a, $p]), []],
            ['POST', route('fit-proper.examiners', $a), ['name' => 'New']],
            ['DELETE', route('fit-proper.examiners.delete', [$a, $e]), []],
            ['PUT', route('fit-proper.pics', $a), ['users' => []]],
            ['POST', route('fit-proper.token', [$a, $e]), []],
            ['POST', route('fit-proper.transition', $a), ['status' => 'Draft']],
            ['DELETE', route('fit-proper.destroy', $a), []],
        ] as [$method,$url,$payload]) {
            $this->call($method, $url, $payload)->assertSessionHasErrors();
        }
        $this->assertSame($snapshot, $a->fresh()->final_snapshot);
        $this->get(route('fit-proper.print', $a))->assertOk()->assertSee('Hasil Final');
        $this->get(route('fit-proper.excel', $a))->assertOk();
    }

    public function test_full_http_journey_from_creation_to_guest_scoring_and_final_report(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('fit-proper.store'), [
            'name' => 'Alur HTTP lengkap', 'target_position' => 'Kepala Unit',
            'scheduled_at' => '2026-10-19',
        ])->assertRedirect();
        $activity = FitProperActivity::sole();
        $this->put(route('fit-proper.pics', $activity), ['users' => [$admin->id]])->assertRedirect();
        foreach (['Fiktif Satu', 'Fiktif Dua'] as $name) {
            $this->post(route('fit-proper.participants.store', $activity), [
                'name' => $name, 'kpi_year_1' => 4, 'kpi_year_2' => 4, 'assessment' => 3,
            ])->assertRedirect();
        }
        $links = [];
        for ($index = 1; $index <= 3; $index++) {
            $this->post(route('fit-proper.examiners', $activity), ['name' => 'Penguji manual '.$index])->assertRedirect();
            $examiner = $activity->examiners()->latest('id')->first();
            $response = $this->post(route('fit-proper.token', [$activity, $examiner]));
            $response->assertRedirect()->assertSessionHas('examiner_link');
            $links[] = session('examiner_link');
        }
        $this->post(route('fit-proper.transition', $activity), ['status' => 'Berjalan'])->assertRedirect();
        $this->post(route('logout'));
        foreach ($links as $link) {
            $this->get($link)->assertOk()->assertSee('Fiktif Satu')->assertSee('Fiktif Dua');
            $this->post($link, $this->scores($activity, 4) + ['intent' => 'submit'])->assertRedirect();
        }
        $this->actingAs($admin)->post(route('fit-proper.transition', $activity), ['status' => 'Selesai'])->assertRedirect();
        $activity->refresh();
        $this->assertSame('Selesai', $activity->status);
        $this->assertTrue($activity->final_snapshot['complete']);
        $this->assertEquals(88, $activity->final_snapshot['rows'][0]['total']);
        $this->get(route('fit-proper.show', $activity))->assertOk()->assertSee('Hasil Akhir');
        $this->get(route('fit-proper.print', $activity))->assertOk()->assertSee('Hasil Final');
        $response = $this->get(route('fit-proper.excel', $activity));
        $response->assertOk()->assertDownload();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'fit-proper-test-');
        try {
            file_put_contents($temporaryPath, $response->streamedContent());
            $workbook = IOFactory::load($temporaryPath);
            $this->assertSame('Alur HTTP lengkap', $workbook->getActiveSheet()->getCell('A1')->getValue());
            $this->assertEquals(88, $workbook->getActiveSheet()->getCell('J5')->getValue());
            $this->assertSame(7, $workbook->getSheetByName('Rincian penguji')->getHighestRow());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($temporaryPath);
        }
        foreach ($links as $link) {
            $this->get($link)->assertNotFound();
        }
    }

    public function test_failure_during_finalization_rolls_back_status_snapshot_tokens_and_history(): void
    {
        $a = $this->activity();
        $this->participant($a);
        $e = $this->service()->examiner($a, ['name' => 'Penguji']);
        $token = $this->service()->token($a, $e, null);
        $this->service()->transition($a, 'Berjalan');
        $this->service()->saveScores($e, $this->scores($a), true);
        $historyCount = $a->histories()->count();
        $failingService = \Mockery::mock(FitProperService::class, [$this->service()->calculation])->makePartial();
        $failingService->shouldReceive('history')->once()->andThrow(new \RuntimeException('Simulasi kegagalan penyimpanan riwayat'));
        try {
            $failingService->transition($a, 'Selesai');
            $this->fail('Finalization should roll back');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi kegagalan penyimpanan riwayat', $exception->getMessage());
        }
        $this->assertSame('Berjalan', $a->fresh()->status);
        $this->assertNull($a->fresh()->final_snapshot);
        $this->assertNull($a->fresh()->finalized_at);
        $this->assertSame(hash('sha256', $token), $e->fresh()->getRawOriginal('token_hash'));
        $this->assertNull($e->fresh()->revoked_at);
        $this->assertSame($historyCount, $a->histories()->count());
    }

    public function test_cv_removal_and_cross_activity_cv_access(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $a = $this->activity($admin);
        $this->actingAs($admin)->post(route('fit-proper.participants.store', $a), [
            'name' => 'Peserta CV',
            'cv' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $p = $a->participants()->sole();
        $other = $this->activity($admin);
        $this->get(route('fit-proper.cv', [$other, $p]))->assertNotFound();
        $this->put(route('fit-proper.participants.update', [$a, $p]), ['name' => $p->name, 'remove_cv' => true])->assertRedirect();
        $this->assertNull($p->fresh()->cv_path);
        $this->get(route('fit-proper.cv', [$a, $p]))->assertNotFound();
    }

    public function test_demo_is_idempotent_and_excluded_from_real_dashboard_statistics(): void
    {
        $admin = $this->admin();
        $employees = Employee::count();
        $users = User::count();
        $this->artisan('fit-proper:demo', ['--admin' => $admin->email])->assertSuccessful();
        $this->artisan('fit-proper:demo', ['--admin' => $admin->email])->assertSuccessful();
        $this->assertSame(3, FitProperActivity::count());
        $this->assertSame(15, FitProperParticipant::count());
        $this->assertSame(15, FitProperParticipant::whereNotNull('fit_proper_activity_id')->whereHas('activity')->count());
        $this->assertSame(9, FitProperExaminer::count());
        $this->assertSame($employees, Employee::count());
        $this->assertSame($users, User::count());
        $a = FitProperActivity::where('status', 'Selesai')->sole();
        $this->assertTrue($a->final_snapshot['complete']);
        $this->assertContains(true, array_column($a->final_snapshot['rows'], 'passed'));
        $this->assertContains(false, array_column($a->final_snapshot['rows'], 'passed'));
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === 0);
        $real = $this->activity($admin);
        $this->participant($real);
        $this->service()->examiner($real, ['name' => 'A']);
        $this->service()->transition($real, 'Berjalan');
        $this->get(route('dashboard'))->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === 1);
        $this->get(route('fit-proper.print', $a))->assertSee('Data Simulasi');
        $this->get(route('fit-proper', ['simulation' => '0']))->assertDontSee('SIMULASI FIKTIF');
    }
}
