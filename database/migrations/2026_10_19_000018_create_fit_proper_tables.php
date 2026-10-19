<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('fit_proper_settings', function (Blueprint $t) {
            $t->id();
            $t->json('standard');
            $t->timestamps();
        });
        $this->createIfMissing('fit_proper_activities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('target_position');
            $t->string('department')->nullable();
            $t->date('scheduled_at');
            $t->text('description')->nullable();
            $t->string('status')->default('Draft')->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('standard');
            $t->json('final_snapshot')->nullable();
            $t->timestamp('finalized_at')->nullable();
            $t->boolean('is_simulation')->default(false)->index();
            $t->string('demo_key')->nullable()->unique();
            $t->timestamps();
        });
        $this->createIfMissing('fit_proper_pics', function (Blueprint $t) {
            $t->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['activity_id', 'user_id']);
        });
        $this->createIfMissing('fit_proper_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->uuid('internal_identity');
            $t->string('name');
            $t->string('sap')->nullable();
            $t->date('birth_date')->nullable();
            $t->json('profile');
            $t->decimal('kpi_year_1', 12, 6)->nullable();
            $t->decimal('kpi_year_2', 12, 6)->nullable();
            $t->decimal('assessment', 12, 6)->nullable();
            $t->string('cv_path')->nullable();
            $t->timestamps();
            $t->unique(['activity_id', 'employee_id']);
            $t->unique(['activity_id', 'internal_identity']);
        });
        $this->createIfMissing('fit_proper_examiners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('token_hash', 64)->nullable()->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
            $t->unique(['activity_id', 'user_id']);
        });
        $this->createIfMissing('fit_proper_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participant_id')->constrained('fit_proper_participants')->cascadeOnDelete();
            $t->foreignId('examiner_id')->constrained('fit_proper_examiners')->cascadeOnDelete();
            $t->json('indicators');
            $t->text('recommendation')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['participant_id', 'examiner_id']);
        });
        $this->createIfMissing('fit_proper_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->json('context')->nullable();
            $t->timestamp('created_at');
        });
    }

    /**
     * Adopt existing tables without replacing their schema or data.
     */
    private function createIfMissing(string $table, callable $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback Fit & Proper dinonaktifkan agar tabel dan data yang sudah ada tidak terhapus.');
    }
};
