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
            $table->unsignedSmallInteger('period');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('work_schedule_id');
            $table->unsignedBigInteger('work_calendar_id');
            $table->decimal('efficiency_factor', 5, 4)->unsigned();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedSmallInteger('working_days')->nullable();
            $table->decimal('working_hours_year', 10, 2)->nullable();
            $table->decimal('effective_working_hours', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->restrictOnDelete();
            $table->foreign('work_schedule_id')->references('id')->on('work_schedules')->restrictOnDelete();
            $table->foreign('work_calendar_id')->references('id')->on('work_calendars')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('period');
            $table->index('department_id');
            $table->index('unit_id');
            $table->index('position_id');
            $table->index('work_schedule_id');
            $table->index('work_calendar_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wla_assessments');
    }
};
