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
        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->renameColumn('tanggal_mulai_terhitung', 'tmt');
        });

        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->renameColumn('tanggal_mulai_terhitung', 'tmt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->renameColumn('tmt', 'tanggal_mulai_terhitung');
        });

        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->renameColumn('tmt', 'tanggal_mulai_terhitung');
        });
    }
};
