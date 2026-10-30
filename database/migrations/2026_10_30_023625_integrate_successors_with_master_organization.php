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
        $legacyMappings = DB::table('successors')
            ->leftJoin(
                'successor_structure_positions',
                'successor_structure_positions.key',
                '=',
                'successors.structure_position_key',
            )
            ->get([
                'successors.id',
                'successors.name as employee_name',
                'successor_structure_positions.name as target_position_name',
            ])
            ->map(function (object $successor): array {
                $employeeIds = DB::table('employees')
                    ->where('name', $successor->employee_name)
                    ->pluck('id');
                $positionIds = DB::table('positions')
                    ->where('name', $successor->target_position_name)
                    ->pluck('id');

                if ($employeeIds->count() !== 1 || $positionIds->count() !== 1) {
                    throw new RuntimeException(
                        "Successor lama #{$successor->id} tidak dapat dipetakan secara unik ke Employee dan Position master.",
                    );
                }

                return [
                    'id' => $successor->id,
                    'employee_id' => $employeeIds->sole(),
                    'target_position_id' => $positionIds->sole(),
                ];
            });

        Schema::table('positions', function (Blueprint $table): void {
            $table->string('job_level', 100)->nullable()->after('name');
            $table->unsignedSmallInteger('formation_count')->default(1)->after('job_level');
            $table->unsignedInteger('sort_order')->default(0)->after('formation_count');
        });

        DB::statement(<<<'SQL'
            UPDATE positions
            SET job_level = CASE
                    WHEN LOWER(name) LIKE 'gm %' OR LOWER(name) LIKE 'general manager%' THEN 'General Manager'
                    WHEN LOWER(name) LIKE 'sm %' OR LOWER(name) LIKE 'senior manager%' THEN 'Senior Manager'
                    WHEN LOWER(name) LIKE 'mgr %' OR LOWER(name) LIKE 'manager%' THEN 'Manager'
                    WHEN LOWER(name) LIKE 'spv %' OR LOWER(name) LIKE 'supervisor%' THEN 'Supervisor'
                    ELSE 'Staff'
                END,
                formation_count = CASE
                    WHEN (SELECT COUNT(*) FROM employees WHERE employees.position_id = positions.id) < 1 THEN 1
                    ELSE (SELECT COUNT(*) FROM employees WHERE employees.position_id = positions.id)
                END,
                sort_order = id
            SQL);

        Schema::table('successors', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->after('id');
            $table->foreignId('target_position_id')->nullable()->after('employee_id');
        });

        foreach ($legacyMappings as $mapping) {
            DB::table('successors')->where('id', $mapping['id'])->update([
                'employee_id' => $mapping['employee_id'],
                'target_position_id' => $mapping['target_position_id'],
            ]);
        }

        Schema::table('successors', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->unsignedBigInteger('target_position_id')->nullable(false)->change();
        });

        Schema::table('successors', function (Blueprint $table): void {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('target_position_id')->references('id')->on('positions')->restrictOnDelete();
            $table->unique(['target_position_id', 'employee_id']);
            $table->dropIndex(['structure_position_key']);
            $table->dropColumn('structure_position_key');
        });

        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('successor_structure_positions');
            Schema::dropIfExists('successor_structure_departments');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('successor_structure_departments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('number');
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('successor_structure_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('successor_structure_departments')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('successor_structure_positions')->restrictOnDelete();
            $table->string('key', 160)->unique();
            $table->string('level', 100);
            $table->string('name');
            $table->unsignedSmallInteger('formation_count')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('align_with_grandchildren')->default(false);
            $table->timestamps();
        });

        Schema::table('successors', function (Blueprint $table): void {
            $table->string('structure_position_key', 160)->nullable()->after('id')->index();
        });

        DB::table('successors')->orderBy('id')->each(function (object $successor): void {
            DB::table('successors')->where('id', $successor->id)->update([
                'structure_position_key' => 'position.'.$successor->target_position_id,
            ]);
        });

        Schema::table('successors', function (Blueprint $table): void {
            $table->dropUnique(['target_position_id', 'employee_id']);
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['target_position_id']);
            $table->dropColumn(['employee_id', 'target_position_id']);
        });

        Schema::table('positions', function (Blueprint $table): void {
            $table->dropColumn(['job_level', 'formation_count', 'sort_order']);
        });
    }
};
