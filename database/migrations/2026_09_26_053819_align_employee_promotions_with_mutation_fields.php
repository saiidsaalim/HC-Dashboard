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
        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->renameColumn('departemen', 'departemen_lama');
            $table->renameColumn('tanggal_promosi', 'tanggal_mulai_terhitung');
            $table->string('departemen_baru')->nullable()->after('departemen_lama');
        });

        DB::table('employee_promotions')->update([
            'departemen_baru' => DB::raw('departemen_lama'),
        ]);

        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->string('departemen_baru')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->renameColumn('tanggal_mulai_terhitung', 'tanggal_promosi');
            $table->dropColumn('departemen_baru');
            $table->renameColumn('departemen_lama', 'departemen');
        });
    }
};
