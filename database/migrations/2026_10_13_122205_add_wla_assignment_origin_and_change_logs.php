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
        Schema::table('wla_participants', function (Blueprint $table): void {
            $table->foreignId('assigned_by')
                ->nullable()
                ->after('employee_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('wla_activities', function (Blueprint $table): void {
            $table->string('origin', 20)->default('legacy')->after('wla_assessment_id');
        });

        Schema::create('wla_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wla_assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assessment_code_snapshot', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role', 50);
            $table->string('event', 64);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wla_assessment_id', 'created_at']);
            $table->index('actor_id');
            $table->index('event');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wla_change_logs');

        Schema::table('wla_activities', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });

        Schema::table('wla_participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_by');
        });
    }
};
