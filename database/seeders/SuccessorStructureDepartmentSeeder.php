<?php

namespace Database\Seeders;

use App\Models\SuccessorStructureDepartment;
use Illuminate\Database\Seeder;

class SuccessorStructureDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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

        foreach ($departments as $department) {
            SuccessorStructureDepartment::query()->firstOrCreate(
                ['slug' => $department[2]],
                [
                    'number' => $department[0],
                    'name' => $department[1],
                    'sort_order' => $department[0],
                ],
            );
        }
    }
}
