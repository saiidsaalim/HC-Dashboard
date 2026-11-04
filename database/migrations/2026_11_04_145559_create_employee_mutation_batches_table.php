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
        Schema::create('employee_mutation_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('default_tmt');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->foreignId('employee_mutation_batch_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_mutation_batch_id');
        });

        Schema::dropIfExists('employee_mutation_batches');
    }
};
