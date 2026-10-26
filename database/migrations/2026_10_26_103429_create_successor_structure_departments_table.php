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
        Schema::create('successor_structure_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number');
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $departments = [
            [1, 'Market Planning & Development', 'market-planning-development'],
            [2, 'Sales', 'sales'],
            [3, 'Internal Audit', 'internal-audit'],
            [4, 'Communication Legal & GA', 'communication-legal-ga'],
            [5, 'Finance & Accounting', 'finance-accounting'],
            [6, 'HC & GRC', 'hc-grc'],
            [7, 'Production Planning & Control', 'production-planning-control'],
            [8, 'Mining & Power Plant', 'mining-power-plant'],
            [9, 'Clinker & Cement Production', 'clinker-cement-production'],
            [10, 'Infrastructure', 'infrastructure'],
            [11, 'Maintenance', 'maintenance'],
            [12, 'Project Management & Maint. Support', 'project-management-maint-support'],
        ];

        DB::table('successor_structure_departments')->insert(array_map(
            fn (array $department): array => [
                'number' => $department[0],
                'name' => $department[1],
                'slug' => $department[2],
                'sort_order' => $department[0],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $departments,
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('successor_structure_departments');
    }
};
