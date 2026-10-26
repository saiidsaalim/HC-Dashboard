<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->dropColumn('job_level');
        });
    }

    public function down(): void
    {
        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->string('job_level', 100);
        });
    }
};
