<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->reconcileParticipantKey();
        $this->extendActivities();
        $this->extendParticipants();
        $this->preservePicAssignments();
    }

    private function reconcileParticipantKey(): void
    {
        $hasCanonical = Schema::hasColumn('fit_proper_participants', 'fit_proper_activity_id');
        $hasShort = Schema::hasColumn('fit_proper_participants', 'activity_id');
        if ($hasCanonical && $hasShort) {
            throw new RuntimeException('Peserta memiliki dua kolom kegiatan. Rekonsiliasi data perlu ditinjau sebelum migration dilanjutkan.');
        }
        if ($hasShort) {
            Schema::table('fit_proper_participants', function (Blueprint $table): void {
                $table->renameColumn('activity_id', 'fit_proper_activity_id');
            });
        } elseif (! $hasCanonical) {
            throw new RuntimeException('Kolom penghubung kegiatan peserta tidak ditemukan; tidak menambahkan foreign key kosong pada data lama.');
        }
    }

    private function extendActivities(): void
    {
        foreach ([
            'target_position' => fn (Blueprint $table) => $table->string('target_position')->nullable(),
            'scheduled_at' => fn (Blueprint $table) => $table->date('scheduled_at')->nullable(),
            'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            'standard' => fn (Blueprint $table) => $table->json('standard')->nullable(),
            'final_snapshot' => fn (Blueprint $table) => $table->json('final_snapshot')->nullable(),
            'finalized_at' => fn (Blueprint $table) => $table->timestamp('finalized_at')->nullable(),
            'is_simulation' => fn (Blueprint $table) => $table->boolean('is_simulation')->default(false)->index(),
            'demo_key' => fn (Blueprint $table) => $table->string('demo_key')->nullable()->unique(),
        ] as $column => $definition) {
            $this->addColumn('fit_proper_activities', $column, $definition);
        }
        if (Schema::hasColumn('fit_proper_activities', 'position')) {
            Schema::table('fit_proper_activities', function (Blueprint $table): void {
                $table->string('position', 150)->nullable()->change();
            });
        }
        DB::table('fit_proper_activities')->orderBy('id')->chunkById(100, function ($activities): void {
            foreach ($activities as $activity) {
                $attributes = [];
                if ($activity->standard === null) {
                    $attributes['standard'] = json_encode([
                        'weight_kpi' => (float) ($activity->weight_kpi ?? 0.1),
                        'weight_assessment' => (float) ($activity->weight_assessment ?? 0.4),
                        'weight_fp' => (float) ($activity->weight_fit_proper ?? 0.5),
                        'max_kpi' => (float) ($activity->kpi_scale ?? 5),
                        'max_assessment' => (float) ($activity->assessment_scale ?? 4),
                        'passing_grade' => (float) ($activity->passing_grade ?? 75),
                    ], JSON_THROW_ON_ERROR);
                }
                if ($activity->target_position === null) {
                    $attributes['target_position'] = $activity->position ?? '';
                }
                if ($activity->scheduled_at === null && isset($activity->event_date)) {
                    $attributes['scheduled_at'] = $activity->event_date;
                }
                $status = ['draft' => 'Draft', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'][strtolower($activity->status)] ?? $activity->status;
                if ($status !== $activity->status) {
                    $attributes['status'] = $status;
                }
                if ($attributes !== []) {
                    DB::table('fit_proper_activities')->where('id', $activity->id)->update($attributes);
                }
            }
        });
    }

    private function extendParticipants(): void
    {
        foreach ([
            'internal_identity' => fn (Blueprint $table) => $table->uuid('internal_identity')->nullable(),
            'name' => fn (Blueprint $table) => $table->string('name')->nullable(),
            'sap' => fn (Blueprint $table) => $table->string('sap')->nullable(),
            'birth_date' => fn (Blueprint $table) => $table->date('birth_date')->nullable(),
            'profile' => fn (Blueprint $table) => $table->json('profile')->nullable(),
            'assessment' => fn (Blueprint $table) => $table->decimal('assessment', 12, 6)->nullable(),
        ] as $column => $definition) {
            $this->addColumn('fit_proper_participants', $column, $definition);
        }
        Schema::table('fit_proper_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            $table->decimal('kpi_year_1', 12, 6)->nullable()->change();
            $table->decimal('kpi_year_2', 12, 6)->nullable()->change();
        });
        DB::table('fit_proper_participants')->orderBy('id')->chunkById(100, function ($participants): void {
            foreach ($participants as $participant) {
                $employee = $participant->employee_id ? DB::table('employees')->find($participant->employee_id) : null;
                $activity = DB::table('fit_proper_activities')->find($participant->fit_proper_activity_id);
                $attributes = [];
                if ($participant->internal_identity === null) {
                    $attributes['internal_identity'] = (string) Str::uuid();
                }
                if ($participant->name === null) {
                    $attributes['name'] = $employee?->name ?? $employee?->sap ?? 'Peserta #'.$participant->id;
                    $attributes['sap'] = $participant->sap ?? $employee?->sap ?? $employee?->personal_number;
                    $attributes['birth_date'] = $participant->birth_date ?? $employee?->birth_date;
                }
                if ($participant->profile === null) {
                    $attributes['profile'] = json_encode([
                        'department' => $employee?->txt_dept ?? $employee?->department ?? $activity?->department,
                        'current_position' => $employee?->position,
                        'target_position' => $activity?->target_position,
                        'current_jg' => $participant->jg_current ?? null,
                        'target_jg' => $participant->jg_target ?? null,
                        'gap_jg' => isset($participant->jg_current, $participant->jg_target) ? $participant->jg_current - $participant->jg_target : null,
                        'education' => $employee?->education ?? $employee?->pendidikan,
                        'education_background' => $participant->education_background ?? null,
                        'experience' => $participant->experience ?? null,
                        'training' => $participant->training ?? null,
                        'achievement' => $participant->achievement ?? null,
                        'assessment_notes' => $participant->assessment_recommendation ?? null,
                    ], JSON_THROW_ON_ERROR);
                }
                if ($participant->assessment === null && isset($participant->assessment_score)) {
                    $attributes['assessment'] = $participant->assessment_score;
                }
                if ($attributes !== []) {
                    DB::table('fit_proper_participants')->where('id', $participant->id)->update($attributes);
                }
            }
        });
        $this->ensureUnique(['fit_proper_activity_id', 'employee_id']);
        $this->ensureUnique(['fit_proper_activity_id', 'internal_identity']);
    }

    private function preservePicAssignments(): void
    {
        if (! Schema::hasTable('fit_proper_activity_user')) {
            return;
        }
        DB::table('fit_proper_activity_user')->orderBy('id')->chunkById(100, function ($assignments): void {
            foreach ($assignments as $assignment) {
                DB::table('fit_proper_pics')->updateOrInsert([
                    'activity_id' => $assignment->fit_proper_activity_id,
                    'user_id' => $assignment->user_id,
                ], []);
            }
        });
    }

    private function addColumn(string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, $definition);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureUnique(array $columns): void
    {
        foreach (Schema::getIndexes('fit_proper_participants') as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return;
            }
        }
        $duplicates = DB::table('fit_proper_participants')->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1');
        foreach ($columns as $column) {
            $duplicates->whereNotNull($column);
        }
        if ($duplicates->exists()) {
            return;
        }
        Schema::table('fit_proper_participants', function (Blueprint $table) use ($columns): void {
            $name = in_array('employee_id', $columns, true) ? 'fp_participant_employee_unique' : 'fp_participant_identity_unique';
            $table->unique($columns, $name);
        });
    }

    /**
     * Schema adoption is forward-only: rollback must preserve existing data and snapshots.
     */
    public function down(): void {}
};
