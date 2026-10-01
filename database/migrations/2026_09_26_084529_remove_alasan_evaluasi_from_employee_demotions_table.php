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
        Schema::table('employee_demotions', function (Blueprint $table) {
            $table->dropColumn('alasan_evaluasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_demotions', function (Blueprint $table) {
            $table->text('alasan_evaluasi')->after('jg_baru');
        });
    }
};
