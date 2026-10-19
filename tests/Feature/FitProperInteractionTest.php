<?php

namespace Tests\Feature;

use App\Models\FitProperExaminer;
use App\Models\User;
use App\Services\FitProperCalculationService;
use App\Services\FitProperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FitProperInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    }

    private function context(): array
    {
        $admin = User::factory()->create(['role' => 'Admin', 'must_change_password' => false]);
        $activity = app(FitProperService::class)->create(['name' => 'Interaksi kegiatan', 'target_position' => 'Kepala Unit', 'scheduled_at' => '2026-10-19'], $admin);
        $this->actingAs($admin);

        return [$admin, $activity];
    }

    public function test_async_activity_save_returns_state_and_field_validation_without_redirect(): void
    {
        [$admin,$activity] = $this->context();
        $this->postJson(route('fit-proper.store'), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['name', 'scheduled_at', 'target_position']);
        $this->postJson(route('fit-proper.store'), ['name' => 'Baru', 'target_position' => 'Kepala', 'scheduled_at' => '2026-10-19'])
            ->assertCreated()->assertJsonStructure(['message', 'redirect']);
        $response = $this->putJson(route('fit-proper.update', $activity), ['name' => 'Nama diperbarui', 'target_position' => 'Kepala', 'scheduled_at' => '2026-10-19']);
        $response->assertOk()->assertJsonStructure(['message', 'html'])->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('Nama diperbarui', $response->json('html'));
        $this->assertStringContainsString('data-fp-fragment="summary"', $response->json('html'));
        $this->putJson(route('fit-proper.update', $activity), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame('Nama diperbarui', $activity->fresh()->name);
        $this->get(route('fit-proper.show', $activity))->assertSee('data-fp-async', false)->assertSee('data-fp-user-search', false);
    }

    public function test_pics_are_additive_duplicate_safe_individually_removable_and_scoped(): void
    {
        [$admin,$activity] = $this->context();
        $first = User::factory()->create(['role' => 'Staff']);
        $second = User::factory()->create(['role' => 'Member']);
        $outsider = User::factory()->create(['role' => 'Staff']);
        $this->putJson(route('fit-proper.pics', $activity), ['users' => [$first->id]])->assertOk();
        $response = $this->putJson(route('fit-proper.pics', $activity), ['users' => [$second->id]]);
        $response->assertOk()->assertJsonStructure(['html', 'message']);
        $this->assertStringContainsString($first->name, $response->json('html'));
        $this->assertStringContainsString($second->name, $response->json('html'));
        $this->assertSame(2, $activity->pics()->count());
        $this->putJson(route('fit-proper.pics', $activity), ['users' => [$first->id]])->assertUnprocessable()->assertJsonValidationErrors('users');
        $this->putJson(route('fit-proper.pics', $activity), ['users' => [$outsider->id, $outsider->id]])->assertUnprocessable();
        $this->assertSame(2, $activity->pics()->count());
        $indexes = Schema::getIndexes('fit_proper_pics');
        $this->assertTrue(collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === ['activity_id', 'user_id']));
        foreach ([$first, $second] as $pic) {
            $this->actingAs($pic)->get(route('fit-proper.show', $activity))->assertOk();
            $this->get(route('fit-proper'))->assertSee($activity->name);
        }
        $this->actingAs($outsider)->get(route('fit-proper.show', $activity))->assertForbidden();
        $this->actingAs($first)->deleteJson(route('fit-proper.pics.delete', [$activity, $second]))->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('fit-proper.pics.delete', [$activity, $first]))->assertOk();
        $this->assertSame([$second->id], $activity->pics()->pluck('users.id')->all());
        $this->actingAs($first)->get(route('fit-proper.show', $activity))->assertForbidden();
        $this->actingAs($second)->get(route('fit-proper.show', $activity))->assertOk();
    }

    public function test_new_link_is_public_preserves_login_and_supports_draft_submit_and_scoped_cv(): void
    {
        Storage::fake('local');
        [$admin,$activity] = $this->context();
        $this->putJson(route('fit-proper.pics', $activity), ['users' => [$admin->id]])->assertOk();
        $this->post(route('fit-proper.participants.store', $activity), ['name' => 'Kandidat tautan', 'kpi_year_1' => 4, 'kpi_year_2' => 4, 'assessment' => 3, 'cv' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertRedirect();
        $participant = $activity->participants()->sole();
        $response = $this->postJson(route('fit-proper.examiners', $activity), ['name' => 'Penguji tanpa akun']);
        $response->assertOk()->assertJsonStructure(['examiner_id', 'examiner_link', 'html']);
        $examiner = FitProperExaminer::findOrFail($response->json('examiner_id'));
        $link = $response->json('examiner_link');
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->assertSame(hash('sha256', $token), $examiner->getRawOriginal('token_hash'));
        $this->assertStringNotContainsString($examiner->getRawOriginal('token_hash'), $link);
        $this->get($link)->assertNotFound()->assertSee('Kegiatan belum dibuka');
        $this->post(route('fit-proper.transition', $activity), ['status' => 'Berjalan'])->assertRedirect();
        $admin->update(['must_change_password' => true]);
        $this->get($link)->assertOk()->assertSee('Kandidat tautan');
        $this->assertAuthenticatedAs($admin);
        $admin->update(['must_change_password' => false]);
        $this->post(route('logout'));
        $this->get($link)->assertOk();
        $this->assertGuest();
        $this->get(route('fit-proper.link.cv', [$token, $participant]))->assertOk();
        $other = app(FitProperService::class)->create(['name' => 'Lain', 'target_position' => 'Lain', 'scheduled_at' => '2026-10-19'], $admin);
        $foreign = app(FitProperService::class)->participant($other, ['name' => 'Di luar kegiatan']);
        $this->get(route('fit-proper.link.cv', [$token, $foreign]))->assertNotFound();
        $this->post($link, ['intent' => 'draft', 'scores' => [$participant->id => ['adaptability' => 0]]])->assertRedirect();
        $this->assertSame(0, $examiner->scores()->sole()->indicators['adaptability']);
        $this->assertNull($examiner->fresh()->submitted_at);
        $this->postJson($link, ['intent' => 'submit', 'scores' => [$participant->id => ['adaptability' => 0]]])->assertUnprocessable();
        $values = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 4);
        $this->post($link, ['intent' => 'submit', 'scores' => [$participant->id => $values]])->assertRedirect();
        $this->assertNotNull($examiner->fresh()->submitted_at);
        $this->assertSame($examiner->id, $examiner->scores()->sole()->examiner_id);
        app(FitProperService::class)->transition($activity, 'Selesai', $admin);
        $this->get($link)->assertNotFound()->assertSee('Kegiatan sudah selesai');
        $this->actingAs($admin)->deleteJson(route('fit-proper.pics.delete', [$activity, $admin]))->assertUnprocessable();
        $this->postJson(route('fit-proper.examiners', $activity), ['name' => 'Tidak boleh'])->assertUnprocessable();
    }

    public function test_status_opening_refreshes_ajax_ui_without_rotating_tokens_or_bypassing_readiness(): void
    {
        [$admin, $activity] = $this->context();
        $service = app(FitProperService::class);
        $response = $this->postJson(route('fit-proper.examiners', $activity), ['name' => 'Penguji status'])->assertOk();
        $link = $response->json('examiner_link');
        $examiner = $activity->examiners()->sole();
        $hash = $examiner->getRawOriginal('token_hash');
        $this->assertSame('Draft', $activity->fresh()->status);
        $this->get($link)->assertNotFound()->assertSee('Kegiatan belum dibuka');
        $this->get(route('fit-proper.show', $activity))->assertSee('Mulai Kegiatan / Buka Penilaian')->assertSee('Penilaian belum dibuka');
        $this->postJson(route('fit-proper.transition', $activity), ['status' => 'Berjalan'])->assertUnprocessable();
        $this->assertSame('Draft', $activity->fresh()->status);
        $service->assignPics($activity, [$admin->id], $admin);
        $participant = $service->participant($activity, ['name' => 'Kandidat status', 'kpi_year_1' => 4, 'kpi_year_2' => 4, 'assessment' => 3]);
        $outsider = User::factory()->create(['role' => 'Staff']);
        $this->actingAs($outsider)->postJson(route('fit-proper.transition', $activity), ['status' => 'Berjalan'])->assertForbidden();
        $response = $this->actingAs($admin)->postJson(route('fit-proper.transition', $activity), ['status' => 'Berjalan'])->assertOk()->assertJsonPath('structure_changed', true)->assertJsonPath('invalidate_links', false);
        $this->assertStringContainsString('Penilaian sudah dibuka', $response->json('html'));
        $this->assertStringNotContainsString('Tambah peserta</a>', $response->json('html'));
        $this->assertSame('Berjalan', $activity->fresh()->status);
        $this->assertSame($hash, $examiner->fresh()->getRawOriginal('token_hash'));
        $this->get($link)->assertOk();
        $values = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 4);
        $service->saveScores($examiner, ['scores' => [$participant->id => $values]], true);
        $response = $this->postJson(route('fit-proper.transition', $activity), ['status' => 'Selesai'])->assertOk()->assertJsonPath('invalidate_links', true);
        $this->assertStringContainsString('Seluruh penilaian terkunci', $response->json('html'));
        $this->get($link)->assertNotFound()->assertSee('Kegiatan sudah selesai');
        $this->postJson($link, ['intent' => 'draft', 'scores' => [$participant->id => $values]])->assertNotFound();
        $this->postJson(route('fit-proper.transition', $activity), ['status' => 'Berjalan'])->assertUnprocessable();
    }

    public function test_link_errors_reissue_and_async_examiner_delete_are_explicit(): void
    {
        [$admin,$activity] = $this->context();
        $first = $this->postJson(route('fit-proper.examiners', $activity), ['name' => 'Penguji']);
        $examiner = FitProperExaminer::findOrFail($first->json('examiner_id'));
        $oldLink = $first->json('examiner_link');
        $this->get(route('fit-proper.link', 'not-a-valid-token'))->assertNotFound()->assertSee('tidak valid');
        $service = app(FitProperService::class);
        $service->assignPics($activity, [$admin->id], $admin);
        $service->participant($activity, ['name' => 'Peserta']);
        $service->transition($activity, 'Berjalan', $admin);
        $examiner->update(['expires_at' => now()->subMinute()]);
        $this->get($oldLink)->assertNotFound()->assertSee('kedaluwarsa');
        $newLink = $this->postJson(route('fit-proper.token', [$activity, $examiner]))->assertOk()->json('examiner_link');
        $this->get($oldLink)->assertNotFound()->assertSee('tidak valid');
        $this->get($newLink)->assertOk();
        $this->postJson(route('fit-proper.token', [$activity, $examiner]), ['revoke' => true])->assertOk();
        $this->get($newLink)->assertNotFound()->assertSee('telah dicabut');
        $this->deleteJson(route('fit-proper.examiners.delete', [$activity, $examiner]))->assertUnprocessable();
        $service->transition($activity, 'Draft', $admin);
        $response = $this->deleteJson(route('fit-proper.examiners.delete', [$activity, $examiner]))->assertOk();
        $this->assertStringContainsString('0 penguji ditugaskan', $response->json('html'));
        $this->assertDatabaseMissing('fit_proper_examiners', ['id' => $examiner->id]);
    }
}
