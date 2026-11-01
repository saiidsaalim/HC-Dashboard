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
        Schema::create('employee_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_file');
            $table->string('original_file_name')->nullable();
            $table->string('source_type', 50);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at');
            $table->string('status', 30)->default('PENDING')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('approved_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_import_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('source_row');
            $table->string('source_reference')->nullable();
            $table->json('source_payload');
            $table->json('normalized_payload')->nullable();
            $table->string('validation_status', 30)->default('PENDING')->index();
            $table->string('mapping_status', 30)->default('REVIEW_REQUIRED')->index();
            $table->json('validation_errors')->nullable();
            $table->json('mapping_errors')->nullable();
            $table->json('processing_result')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_import_batch_id', 'source_row']);
        });

        Schema::create('employee_organization_mapping_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_directorate_id')->nullable()->constrained('directorates')->nullOnDelete();
            $table->foreignId('new_directorate_id')->nullable()->constrained('directorates')->nullOnDelete();
            $table->foreignId('old_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('new_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('old_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('new_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('old_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('new_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('old_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('new_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_organization_mapping_audits');
        Schema::dropIfExists('employee_import_rows');
        Schema::dropIfExists('employee_import_batches');
    }
};
