<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FitProperActivity;
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
        $connection = config('database.default');
        $database = DB::connection()->getDatabaseName();

        if ($connection === 'sqlite') {
            $this->assertSame(':memory:', $database);
        } else {
            $this->assertSame('mysql', $connection);
            $this->assertStringEndsWith('_testing', $database);
        }

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
        $this->assertFalse($a->is_simulation);
        $employeeCount = Employee::count();
        $this->get(route('fit-proper.show', $a))->assertOk()
            ->assertSee('Belum ada peserta')
            ->assertSee('Tambahkan peserta untuk memulai proses Fit &amp; Proper.', false)
            ->assertSee('Tambah Peserta');
        $this->post(route('fit-proper.participants.store', $a), ['name' => 'Manual Fiktif', 'birth_date' => '1990-01-01', 'current_jg' => 12, 'target_jg' => 10, 'kpi_year_1' => 0, 'kpi_year_2' => 0, 'assessment' => 0, 'cv' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])->assertRedirect();
        $p = $a->participants()->sole();
        $cvPath = $p->cv_path;
        Storage::disk('local')->assertExists($cvPath);
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
        $this->get(route('fit-proper.show', $a))->assertOk()
            ->assertSee('Manual Fiktif')
            ->assertSee('Cari peserta...')
            ->assertSee('data-fp-participant-list', false)
            ->assertSee('data-fp-participant-card', false)
            ->assertSee('Profil Lengkap')
            ->assertSee('Hasil Asesmen')
            ->assertSee('Lihat CV')
            ->assertSee('aria-label="Edit peserta Manual Fiktif"', false)
            ->assertSee('aria-label="Hapus peserta Manual Fiktif"', false)
            ->assertSee('data-fp-participant-edit-trigger', false)
            ->assertSee('data-fp-participant-edit-modal', false)
            ->assertSee('fit-proper-participant-edit-'.$p->id.'-title', false)
            ->assertSee('value="participant-edit-'.$p->id.'"', false)
            ->assertSee('data-fp-participant-form="create"', false)
            ->assertSee('data-fp-participant-form="edit"', false)
            ->assertSee('action="'.route('fit-proper.participants.store', $a).'"', false)
            ->assertSee('action="'.route('fit-proper.participants.update', [$a, $p]).'"', false)
            ->assertSee('fit-proper-participant-create-name', false)
            ->assertSee('fit-proper-participant-edit-'.$p->id.'-name', false)
            ->assertSee('Identitas &amp; Posisi', false)
            ->assertSee('Profil Pendukung')
            ->assertSee('Simpan Perubahan')
            ->assertSee('CV aktif tersedia')
            ->assertDontSee('href="'.route('fit-proper.participants.edit', [$a, $p]).'"', false)
            ->assertSee('data-fp-participant-modal', false)
            ->assertSee('fit-proper-participant-create-title', false)
            ->assertSee('form_context', false)
            ->assertSee('Cari SAP')
            ->assertDontSee('Nama (wajib untuk manual)')
            ->assertDontSee('SAP / personal number (opsional)')
            ->assertDontSee('href="'.route('fit-proper.participants.create', $a).'"', false);
        $this->get(route('fit-proper.participants.edit', [$a, $p]))->assertOk();
        $this->get(route('fit-proper.cv', [$a, $p]))->assertOk();
        $this->get(route('fit-proper.print', $a))->assertOk()->assertSee('Sementara');
        $this->get(route('fit-proper.excel', $a))->assertOk()->assertDownload();
        $this->put(route('fit-proper.participants.update', [$a, $p]), ['name' => 'Manual diedit', 'kpi_year_1' => 0, 'kpi_year_2' => null])->assertRedirect();
        $this->assertSame($identity, $p->fresh()->internal_identity);
        $this->assertNull($p->fresh()->kpi());
        $this->delete(route('fit-proper.participants.delete', [$a, $p]))->assertRedirect();
        $this->assertDatabaseMissing('fit_proper_participants', ['id' => $p->id]);
        Storage::disk('local')->assertMissing($cvPath);
        $this->delete(route('fit-proper.destroy', $a))->assertRedirect();
    }

    public function test_participant_create_validation_reopens_the_modal(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);

        $this->actingAs($admin)
            ->from(route('fit-proper.show', $activity))
            ->followingRedirects()
            ->post(route('fit-proper.participants.store', $activity), [
                'form_context' => 'participant-create',
            ])
            ->assertOk()
            ->assertSee('data-initially-open="true"', false)
            ->assertSee('Peserta belum dapat disimpan.');
    }

    public function test_participant_edit_validation_reopens_the_correct_modal(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $participant = $this->participant($activity);

        $this->actingAs($admin)
            ->from(route('fit-proper.show', $activity))
            ->followingRedirects()
            ->put(route('fit-proper.participants.update', [$activity, $participant]), [
                'form_context' => 'participant-edit-'.$participant->id,
                'name' => '',
            ])
            ->assertOk()
            ->assertSee('data-participant-id="'.$participant->id.'"', false)
            ->assertSee('data-initially-open="true"', false)
            ->assertSee('Peserta belum dapat disimpan.');
    }

    public function test_employee_search_snapshot_and_duplicate_prevention_for_pic(): void
    {
        $a = $this->activity();
        $pic = User::factory()->create(['role' => 'Staff']);
        $this->service()->assignPics($a, [$pic->id], null);
        $id = DB::table('employees')->insertGetId([
            'name' => 'Nama Lama',
            'sap' => 'FP-TEST',
            'personal_number' => 'Pegawai Sumber',
            'birth_date' => '1990-01-02',
            'txt_dept' => 'Human Capital',
            'position' => 'Analis HC',
            'education' => 'S1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($pic)->get(route('fit-proper.employees', ['activity' => $a->id, 'q' => 'FP-TEST']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $id,
                'sap' => 'FP-TEST',
                'name' => 'Pegawai Sumber',
                'birth_date' => '1990-01-02',
                'department' => 'Human Capital',
                'current_position' => 'Analis HC',
                'education' => 'S1',
            ]);
        $payload = ['employee_id' => $id];
        $this->post(route('fit-proper.participants.store', $a), $payload)->assertRedirect();
        $p = $a->participants()->sole();
        $this->assertSame('Pegawai Sumber', $p->name);
        $this->assertSame('FP-TEST', $p->sap);
        $this->assertSame('1990-01-02', $p->birth_date?->toDateString());
        $this->assertSame('Human Capital', $p->profile['department']);
        $this->assertSame('Analis HC', $p->profile['current_position']);
        $this->assertSame('S1', $p->profile['education']);
        $this->post(route('fit-proper.participants.store', $a), $payload)->assertSessionHasErrors('employee_id');
        DB::table('employees')->where('id', $id)->update(['personal_number' => 'Nama berubah']);
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
        $this->put(route('fit-proper.pics', $a), ['users' => [$pic->id]])->assertSessionHasErrors('users');
        $this->actingAs($examiner)->get(route('fit-proper.show', $a))->assertRedirect(route('fit-proper.assess', [$a, $e]));
        $this->get(route('fit-proper.assess', [$a, $e]))->assertOk();
        $this->get(route('fit-proper.print', $a))->assertForbidden();
        $this->post(route('fit-proper.participants.store', $a), ['name' => 'Tidak boleh'])->assertForbidden();
        $this->actingAs($pic)->get(route('fit-proper.assess', [$a, $e]))->assertForbidden();
        $this->post(route('fit-proper.finalize', $a))->assertForbidden();
    }

    public function test_pic_panel_assignment_is_additive_duplicate_safe_and_detaches_only_one_user(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $firstCandidate = User::factory()->create([
            'name' => 'Kandidat PIC Pertama Dengan Nama Panjang',
            'email' => 'kandidat.pic.pertama.dengan.email.panjang@example.com',
        ]);
        $secondCandidate = User::factory()->create([
            'name' => 'Kandidat PIC Kedua',
            'email' => 'kandidat.pic.kedua@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('fit-proper.show', $activity))
            ->assertOk()
            ->assertSee('data-fp-fragment="pic-panel"', false)
            ->assertSee('PIC Aktif')
            ->assertSee('Tambahkan PIC')
            ->assertSee('Bersihkan pilihan')
            ->assertSee('Belum ada pengguna dipilih')
            ->assertSee('Sudah menjadi PIC')
            ->assertSee('aria-label="Lepas '.$admin->name.' dari PIC kegiatan"', false)
            ->assertSee('action="'.route('fit-proper.pics', $activity).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('id="fp-pic-user-'.$activity->id.'-'.$firstCandidate->id.'"', false);

        $this->put(route('fit-proper.pics', $activity), [
            'users' => [$firstCandidate->id, $secondCandidate->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$admin->id, $firstCandidate->id, $secondCandidate->id],
            $activity->fresh()->pics()->pluck('users.id')->all(),
        );

        $this->put(route('fit-proper.pics', $activity), ['users' => [$firstCandidate->id]])
            ->assertSessionHasErrors('users');
        $this->put(route('fit-proper.pics', $activity), ['users' => [PHP_INT_MAX]])
            ->assertSessionHasErrors('users.0');

        $this->delete(route('fit-proper.pics.delete', [$activity, $firstCandidate]))
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$admin->id, $secondCandidate->id],
            $activity->fresh()->pics()->pluck('users.id')->all(),
        );
        $this->assertDatabaseHas('users', ['id' => $firstCandidate->id]);
    }

    public function test_pic_panel_has_safe_empty_state_without_hiding_assignment_panel(): void
    {
        $admin = $this->admin();
        $activity = $this->service()->create([
            'name' => 'Kegiatan tanpa PIC',
            'target_position' => 'Kepala Unit',
            'scheduled_at' => '2026-10-19',
        ], $admin);

        $this->actingAs($admin)
            ->get(route('fit-proper.show', $activity))
            ->assertOk()
            ->assertSee('0 PIC')
            ->assertSee('Belum ada PIC')
            ->assertSee('Pilih pengguna pada panel Tambahkan PIC.')
            ->assertSee('Tambahkan PIC')
            ->assertSee('data-fp-pic-empty', false);
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

    public function test_examiner_workspace_renders_safe_empty_state_and_internal_manual_controls(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $internalUser = User::factory()->create([
            'name' => 'Penguji Internal Dengan Nama Sangat Panjang',
            'email' => 'penguji.internal.dengan.email.panjang@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('fit-proper.show', $activity))
            ->assertOk()
            ->assertSee('data-fp-fragment="examiner-panel"', false)
            ->assertSee('Status kegiatan: Draft')
            ->assertSee('0 Penguji')
            ->assertSee('Belum ada penguji')
            ->assertSee('Tambahkan penguji agar proses penilaian dapat disiapkan.')
            ->assertSee('Tambah Penguji')
            ->assertSee('Masukkan nama penguji')
            ->assertSee('action="'.route('fit-proper.examiners', $activity).'"', false);

        $this->post(route('fit-proper.examiners', $activity), ['user_id' => $internalUser->id])
            ->assertRedirect();
        $this->post(route('fit-proper.examiners', $activity), ['name' => 'Penguji Manual'])
            ->assertRedirect();
        $this->post(route('fit-proper.examiners', $activity), ['user_id' => $internalUser->id])
            ->assertSessionHasErrors('user_id');

        $internalExaminer = $activity->fresh()->examiners()->where('user_id', $internalUser->id)->sole();

        $this->get(route('fit-proper.show', $activity))
            ->assertOk()
            ->assertSee('2 Penguji')
            ->assertSee('Penguji Internal Dengan Nama Sangat Panjang')
            ->assertSee('Penguji Manual')
            ->assertSee('Internal')
            ->assertSee('Manual')
            ->assertSee('Sudah menjadi penguji')
            ->assertSee('Terbitkan ulang')
            ->assertSee('Cabut tautan')
            ->assertSee('aria-label="Hapus penguji '.$internalExaminer->name.'"', false)
            ->assertSee('Tautan telah diterbitkan. URL lengkap hanya ditampilkan saat penerbitan dan tidak disimpan sebagai teks biasa.');
    }

    public function test_recap_matrix_handles_empty_and_participant_count_scroll_states(): void
    {
        $admin = $this->admin();
        $activityWithoutExaminer = $this->activity($admin);

        $this->actingAs($admin)
            ->get(route('fit-proper.show', $activityWithoutExaminer))
            ->assertOk()
            ->assertSee('Belum ada penguji untuk dibandingkan.')
            ->assertSee('Peringkat belum tersedia');

        $activityWithoutParticipant = $this->activity($admin);
        $this->service()->examiner($activityWithoutParticipant, ['name' => 'Penguji Tanpa Peserta']);

        $this->get(route('fit-proper.show', $activityWithoutParticipant))
            ->assertOk()
            ->assertSee('Belum ada peserta pada kegiatan ini.');

        foreach ([1, 2, 3, 4, 10] as $participantCount) {
            $activity = $this->activity($admin);
            $this->service()->examiner($activity, ['name' => 'Penguji '.$participantCount]);

            foreach (range(1, $participantCount) as $index) {
                $this->participant($activity, 'Peserta '.$participantCount.'-'.$index);
            }

            $response = $this->get(route('fit-proper.show', $activity))
                ->assertOk()
                ->assertSee('data-participant-count="'.$participantCount.'"', false)
                ->assertSee('data-scrollable="'.($participantCount > 3 ? 'true' : 'false').'"', false)
                ->assertSee('aria-label="Daftar nilai peserta dapat digeser horizontal."', false);

            $this->assertSame(1, substr_count($response->getContent(), 'class="fp-matrix-viewport"'));

            if ($participantCount > 3) {
                $response->assertSee('Geser untuk melihat peserta lainnya');
            } else {
                $response->assertDontSee('Geser untuk melihat peserta lainnya');
            }
        }
    }

    public function test_recap_matrix_filters_scores_by_examiner_and_uses_official_results(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $firstParticipant = $this->participant($activity, 'Peserta Matriks Pertama');
        $secondParticipant = $this->participant($activity, 'Peserta Matriks Kedua');
        $submittedExaminer = $this->service()->examiner($activity, ['name' => 'Penguji Matriks Selesai']);
        $this->service()->examiner($activity, ['name' => 'Penguji Matriks Menunggu']);
        $this->service()->transition($activity, 'Berjalan');
        $scores = $this->scores($activity, 2.7);
        $scores['scores'][$firstParticipant->id]['recommendation'] = 'Pertimbangkan rotasi terarah.';
        $scores['scores'][$firstParticipant->id]['notes'] = 'Catatan aktual penguji.';
        $this->service()->saveScores($submittedExaminer, $scores, true);

        $response = $this->actingAs($admin)
            ->get(route('fit-proper.show', $activity))
            ->assertOk()
            ->assertSee('Rekap Penilaian per Penguji')
            ->assertSee('Bandingkan hasil penilaian seluruh peserta dari satu penguji.')
            ->assertSee('Penguji Matriks Selesai')
            ->assertSee('Penguji Matriks Menunggu')
            ->assertSee('2 dari 2 peserta dinilai')
            ->assertSee('0 dari 2 peserta dinilai')
            ->assertSee('2,7')
            ->assertSee('Disarankan Dengan Pertimbangan')
            ->assertSee('Pertimbangkan rotasi terarah.')
            ->assertSee('Catatan aktual penguji.')
            ->assertSee('Unduh Rekap')
            ->assertDontSee('fp-raw-table', false);

        $this->assertSame(2, substr_count($response->getContent(), 'class="fp-matrix-viewport"'));
        $this->assertSame(
            ['adaptability', 'authenticity', 'contributing', 'learning', 'diversity'],
            FitProperCalculationService::GROUPS['core']['indicators'],
        );
        $this->assertSame('Peserta Matriks Kedua', $secondParticipant->fresh()->name);
    }

    public function test_examiner_token_reissue_revoke_and_delete_keep_account_and_other_examiners_safe(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $participant = $this->participant($activity);
        $internalUser = User::factory()->create(['name' => 'Penguji Internal']);
        $internalExaminer = $this->service()->examiner($activity, ['user_id' => $internalUser->id], $admin);
        $manualExaminer = $this->service()->examiner($activity, ['name' => 'Penguji Manual'], $admin);

        $this->actingAs($admin);
        $this->post(route('fit-proper.token', [$activity, $internalExaminer]))
            ->assertRedirect()
            ->assertSessionHas('examiner_link');
        $firstLink = session('examiner_link');
        $firstHash = $internalExaminer->fresh()->getRawOriginal('token_hash');

        $this->post(route('fit-proper.token', [$activity, $internalExaminer]))
            ->assertRedirect()
            ->assertSessionHas('examiner_link');
        $secondLink = session('examiner_link');
        $this->assertNotSame($firstHash, $internalExaminer->fresh()->getRawOriginal('token_hash'));
        $this->assertNotSame($firstLink, $secondLink);

        $this->service()->transition($activity, 'Berjalan', $admin);
        $this->get($firstLink)->assertNotFound();
        $this->get($secondLink)->assertOk()->assertSee($participant->name);

        $this->post(route('fit-proper.token', [$activity, $internalExaminer]), ['revoke' => 1])
            ->assertRedirect();
        $this->assertNotNull($internalExaminer->fresh()->revoked_at);
        $this->assertDatabaseHas('fit_proper_examiners', ['id' => $internalExaminer->id]);
        $this->assertDatabaseHas('users', ['id' => $internalUser->id]);
        $this->get($secondLink)->assertNotFound();

        $this->service()->transition($activity, 'Draft', $admin);
        $this->delete(route('fit-proper.examiners.delete', [$activity, $internalExaminer]))
            ->assertRedirect();
        $this->assertDatabaseMissing('fit_proper_examiners', ['id' => $internalExaminer->id]);
        $this->assertDatabaseHas('fit_proper_examiners', ['id' => $manualExaminer->id]);
        $this->assertDatabaseHas('users', ['id' => $internalUser->id]);
    }

    public function test_score_validation_partial_draft_zero_and_double_submit(): void
    {
        $a = $this->activity();
        $p = $this->participant($a);
        $e = $this->service()->examiner($a, ['name' => 'Penguji']);
        $this->service()->transition($a, 'Berjalan');
        $this->service()->saveScores($e, ['scores' => [$p->id => ['adaptability' => 1]]], false);
        $this->expectValidation(fn () => $this->service()->saveScores($e, ['scores' => [$p->id => ['adaptability' => 1]]], true));
        $this->assertNull($e->fresh()->submitted_at);
        foreach ([0, -1, 5.1, 'abc'] as $invalid) {
            $data = $this->scores($a);
            $data['scores'][$p->id]['conflict'] = $invalid;
            $this->expectValidation(fn () => $this->service()->saveScores($e, $data, false));
        }
        $data = $this->scores($a, 1);
        $data['scores'][99999] = [];
        $this->expectValidation(fn () => $this->service()->saveScores($e, $data, true));
        $this->service()->saveScores($e, $this->scores($a, 1), true);
        $this->assertNotNull($e->fresh()->submitted_at);
        $this->expectValidation(fn () => $this->service()->saveScores($e, $this->scores($a, 5), true));
        $recap = $this->service()->calculation->recap($a);
        $this->assertFalse($recap['zero_max']);
        $this->assertTrue($recap['complete']);
        $this->assertSame(1.0, $recap['rows'][0]['raw']);
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
        $this->assertSame(4.0, collect($temporary['rows'])->keyBy('id')[$p->id]['raw']);
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
        $this->actingAs(User::query()->findOrFail($a->created_by))
            ->delete(route('fit-proper.examiners.delete', [$a, $e]))
            ->assertRedirect();
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
        $this->actingAs($admin)->post(route('fit-proper.finalize', $activity))->assertRedirect();
        $activity->refresh();
        $this->assertSame('Selesai', $activity->status);
        $this->assertTrue($activity->final_snapshot['complete']);
        $this->assertEquals(88, $activity->final_snapshot['rows'][0]['total']);
        $this->get(route('fit-proper.show', $activity))->assertOk()
            ->assertSee('Hasil Akhir Fit &amp; Proper', false)
            ->assertSee('fp-result-ranking-card', false)
            ->assertSee('Rincian Perhitungan')
            ->assertSee('Final');
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
        $cvPath = $p->cv_path;
        Storage::disk('local')->assertExists($cvPath);
        $other = $this->activity($admin);
        $this->get(route('fit-proper.cv', [$other, $p]))->assertNotFound();
        $this->put(route('fit-proper.participants.update', [$a, $p]), ['name' => $p->name, 'remove_cv' => true])->assertRedirect();
        $this->assertNull($p->fresh()->cv_path);
        Storage::disk('local')->assertMissing($cvPath);
        $this->get(route('fit-proper.cv', [$a, $p]))->assertNotFound();
    }

    public function test_fit_proper_dashboard_uses_operational_database_counts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('fitProperSummary', fn (array $summary): bool => $summary['activeParticipants'] === 0);
        $activity = $this->activity($admin);
        $this->participant($activity);
        $this->service()->examiner($activity, ['name' => 'A']);
        $this->service()->transition($activity, 'Berjalan');
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('fitProperSummary', fn (array $summary): bool => $summary['activeParticipants'] === 1)
            ->assertSee('data-dashboard-metric="fit-proper"', false);
        $this->get(route('fit-proper'))->assertOk()->assertDontSee('Data Simulasi');
    }

    public function test_fit_proper_workspace_uses_accessible_activity_participant_and_examiner_data(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $this->participant($activity, 'Kandidat Pertama');
        $this->participant($activity, 'Kandidat Kedua');
        $submittedExaminer = $this->service()->examiner($activity, ['name' => 'Penguji Selesai']);
        $this->service()->examiner($activity, ['name' => 'Penguji Menunggu']);
        $this->service()->transition($activity, 'Berjalan');
        $this->service()->saveScores($submittedExaminer, $this->scores($activity), true);

        $this->actingAs($admin)
            ->get(route('fit-proper'))
            ->assertOk()
            ->assertViewHas('activeActivityCount', 1)
            ->assertViewHas('participantCount', 2)
            ->assertViewHas('examinerCount', 2)
            ->assertViewHas('submittedExaminerCount', 1)
            ->assertDontSee('Pantau proses asesmen kandidat secara menyeluruh.')
            ->assertSee('Kegiatan Berlangsung')
            ->assertSee('Ringkasan Kegiatan')
            ->assertSee('Kandidat Pertama')
            ->assertSee('Progres Tahapan')
            ->assertSee('fit-proper-create-title', false)
            ->assertSee('data-fp-root data-app-content', false)
            ->assertSee('Lihat Standar Penilaian')
            ->assertSee('Pengaturan Standar Penilaian')
            ->assertSee('fit-proper-settings-title', false)
            ->assertSee('data-app-content data-fp-settings-modal', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('Kelola Penilaian')
            ->assertSee('aria-selected="true"', false);

        $this->get(route('fit-proper.show', $activity))
            ->assertOk()
            ->assertSee('Progres Penilaian')
            ->assertSee('aria-valuenow="50"', false)
            ->assertDontSee('href="#progres"', false);
    }

    public function test_fit_proper_workspace_has_safe_empty_state_and_hides_create_action_without_permission(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('fit-proper'))
            ->assertOk()
            ->assertSee('Belum ada kegiatan Fit &amp; Proper', false)
            ->assertSee('Tambah Kegiatan');

        $activity = $this->activity($admin);
        $pic = User::factory()->create(['role' => 'Staff', 'must_change_password' => false]);
        $this->service()->assignPics($activity, [$pic->id], $admin);

        $this->actingAs($pic)
            ->get(route('fit-proper'))
            ->assertOk()
            ->assertSee($activity->name)
            ->assertDontSee('Tambah Kegiatan');
    }

    public function test_bulk_drafts_skip_empty_rows_preserve_omitted_participants_and_ignore_browser_results(): void
    {
        $a = $this->activity();
        $first = $this->participant($a, 'Pertama');
        $second = $this->participant($a, 'Kedua');
        $examiner = $this->service()->examiner($a, ['name' => 'Penguji']);
        $this->service()->transition($a, 'Berjalan');
        $this->service()->saveScores($examiner, ['scores' => [$first->id => ['adaptability' => 2], $second->id => ['adaptability' => null]]], false);
        $this->assertSame(1, FitProperScore::count());
        $this->assertNull($this->service()->calculation->recap($a)['rows'][0]['raw']);
        $data = $this->scores($a, 4);
        $data['scores'][$first->id]['core'] = 999;
        $data['scores'][$first->id]['average'] = 999;
        $this->service()->saveScores($examiner, $data, false);
        $this->assertSame(2, FitProperScore::count());
        $this->assertSame(4.0, $this->service()->calculation->recap($a)['rows'][0]['raw']);
        $this->service()->saveScores($examiner, ['scores' => [$second->id => ['adaptability' => 3]]], false);
        $this->assertSame(4, FitProperScore::where('participant_id', $first->id)->sole()->indicators['adaptability']);
        $this->assertSame(2, FitProperScore::count());
    }

    public function test_recap_averages_flexible_complete_examiners_and_excludes_incomplete_rows(): void
    {
        $a = $this->activity();
        $first = $this->participant($a, 'Pertama');
        $second = $this->participant($a, 'Kedua');
        $examiners = [];
        foreach (range(1, 4) as $index) {
            $examiners[] = $this->service()->examiner($a, ['name' => 'Penguji '.$index]);
        }
        $this->service()->transition($a, 'Berjalan');
        foreach ([2, 4, 3] as $index => $value) {
            $data = $this->scores($a, $value);
            $data['scores'][$second->id] = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 1);
            $this->service()->saveScores($examiners[$index], $data, false);
        }
        $this->service()->saveScores($examiners[3], ['scores' => [$first->id => ['adaptability' => 5]]], false);
        $recap = $this->service()->calculation->recap($a);
        $rows = collect($recap['rows'])->keyBy('id');
        $this->assertSame(3.0, $rows[$first->id]['raw']);
        $this->assertSame(0, $rows[$first->id]['submitted_count']);
        $this->assertSame(1.0, $rows[$first->id]['fp']);
        $this->assertEqualsWithDelta(1 / 3, $rows[$second->id]['fp'], 0.00000001);
        $this->assertFalse($recap['complete']);
    }

    public function test_second_participant_write_failure_rolls_back_first_write(): void
    {
        $a = $this->activity();
        $this->participant($a, 'Pertama');
        $second = $this->participant($a, 'Kedua');
        $examiner = $this->service()->examiner($a, ['name' => 'Penguji']);
        $this->service()->transition($a, 'Berjalan');
        FitProperScore::creating(function (FitProperScore $score) use ($second): void {
            if ($score->participant_id === $second->id) {
                throw new \RuntimeException('Gagal peserta kedua');
            }
        });
        try {
            $this->service()->saveScores($examiner, $this->scores($a), true);
            $this->fail('Expected write failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Gagal peserta kedua', $exception->getMessage());
        } finally {
            FitProperScore::flushEventListeners();
        }
        $this->assertSame(0, FitProperScore::count());
        $this->assertNull($examiner->fresh()->submitted_at);
    }

    public function test_ui_formats_one_decimal_without_mutating_raw_values_or_results(): void
    {
        $admin = $this->admin();
        $activity = $this->activity($admin);
        $participant = $this->participant($activity, 'Peserta Presisi', 4.25, 3.67);
        $participant->update(['sap' => '00123456']);
        $other = $this->participant($activity, 'Peserta Pembanding');
        $examiner = $this->service()->examiner($activity, ['name' => 'Penguji Presisi']);
        $this->service()->transition($activity, 'Berjalan');
        $data = $this->scores($activity, 4.25);
        $data['scores'][$other->id] = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 3.7778);
        $this->service()->saveScores($examiner, $data, true);
        $before = $this->service()->calculation->recap($activity);
        $rawBefore = FitProperScore::orderBy('id')->get()->toArray();
        $this->actingAs($admin)->get(route('fit-proper.show', $activity))->assertOk()
            ->assertSee('4,3')->assertSee('3,7')->assertSee('0,9')->assertSee('75,0')->assertSee('00123456')
            ->assertSee('fp-result-dashboard', false)
            ->assertSee('Hasil Akhir Fit &amp; Proper', false)
            ->assertSee('Lihat metode perhitungan')
            ->assertSee('Peserta Dinilai')
            ->assertSee('Penguji Selesai')
            ->assertSee('Nilai Tertinggi')
            ->assertSee('Peserta Lulus')
            ->assertSee('fp-result-ranking-card', false)
            ->assertSee('Rincian Perhitungan')
            ->assertSee('Sementara')
            ->assertSee('Hasil ini merupakan referensi.');
        $this->get(route('fit-proper.print', $activity))->assertOk()->assertSee('4,3')->assertSee('10,0%');
        $this->get(route('fit-proper.participants.edit', [$activity, $participant]))->assertForbidden();
        $this->assertSame($before, $this->service()->calculation->recap($activity));
        $this->assertSame($rawBefore, FitProperScore::orderBy('id')->get()->toArray());
        $this->assertSame(4.25, $participant->fresh()->kpi());
        $this->service()->transition($activity, 'Draft');
        $this->get(route('fit-proper.participants.edit', [$activity, $participant]))->assertOk()->assertSee('value="4.25"', false);
    }
}
