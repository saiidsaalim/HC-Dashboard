<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $departmentId = DB::table('successor_structure_departments')
            ->where('slug', 'sales')
            ->value('id');

        if ($departmentId === null) {
            return;
        }

        $generalManagerId = $this->position($departmentId, null, 'sales.sales', 'General Manager', 'Sales', 1, 1);
        $this->position($departmentId, $generalManagerId, 'sales.sales-excellence-specialist', 'Staff', 'Sales Excellence Specialist', 2, 1, true);
        $salesOneId = $this->position($departmentId, $generalManagerId, 'sales.sales-1', 'Senior Sales Manager', 'Sales 1', 1, 2);
        $salesTwoId = $this->position($departmentId, $generalManagerId, 'sales.sales-2', 'Senior Sales Manager', 'Sales 2', 1, 3);
        $corporateSalesId = $this->position($departmentId, $generalManagerId, 'sales.corporate-sales', 'Senior Sales Manager', 'Corporate Sales', 1, 4);
        $outboundLogisticId = $this->position($departmentId, $generalManagerId, 'sales.outbound-logistic', 'Senior Manager', 'Outbound Logistic', 1, 5);

        $this->position($departmentId, $salesOneId, 'sales.sulsel', 'Area Sales Manager', 'Sulsel', 1, 1);
        $this->position($departmentId, $salesOneId, 'sales.sulbar-sulteng', 'Area Sales Manager', 'Sulbar & Sulteng', 1, 2);
        $this->position($departmentId, $salesOneId, 'sales.sultra-sulut-gorontalo', 'Area Sales Manager', 'Sultra, Sulut & Gorontalo', 1, 3);
        $this->position($departmentId, $salesOneId, 'sales.kaltim-kaltara', 'Area Sales Manager', 'Kaltim & Kaltara', 1, 4);

        $this->position($departmentId, $salesTwoId, 'sales.kalsel-ntb', 'Area Sales Manager', 'Kalsel & NTB', 1, 1);
        $this->position($departmentId, $salesTwoId, 'sales.papua', 'Area Sales Manager', 'Papua', 1, 2);
        $this->position($departmentId, $salesTwoId, 'sales.maluku-maluku-utara', 'Area Sales Manager', 'Maluku & Maluku Utara', 1, 3);
        $this->position($departmentId, $salesTwoId, 'sales.ntt', 'Area Sales Manager', 'NTT', 1, 4);

        $this->position($departmentId, $corporateSalesId, 'sales.technical-customer-support', 'Manager', 'Technical & Customer Support', 1, 1);
        $this->position($departmentId, $corporateSalesId, 'sales.key-account-officer', 'Staff', 'Key Account Officer', 11, 2, true);

        $this->position($departmentId, $outboundLogisticId, 'sales.shipment-management', 'Manager', 'Shipment Management', 1, 1);
        $this->position($departmentId, $outboundLogisticId, 'sales.packaging-management', 'Manager', 'Packaging Management', 1, 2);
        $this->position($departmentId, $outboundLogisticId, 'sales.bulk-transportation', 'Manager', 'Bulk Transportation', 1, 3);
        $this->position($departmentId, $outboundLogisticId, 'sales.land-transportation', 'Manager', 'Land Transportation', 1, 4);
        $this->position($departmentId, $outboundLogisticId, 'sales.sea-transportation', 'Manager', 'Sea Transportation', 1, 5);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    private function position(
        int $departmentId,
        ?int $parentId,
        string $key,
        string $level,
        string $name,
        int $formationCount,
        int $sortOrder,
        bool $alignWithGrandchildren = false,
    ): int {
        $existingId = DB::table('successor_structure_positions')
            ->where('key', $key)
            ->value('id');
        $attributes = [
            'department_id' => $departmentId,
            'parent_id' => $parentId,
            'level' => $level,
            'name' => $name,
            'formation_count' => $formationCount,
            'sort_order' => $sortOrder,
            'align_with_grandchildren' => $alignWithGrandchildren,
            'updated_at' => now(),
        ];

        if ($existingId !== null) {
            DB::table('successor_structure_positions')
                ->where('id', $existingId)
                ->update($attributes);

            return (int) $existingId;
        }

        return DB::table('successor_structure_positions')->insertGetId([
            ...$attributes,
            'key' => $key,
            'created_at' => now(),
        ]);
    }
};
