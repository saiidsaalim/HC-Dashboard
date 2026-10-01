<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique('employees_personnel_no_unique');
            $table->renameColumn('personnel_no', 'sap');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->unique('sap');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique('employees_sap_unique');
            $table->renameColumn('sap', 'personnel_no');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->unique('personnel_no');
        });
    }
};
