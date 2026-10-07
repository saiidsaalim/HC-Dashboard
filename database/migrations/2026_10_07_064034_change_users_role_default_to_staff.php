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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('Staff')->change();
        });

        DB::table('users')
            ->whereLike('role', 'Pegawai', caseSensitive: true)
            ->update(['role' => 'Staff']);
    }

    /**
     * Restore the previous default while preserving existing Staff roles.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('Pegawai')->change();
        });
    }
};
