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
        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->string('calculation_type', 20)->nullable()->after('schedule_type');
        });

        Schema::table('wla_activities', function (Blueprint $table): void {
            $table->string('period_unit', 16)->nullable()->after('frequency');
            $table->decimal('time_allocated_hours', 12, 2)->nullable()->after('time_unit');
            $table->decimal('annual_workload_hours', 24, 4)->nullable()->after('time_allocated_hours');
        });

        Schema::table('wla_assessments', function (Blueprint $table): void {
            $table->decimal('total_annual_workload_hours', 24, 4)->default(0)->after('effective_working_hours');
            $table->decimal('fte', 16, 6)->default(0)->after('total_annual_workload_hours');
            $table->unsignedInteger('recommended_employees')->default(0)->after('fte');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wla_assessments', function (Blueprint $table): void {
            $table->dropColumn(['total_annual_workload_hours', 'fte', 'recommended_employees']);
        });

        Schema::table('wla_activities', function (Blueprint $table): void {
            $table->dropColumn(['period_unit', 'time_allocated_hours', 'annual_workload_hours']);
        });

        Schema::table('work_schedules', function (Blueprint $table): void {
            $table->dropColumn('calculation_type');
        });
    }
};
