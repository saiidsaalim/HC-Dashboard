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
        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('schedule_type');
            $table->string('calculation_type', 20)->nullable();
            $table->decimal('working_hours_per_day', 5, 2)->unsigned();
            $table->unsignedTinyInteger('working_days_per_week');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('work_calendars', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedSmallInteger('total_days');
            $table->unsignedTinyInteger('total_weeks');
            $table->unsignedSmallInteger('annual_leave')->default(0);
            $table->unsignedSmallInteger('national_holiday')->default(0);
            $table->unsignedSmallInteger('common_leave')->default(0);
            $table->unsignedSmallInteger('saturday_days')->default(0);
            $table->unsignedSmallInteger('sunday_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_calendars');
        Schema::dropIfExists('work_schedules');
    }
};
