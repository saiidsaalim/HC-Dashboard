<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->renameColumn('incumbent_name', 'incumbent');
        });
    }

    public function down(): void
    {
        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->renameColumn('incumbent', 'incumbent_name');
        });
    }
};
