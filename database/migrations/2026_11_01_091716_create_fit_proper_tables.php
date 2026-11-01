<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fit_proper_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('standard');
            $table->timestamps();
        });

        Schema::create('fit_proper_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('target_position');
            $table->string('department')->nullable();
            $table->date('scheduled_at');
            $table->text('description')->nullable();
            $table->string('status')->default('Draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('standard');
            $table->json('final_snapshot')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->boolean('is_simulation')->default(false)->index();
            $table->string('demo_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('fit_proper_pics', function (Blueprint $table): void {
            $table->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['activity_id', 'user_id']);
        });

        Schema::create('fit_proper_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fit_proper_activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('internal_identity');
            $table->string('name');
            $table->string('sap')->nullable();
            $table->date('birth_date')->nullable();
            $table->json('profile');
            $table->decimal('kpi_year_1', 12, 6)->nullable();
            $table->decimal('kpi_year_2', 12, 6)->nullable();
            $table->decimal('assessment', 12, 6)->nullable();
            $table->string('cv_path')->nullable();
            $table->timestamps();

            $table->unique(['fit_proper_activity_id', 'employee_id'], 'fp_participant_employee_unique');
            $table->unique(['fit_proper_activity_id', 'internal_identity'], 'fp_participant_identity_unique');
        });

        Schema::create('fit_proper_examiners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
        });

        Schema::create('fit_proper_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('participant_id')->constrained('fit_proper_participants')->cascadeOnDelete();
            $table->foreignId('examiner_id')->constrained('fit_proper_examiners')->cascadeOnDelete();
            $table->json('indicators');
            $table->text('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'examiner_id']);
        });

        Schema::create('fit_proper_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('fit_proper_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('context')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fit_proper_histories');
        Schema::dropIfExists('fit_proper_scores');
        Schema::dropIfExists('fit_proper_examiners');
        Schema::dropIfExists('fit_proper_participants');
        Schema::dropIfExists('fit_proper_pics');
        Schema::dropIfExists('fit_proper_activities');
        Schema::dropIfExists('fit_proper_settings');
    }
};
