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
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->index();
            $table->foreignId('unit_id')->nullable()->index();
            $table->foreignId('position_id')->nullable()->index();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
