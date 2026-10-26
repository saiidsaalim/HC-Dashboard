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
        Schema::create('successor_structure_positions', function (Blueprint $table) {
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

        $departmentId = DB::table('successor_structure_departments')
            ->where('slug', 'market-planning-development')
            ->value('id');

        if ($departmentId === null) {
            return;
        }

        $now = now();
        $generalManagerId = DB::table('successor_structure_positions')->insertGetId([
            'department_id' => $departmentId,
            'parent_id' => null,
            'key' => 'market-planning-development.general-manager',
            'level' => 'General Manager',
            'name' => 'Market Planning & Development',
            'formation_count' => 1,
            'sort_order' => 1,
            'align_with_grandchildren' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productManagerId = DB::table('successor_structure_positions')->insertGetId([
            'department_id' => $departmentId,
            'parent_id' => $generalManagerId,
            'key' => 'market-planning-development.product-pricing-promotion-manager',
            'level' => 'Senior Manager',
            'name' => 'Product, Pricing & Promotion Management',
            'formation_count' => 1,
            'sort_order' => 1,
            'align_with_grandchildren' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $marketManagerId = DB::table('successor_structure_positions')->insertGetId([
            'department_id' => $departmentId,
            'parent_id' => $generalManagerId,
            'key' => 'market-planning-development.market-intelligence-channel-manager',
            'level' => 'Senior Manager',
            'name' => 'Market Intelligence, Channel & Sales Force Management',
            'formation_count' => 1,
            'sort_order' => 2,
            'align_with_grandchildren' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('successor_structure_positions')->insert([
            $this->position($departmentId, $productManagerId, 'market-planning-development.pricing-policy-revenue-staff', 'Staff of Pricing Policy & Revenue Management', 3, 1, $now),
            $this->position($departmentId, $productManagerId, 'market-planning-development.trade-marketing-promotion-staff', 'Staff of Trade Marketing & Promotion Management', 4, 2, $now),
            $this->position($departmentId, $productManagerId, 'market-planning-development.product-advocacy-staff', 'Staff of Product Advocacy', 1, 3, $now),
            $this->position($departmentId, $marketManagerId, 'market-planning-development.market-intelligence-planning-staff', 'Staff of Market Intelligence, Sales Planning & Evaluation', 4, 1, $now),
            $this->position($departmentId, $marketManagerId, 'market-planning-development.channel-sales-force-staff', 'Staff of Channel & Sales Force Management', 3, 2, $now),
            $this->position($departmentId, $generalManagerId, 'market-planning-development.sales-process-staff', 'Staff of Sales Process Management', 1, 3, $now, true),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('successor_structure_positions');
    }

    /** @return array<string, mixed> */
    private function position(
        int $departmentId,
        int $parentId,
        string $key,
        string $name,
        int $formationCount,
        int $sortOrder,
        mixed $now,
        bool $alignWithGrandchildren = false,
    ): array {
        return [
            'department_id' => $departmentId,
            'parent_id' => $parentId,
            'key' => $key,
            'level' => 'Staff',
            'name' => $name,
            'formation_count' => $formationCount,
            'sort_order' => $sortOrder,
            'align_with_grandchildren' => $alignWithGrandchildren,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};
