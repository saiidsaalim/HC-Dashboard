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
        Schema::create('wla_assessments', function (Blueprint $table): void {
            $table->id();
            $table->string('assessment_code', 40)->unique();
            $table->unsignedSmallInteger('period')->index();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_calendar_id')->constrained()->restrictOnDelete();
            $table->decimal('efficiency_factor', 5, 4)->unsigned();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('finalization_key', 100)->nullable()->unique();
            $table->json('final_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('working_days')->nullable();
            $table->decimal('working_hours_year', 10, 2)->nullable();
            $table->decimal('effective_working_hours', 10, 2)->nullable();
            $table->decimal('total_annual_workload_hours', 24, 4)->default(0);
            $table->decimal('fte', 16, 6)->default(0);
            $table->unsignedInteger('recommended_employees')->default(0);
            $table->timestamps();
        });

        Schema::create('wla_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wla_assessment_id')->constrained()->restrictOnDelete();
            $table->string('origin', 20)->default('legacy');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_name');
            $table->decimal('frequency', 10, 2)->unsigned();
            $table->string('period_unit', 16)->nullable();
            $table->string('frequency_unit', 16);
            $table->decimal('volume', 12, 2)->unsigned();
            $table->string('volume_unit', 16);
            $table->decimal('time_allocated', 10, 2)->unsigned();
            $table->string('time_unit', 16);
            $table->decimal('time_allocated_hours', 12, 2)->nullable();
            $table->decimal('annual_workload_hours', 24, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['wla_assessment_id', 'sort_order']);
        });

        Schema::create('wla_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wla_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['wla_assessment_id', 'employee_id']);
            $table->index(['wla_assessment_id', 'status']);
        });

        Schema::create('wla_participant_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wla_participant_id')->constrained()->cascadeOnDelete();
            $table->string('activity_name');
            $table->decimal('frequency', 10, 2)->unsigned();
            $table->string('period_unit', 16);
            $table->decimal('time_allocated_hours', 12, 2)->unsigned();
            $table->decimal('annual_workload_hours', 24, 4)->unsigned();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['wla_participant_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wla_participant_activities');
        Schema::dropIfExists('wla_participants');
        Schema::dropIfExists('wla_activities');
        Schema::dropIfExists('wla_assessments');
    }
};
