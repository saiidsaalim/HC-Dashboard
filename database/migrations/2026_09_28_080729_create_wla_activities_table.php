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
        Schema::create('wla_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('wla_assessment_id');
            $table->string('activity_name');
            $table->decimal('frequency', 10, 2)->unsigned();
            $table->string('frequency_unit', 16);
            $table->decimal('volume', 12, 2)->unsigned();
            $table->string('volume_unit', 16);
            $table->decimal('time_allocated', 10, 2)->unsigned();
            $table->string('time_unit', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('wla_assessment_id')->references('id')->on('wla_assessments')->restrictOnDelete();
            $table->index(['wla_assessment_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wla_activities');
    }
};
