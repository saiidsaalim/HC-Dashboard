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
        if (! Schema::hasColumn('employee_organization_mapping_audits', 'old_directorate_id')) {
            Schema::table('employee_organization_mapping_audits', function (Blueprint $table): void {
                $table->foreignId('old_directorate_id')->nullable()->after('employee_id')->constrained('directorates')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('employee_organization_mapping_audits', 'new_directorate_id')) {
            Schema::table('employee_organization_mapping_audits', function (Blueprint $table): void {
                $table->foreignId('new_directorate_id')->nullable()->after('old_directorate_id')->constrained('directorates')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('employee_organization_mapping_audits', 'old_section_id')) {
            Schema::table('employee_organization_mapping_audits', function (Blueprint $table): void {
                $table->foreignId('old_section_id')->nullable()->after('new_unit_id')->constrained('sections')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('employee_organization_mapping_audits', 'new_section_id')) {
            Schema::table('employee_organization_mapping_audits', function (Blueprint $table): void {
                $table->foreignId('new_section_id')->nullable()->after('old_section_id')->constrained('sections')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['new_section_id', 'old_section_id', 'new_directorate_id', 'old_directorate_id'] as $column) {
            if (Schema::hasColumn('employee_organization_mapping_audits', $column)) {
                Schema::table('employee_organization_mapping_audits', function (Blueprint $table) use ($column): void {
                    $table->dropConstrainedForeignId($column);
                });
            }
        }
    }
};
