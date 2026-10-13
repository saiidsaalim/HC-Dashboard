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
    }
};
