<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_calendars', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
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

            $table->unique('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_calendars');
    }
};
