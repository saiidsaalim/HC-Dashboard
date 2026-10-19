<?php

namespace Tests\Feature;

use App\Models\FitProperActivity;
use App\Models\FitProperParticipant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FitProperSchemaCompatibilityTest extends TestCase
{
    public function test_existing_mysql_shaped_schema_is_adopted_without_duplicate_keys_or_data_loss(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $paths = [];
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (! str_contains(basename($path), 'fit_proper')) {
                $paths[] = 'database/migrations/'.basename($path);
            }
        }
        $this->artisan('migrate', ['--path' => $paths, '--no-interaction' => true])->assertSuccessful();
        $admin = User::factory()->create(['role' => 'Admin', 'must_change_password' => false]);
        $employeeId = DB::table('employees')->insertGetId([
            'name' => 'Pegawai Existing', 'sap' => 'EXISTING-FP-001',
            'position' => 'Staf', 'birth_date' => '1990-01-01',
        ]);
        Schema::create('fit_proper_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('position', 150);
            $table->string('department', 150)->nullable();
            $table->date('event_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('passing_grade', 5, 2)->default(75);
            $table->decimal('weight_kpi', 4, 3)->default(0.1);
            $table->decimal('weight_assessment', 4, 3)->default(0.4);
            $table->decimal('weight_fit_proper', 4, 3)->default(0.5);
            $table->decimal('kpi_scale', 5, 2)->default(5);
            $table->decimal('assessment_scale', 5, 2)->default(4);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::create('fit_proper_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fit_proper_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->smallInteger('jg_current')->nullable();
            $table->smallInteger('jg_target')->nullable();
            $table->smallInteger('jg_gap')->nullable();
            $table->string('education_background', 150)->nullable();
            foreach (['experience', 'training', 'achievement', 'assessment_recommendation'] as $column) {
                $table->text($column)->nullable();
            }
            foreach (['kpi_year_1', 'kpi_year_2', 'kpi_score'] as $column) {
                $table->decimal($column, 4, 2)->nullable();
            }
            $table->decimal('assessment_score', 5, 2)->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->timestamps();
        });
        Schema::create('fit_proper_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('standard');
            $table->timestamps();
        });
        Schema::create('fit_proper_activity_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fit_proper_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        $activityId = DB::table('fit_proper_activities')->insertGetId([
            'name' => 'Kegiatan Existing', 'position' => 'Kepala Existing',
            'department' => 'Unit Existing', 'event_date' => '2026-10-19',
            'passing_grade' => 80, 'created_by' => $admin->id,
        ]);
        $participantId = DB::table('fit_proper_participants')->insertGetId([
            'fit_proper_activity_id' => $activityId, 'employee_id' => $employeeId,
            'jg_current' => 12, 'jg_target' => 10, 'jg_gap' => 2,
            'kpi_year_1' => 0, 'kpi_year_2' => 0, 'assessment_score' => 0,
            'assessment_recommendation' => 'Narasi existing', 'cv_path' => 'private-existing.pdf',
        ]);
        DB::table('fit_proper_activity_user')->insert([
            'fit_proper_activity_id' => $activityId, 'user_id' => $admin->id,
        ]);
        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('fit_proper_participants', 'activity_id'));
        $this->assertTrue(Schema::hasColumn('fit_proper_participants', 'fit_proper_activity_id'));
        foreach (Schema::getIndexes('fit_proper_participants') as $index) {
            $this->assertLessThanOrEqual(64, strlen($index['name']));
        }
        $activity = FitProperActivity::findOrFail($activityId);
        $participant = FitProperParticipant::findOrFail($participantId);
        $this->assertSame($activityId, $participant->activity->id);
        $this->assertSame($participantId, $activity->participants()->sole()->id);
        $this->assertSame('Kepala Existing', $activity->target_position);
        $this->assertSame('Draft', $activity->status);
        $this->assertEquals(80, $activity->standard['passing_grade']);
        $this->assertSame('Pegawai Existing', $participant->name);
        $this->assertSame(0.0, $participant->kpi());
        $this->assertEquals(0, $participant->assessment);
        $this->assertSame('Narasi existing', $participant->profile['assessment_notes']);
        $this->assertSame('private-existing.pdf', $participant->cv_path);
        $this->assertSame(2, $participant->profile['gap_jg']);
        $this->assertTrue($activity->pics()->where('users.id', $admin->id)->exists());
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === 0);
        $this->get(route('fit-proper.show', $activity))->assertOk();
        $this->post(route('fit-proper.participants.store', $activity), ['name' => 'Manual baru'])->assertRedirect();
        $manual = $activity->participants()->whereNull('employee_id')->sole();
        $this->assertSame($activityId, $manual->fit_proper_activity_id);
        $this->put(route('fit-proper.participants.update', [$activity, $manual]), ['name' => 'Manual diperbarui'])->assertRedirect();
        $this->assertSame('Manual diperbarui', $manual->fresh()->name);
        $this->post(route('fit-proper.participants.store', $activity), ['employee_id' => $employeeId])->assertSessionHasErrors('employee_id');
        $this->post(route('fit-proper.store'), [
            'name' => 'Kegiatan baru', 'target_position' => 'Kepala baru', 'scheduled_at' => '2026-10-19',
        ])->assertRedirect();
        $newActivity = FitProperActivity::where('name', 'Kegiatan baru')->sole();
        $this->assertSame('Draft', $newActivity->status);
        $this->post(route('fit-proper.participants.store', $newActivity), ['name' => 'Manual kegiatan baru'])->assertRedirect();
        $migration = require database_path('migrations/2026_10_19_002405_reconcile_fit_proper_schema_with_existing_tables.php');
        $identity = $participant->internal_identity;
        $migration->up();
        $this->assertSame($identity, $participant->fresh()->internal_identity);
        $this->assertSame(3, FitProperParticipant::count());
        $this->assertDatabaseHas('fit_proper_participants', ['id' => $participantId, 'assessment_score' => 0]);
        $migration->down();
        $this->assertSame(3, FitProperParticipant::count());
    }
}
