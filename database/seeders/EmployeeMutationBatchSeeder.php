<?php

namespace Database\Seeders;

use App\Models\EmployeeMutationBatch;
use Illuminate\Database\Seeder;

class EmployeeMutationBatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmployeeMutationBatch::factory()->count(3)->create();
    }
}
