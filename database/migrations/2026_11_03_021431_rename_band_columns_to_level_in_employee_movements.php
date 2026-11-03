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
        foreach (['employee_promotions', 'employee_demotions', 'employee_mutations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->renameColumn('band_lama', 'level_lama');
                $table->renameColumn('band_baru', 'level_baru');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['employee_promotions', 'employee_demotions', 'employee_mutations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->renameColumn('level_lama', 'band_lama');
                $table->renameColumn('level_baru', 'band_baru');
            });
        }
    }
};
