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

        $this->seedSalesStructure();
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

    private function seedSalesStructure(): void
    {
        $department = SuccessorStructureDepartment::query()
            ->where('slug', 'sales')
            ->firstOrFail();
        $generalManager = $this->position($department->id, null, [
            'key' => 'sales.sales',
            'level' => 'General Manager',
            'name' => 'Sales',
            'formation_count' => 1,
            'sort_order' => 1,
        ]);

        $this->position($department->id, $generalManager->id, [
            'key' => 'sales.sales-excellence-specialist',
            'level' => 'Staff',
            'name' => 'Sales Excellence Specialist',
            'formation_count' => 2,
            'sort_order' => 1,
            'align_with_grandchildren' => true,
        ]);
        $salesOne = $this->position($department->id, $generalManager->id, [
            'key' => 'sales.sales-1',
            'level' => 'Senior Sales Manager',
            'name' => 'Sales 1',
            'formation_count' => 1,
            'sort_order' => 2,
        ]);
        $salesTwo = $this->position($department->id, $generalManager->id, [
            'key' => 'sales.sales-2',
            'level' => 'Senior Sales Manager',
            'name' => 'Sales 2',
            'formation_count' => 1,
            'sort_order' => 3,
        ]);
        $corporateSales = $this->position($department->id, $generalManager->id, [
            'key' => 'sales.corporate-sales',
            'level' => 'Senior Sales Manager',
            'name' => 'Corporate Sales',
            'formation_count' => 1,
            'sort_order' => 4,
        ]);
        $outboundLogistic = $this->position($department->id, $generalManager->id, [
            'key' => 'sales.outbound-logistic',
            'level' => 'Senior Manager',
            'name' => 'Outbound Logistic',
            'formation_count' => 1,
            'sort_order' => 5,
        ]);

        foreach ([
            ['sales.sulsel', 'Sulsel'],
            ['sales.sulbar-sulteng', 'Sulbar & Sulteng'],
            ['sales.sultra-sulut-gorontalo', 'Sultra, Sulut & Gorontalo'],
            ['sales.kaltim-kaltara', 'Kaltim & Kaltara'],
        ] as $index => [$key, $name]) {
            $this->position($department->id, $salesOne->id, [
                'key' => $key,
                'level' => 'Area Sales Manager',
                'name' => $name,
                'formation_count' => 1,
                'sort_order' => $index + 1,
            ]);
        }

        foreach ([
            ['sales.kalsel-ntb', 'Kalsel & NTB'],
            ['sales.papua', 'Papua'],
            ['sales.maluku-maluku-utara', 'Maluku & Maluku Utara'],
            ['sales.ntt', 'NTT'],
        ] as $index => [$key, $name]) {
            $this->position($department->id, $salesTwo->id, [
                'key' => $key,
                'level' => 'Area Sales Manager',
                'name' => $name,
                'formation_count' => 1,
                'sort_order' => $index + 1,
            ]);
        }

        $this->position($department->id, $corporateSales->id, [
            'key' => 'sales.technical-customer-support',
            'level' => 'Manager',
            'name' => 'Technical & Customer Support',
            'formation_count' => 1,
            'sort_order' => 1,
        ]);
        $this->position($department->id, $corporateSales->id, [
            'key' => 'sales.key-account-officer',
            'level' => 'Staff',
            'name' => 'Key Account Officer',
            'formation_count' => 11,
            'sort_order' => 2,
            'align_with_grandchildren' => true,
        ]);

        foreach ([
            ['sales.shipment-management', 'Shipment Management'],
            ['sales.packaging-management', 'Packaging Management'],
            ['sales.bulk-transportation', 'Bulk Transportation'],
            ['sales.land-transportation', 'Land Transportation'],
            ['sales.sea-transportation', 'Sea Transportation'],
        ] as $index => [$key, $name]) {
            $this->position($department->id, $outboundLogistic->id, [
                'key' => $key,
                'level' => 'Manager',
                'name' => $name,
                'formation_count' => 1,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
