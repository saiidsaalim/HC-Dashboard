<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->foreignId('employee_import_batch_id')
                ->constrained('employee_import_batches')
                ->restrictOnDelete();
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('employee_import_rows')->exists() || DB::table('employee_import_batches')->exists()) {
            throw new RuntimeException('Cannot roll back Employee import staging while batch or source rows exist.');
        }

        Schema::dropIfExists('employee_import_rows');
        Schema::dropIfExists('employee_import_batches');
    }
};
