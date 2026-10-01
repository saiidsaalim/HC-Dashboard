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
            $table->string('pg')->nullable()->after('tanggal_mulai_terhitung');
            $table->string('band_lama')->nullable()->after('pg');
            $table->string('jg_lama')->nullable()->after('band_lama');
            $table->string('band_baru')->nullable()->after('jg_lama');
            $table->string('jg_baru')->nullable()->after('band_baru');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->dropColumn(['pg', 'band_lama', 'jg_lama', 'band_baru', 'jg_baru']);
        });
    }
};
