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
        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->decimal('working_hours_per_day', 5, 2)->unsigned()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('work_schedules')->whereRaw('working_hours_per_day != CAST(working_hours_per_day AS INTEGER)')->exists()) {
            throw new RuntimeException('Cannot restore integer work hours while fractional schedule values exist.');
        }

        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('working_hours_per_day')->change();
        });
    }
};
