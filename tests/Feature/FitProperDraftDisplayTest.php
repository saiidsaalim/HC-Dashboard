<?php

namespace Tests\Feature;

use App\Models\FitProperScore;
use App\Models\User;
use App\Services\FitProperCalculationService;
use App\Services\FitProperService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FitProperDraftDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    }

    private function context(bool $withParticipants = true): array
    {
        $service = app(FitProperService::class);
        $admin = User::factory()->create(['role' => 'Admin', 'must_change_password' => false]);
        $activity = $service->create(['name' => 'Uji tampilan draft', 'target_position' => 'Kepala', 'scheduled_at' => '2026-10-19'], $admin);
        $service->assignPics($activity, [$admin->id], $admin);
        if ($withParticipants) {
            foreach (['Peserta Satu', 'Peserta Dua'] as $name) {
                $service->participant($activity, ['name' => $name, 'kpi_year_1' => 4, 'kpi_year_2' => 4, 'assessment' => 3]);
            }
        }
        $first = $service->examiner($activity, ['name' => 'Penguji Satu']);
        $second = $service->examiner($activity, ['name' => 'Penguji Dua']);
        $firstToken = $service->token($activity, $first, $admin);
        $secondToken = $service->token($activity, $second, $admin);
        if ($withParticipants) {
            $service->transition($activity, 'Berjalan', $admin);
        }

        return [$activity, $first, $second, route('fit-proper.link', $firstToken), route('fit-proper.link', $secondToken)];
    }

    /**
     * @return array<string, string>
     */
    private function fields(string $html): array
    {
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $values = [];
        foreach (['input', 'textarea'] as $tag) {
            foreach ($document->getElementsByTagName($tag) as $field) {
                $name = $field->getAttribute('name');
                if (str_starts_with($name, 'scores[')) {
                    $values[$name] = $tag === 'textarea' ? $field->textContent : $field->getAttribute('value');
                }
            }
        }

        return $values;
    }

    public function test_candidates_and_empty_inputs_render_without_score_records(): void
    {
        [$activity,$first,$second,$link] = $this->context();
        $response = $this->get($link)->assertOk()->assertSee('Peserta Satu')->assertSee('Peserta Dua');
        $response->assertViewHas('scores', fn ($scores) => $scores->isEmpty());
        $values = $this->fields($response->getContent());
        $this->assertCount(24, $values);
        $this->assertSame([''], array_values(array_unique($values)));
        $this->assertSame(0, FitProperScore::count());
        $this->assertStringContainsString('data-fp-assessment', $response->getContent());
    }

    public function test_partial_zero_draft_reopens_and_updates_same_record_without_trusting_identity_input(): void
    {
        [$activity,$first,$second,$link] = $this->context();
        $participant = $activity->participants()->first();
        $payload = ['intent' => 'draft', 'examiner_id' => $second->id, '_fit_proper_examiner_id' => $second->id,
            'scores' => [$participant->id => ['adaptability' => 0, 'authenticity' => 2.5, 'notes' => 'Catatan draft saya', 'recommendation' => 'Rekomendasi saya']]];
        $this->postJson($link, $payload)->assertOk()->assertJsonPath('assessment_saved', true)->assertJsonPath('submitted', false);
        $record = FitProperScore::where('examiner_id', $first->id)->where('participant_id', $participant->id)->sole();
        $recordId = $record->id;
        $this->assertSame(0, FitProperScore::where('examiner_id', $second->id)->count());
        $response = $this->get($link)->assertOk();
        $fields = $this->fields($response->getContent());
        $prefix = 'scores['.$participant->id.']';
        $this->assertSame('0', $fields[$prefix.'[adaptability]']);
        $this->assertSame('2.5', $fields[$prefix.'[authenticity]']);
        $this->assertSame('', $fields[$prefix.'[diversity]']);
        $this->assertSame('Catatan draft saya', $fields[$prefix.'[notes]']);
        $this->assertSame('Rekomendasi saya', $fields[$prefix.'[recommendation]']);
        $payload['scores'][$participant->id]['notes'] = 'Catatan diperbarui';
        $this->postJson($link, $payload)->assertOk();
        $this->assertSame($recordId, FitProperScore::where('examiner_id', $first->id)->where('participant_id', $participant->id)->sole()->id);
        $this->assertSame('Catatan diperbarui', $record->fresh()->notes);
        $this->assertNull($first->fresh()->submitted_at);
    }

    public function test_other_examiner_drafts_and_old_validation_input_are_not_exposed(): void
    {
        [$activity,$first,$second,$firstLink,$secondLink] = $this->context();
        $participant = $activity->participants()->first();
        $this->postJson($firstLink, ['intent' => 'draft', 'scores' => [$participant->id => ['adaptability' => 0, 'notes' => 'Rahasia penguji pertama']]])->assertOk();
        $this->postJson($secondLink, ['intent' => 'draft', 'scores' => [$participant->id => ['adaptability' => 5, 'notes' => 'Catatan penguji kedua']]])->assertOk();
        $response = $this->withSession(['_old_input' => ['_fit_proper_examiner_id' => $first->id, 'scores' => [$participant->id => ['adaptability' => 1, 'notes' => 'Input gagal penguji pertama']]]])
            ->get($secondLink)->assertOk()->assertDontSee('Rahasia penguji pertama')->assertDontSee('Input gagal penguji pertama');
        $fields = $this->fields($response->getContent());
        $this->assertSame('5', $fields['scores['.$participant->id.'][adaptability]']);
        $this->assertSame('Catatan penguji kedua', $fields['scores['.$participant->id.'][notes]']);
        $this->from($firstLink)->post($firstLink, ['intent' => 'submit', '_fit_proper_examiner_id' => $second->id, 'scores' => [$participant->id => ['adaptability' => 0, 'notes' => 'Input gagal penguji pertama']]])->assertSessionHasErrors();
        $this->get($firstLink)->assertOk()->assertSee('Input gagal penguji pertama');
        $this->get($secondLink)->assertOk()->assertDontSee('Input gagal penguji pertama');
    }

    public function test_missing_candidates_show_explicit_empty_state_without_save_buttons(): void
    {
        [$activity,$first,$second,$link] = $this->context(false);
        $activity->update(['status' => 'Berjalan']);
        $this->get($link)->assertOk()->assertSee('Peserta belum ditambahkan oleh PIC')->assertDontSee('Simpan Draft');
    }

    public function test_load_failure_is_503_with_retry_and_sanitized_logging_instead_of_empty_state(): void
    {
        [$activity,$first,$second,$link] = $this->context();
        Log::spy();
        DB::connection()->beforeExecuting(function (string $query, array $bindings): void {
            if (str_starts_with($query, 'select') && str_contains($query, 'fit_proper_scores')) {
                throw new QueryException('sqlite', $query, $bindings, new \PDOException('Simulasi kegagalan baca'));
            }
        });
        $this->get($link)->assertStatus(503)->assertSee('Data penilaian gagal dimuat')->assertSee('Coba lagi')->assertDontSee('Peserta belum ditambahkan oleh PIC');
        Log::shouldHaveReceived('error')->once()->with('Gagal memuat formulir Fit & Proper.', \Mockery::on(fn ($context) => array_keys($context) === ['examiner_id', 'database_code'] && $context['examiner_id'] === $first->id));
    }

    public function test_failed_save_rolls_back_and_final_submissions_remain_locked(): void
    {
        [$activity,$first,$second,$link] = $this->context();
        $participant = $activity->participants()->first();
        $service = app(FitProperService::class);
        $failing = \Mockery::mock(FitProperService::class, [$service->calculation])->makePartial();
        $failing->shouldReceive('history')->once()->andThrow(new \RuntimeException('Simulasi kegagalan simpan'));
        $this->app->instance(FitProperService::class, $failing);
        Log::spy();
        $this->postJson($link, ['intent' => 'draft', 'scores' => [$participant->id => ['adaptability' => 0]]])->assertStatus(500)->assertJsonMissing(['assessment_saved' => true]);
        $this->assertSame(0, FitProperScore::count());
        $this->app->instance(FitProperService::class, $service);
        $this->app['router']->getRoutes()->getByName('fit-proper.link.submit')->flushController();
        $complete = [];
        foreach ($activity->participants()->get() as $candidate) {
            $complete[$candidate->id] = array_fill_keys(array_keys(FitProperCalculationService::INDICATORS), 4);
        }
        $this->postJson($link, ['intent' => 'submit', 'scores' => $complete])->assertOk()->assertJsonPath('submitted', true);
        $response = $this->get($link)->assertOk()->assertSee('Penilaian telah dikirim dan terkunci');
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertTrue($document->getElementsByTagName('fieldset')->item(0)->hasAttribute('disabled'));
        $this->postJson($link, ['intent' => 'draft', 'scores' => $complete])->assertUnprocessable();
        $service->saveScores($second, ['scores' => $complete], true);
        $service->transition($activity,'Selesai');
        $this->get($link)->assertNotFound()->assertSee('Kegiatan sudah selesai');
        $this->postJson($link,['intent' => 'draft', 'scores' => $complete])->assertNotFound();
    }
}
