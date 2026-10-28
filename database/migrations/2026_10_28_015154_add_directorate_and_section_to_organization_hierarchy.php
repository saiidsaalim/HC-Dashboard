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
        if (! Schema::hasColumn('departments', 'directorate_id')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->foreignId('directorate_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('positions', 'section_id')) {
            Schema::table('positions', function (Blueprint $table): void {
                $table->foreignId('section_id')->nullable()->after('unit_id')->constrained()->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('employees', 'directorate_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->foreignId('directorate_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('employees', 'section_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->foreignId('section_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employees', 'section_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('section_id');
            });
        }

        if (Schema::hasColumn('employees', 'directorate_id')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('directorate_id');
            });
        }

        if (Schema::hasColumn('positions', 'section_id')) {
            Schema::table('positions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('section_id');
            });
        }

        if (Schema::hasColumn('departments', 'directorate_id')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('directorate_id');
            });
        }
    }
};
