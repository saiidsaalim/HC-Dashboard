<?php

namespace Database\Seeders;

use App\Models\SuccessorStructureDepartment;
use App\Models\SuccessorStructurePosition;
use Illuminate\Database\Seeder;

class SuccessorStructurePositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $department = SuccessorStructureDepartment::query()
            ->where('slug', 'market-planning-development')
            ->firstOrFail();

        $generalManager = $this->position($department->id, null, [
            'key' => 'market-planning-development.general-manager',
            'level' => 'General Manager',
            'name' => 'Market Planning & Development',
            'formation_count' => 1,
            'sort_order' => 1,
        ]);
        $productManager = $this->position($department->id, $generalManager->id, [
            'key' => 'market-planning-development.product-pricing-promotion-manager',
            'level' => 'Senior Manager',
            'name' => 'Product, Pricing & Promotion Management',
            'formation_count' => 1,
            'sort_order' => 1,
        ]);
        $marketManager = $this->position($department->id, $generalManager->id, [
            'key' => 'market-planning-development.market-intelligence-channel-manager',
            'level' => 'Senior Manager',
            'name' => 'Market Intelligence, Channel & Sales Force Management',
            'formation_count' => 1,
            'sort_order' => 2,
        ]);

        $this->position($department->id, $productManager->id, [
            'key' => 'market-planning-development.pricing-policy-revenue-staff',
            'level' => 'Staff',
            'name' => 'Staff of Pricing Policy & Revenue Management',
            'formation_count' => 3,
            'sort_order' => 1,
        ]);
        $this->position($department->id, $productManager->id, [
            'key' => 'market-planning-development.trade-marketing-promotion-staff',
            'level' => 'Staff',
            'name' => 'Staff of Trade Marketing & Promotion Management',
            'formation_count' => 4,
            'sort_order' => 2,
        ]);
        $this->position($department->id, $productManager->id, [
            'key' => 'market-planning-development.product-advocacy-staff',
            'level' => 'Staff',
            'name' => 'Staff of Product Advocacy',
            'formation_count' => 1,
            'sort_order' => 3,
        ]);
        $this->position($department->id, $marketManager->id, [
            'key' => 'market-planning-development.market-intelligence-planning-staff',
            'level' => 'Staff',
            'name' => 'Staff of Market Intelligence, Sales Planning & Evaluation',
            'formation_count' => 4,
            'sort_order' => 1,
        ]);
        $this->position($department->id, $marketManager->id, [
            'key' => 'market-planning-development.channel-sales-force-staff',
            'level' => 'Staff',
            'name' => 'Staff of Channel & Sales Force Management',
            'formation_count' => 3,
            'sort_order' => 2,
        ]);
        $this->position($department->id, $generalManager->id, [
            'key' => 'market-planning-development.sales-process-staff',
            'level' => 'Staff',
            'name' => 'Staff of Sales Process Management',
            'formation_count' => 1,
            'sort_order' => 3,
            'align_with_grandchildren' => true,
        ]);
    }

    /** @param array{key: string, level: string, name: string, formation_count: int, sort_order: int, align_with_grandchildren?: bool} $attributes */
    private function position(int $departmentId, ?int $parentId, array $attributes): SuccessorStructurePosition
    {
        return SuccessorStructurePosition::query()->firstOrCreate(
            ['key' => $attributes['key']],
            [
                'department_id' => $departmentId,
                'parent_id' => $parentId,
                'level' => $attributes['level'],
                'name' => $attributes['name'],
                'formation_count' => $attributes['formation_count'],
                'sort_order' => $attributes['sort_order'],
                'align_with_grandchildren' => $attributes['align_with_grandchildren'] ?? false,
            ],
        );
    }
}
