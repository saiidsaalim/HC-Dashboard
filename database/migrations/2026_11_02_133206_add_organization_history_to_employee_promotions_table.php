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
        Schema::table('employee_promotions', function (Blueprint $table) {
            $table->foreignId('old_directorate_id')->nullable()->constrained('directorates')->restrictOnDelete();
            $table->foreignId('new_directorate_id')->nullable()->constrained('directorates')->restrictOnDelete();
            $table->foreignId('old_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('new_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('old_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('new_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('old_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('new_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('old_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->foreignId('new_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->string('direktorat_lama')->nullable();
            $table->string('direktorat_baru')->nullable();
            $table->string('unit_lama')->nullable();
            $table->string('unit_baru')->nullable();
            $table->string('section_lama')->nullable();
            $table->string('section_baru')->nullable();
            $table->timestamp('employee_applied_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('old_directorate_id');
            $table->dropConstrainedForeignId('new_directorate_id');
            $table->dropConstrainedForeignId('old_department_id');
            $table->dropConstrainedForeignId('new_department_id');
            $table->dropConstrainedForeignId('old_unit_id');
            $table->dropConstrainedForeignId('new_unit_id');
            $table->dropConstrainedForeignId('old_section_id');
            $table->dropConstrainedForeignId('new_section_id');
            $table->dropConstrainedForeignId('old_position_id');
            $table->dropConstrainedForeignId('new_position_id');
            $table->dropColumn([
                'direktorat_lama', 'direktorat_baru', 'unit_lama', 'unit_baru',
                'section_lama', 'section_baru', 'employee_applied_at',
            ]);
        });
    }
};
