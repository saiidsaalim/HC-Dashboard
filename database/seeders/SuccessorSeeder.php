<?php

namespace Database\Seeders;

use App\Models\Successor;
use Illuminate\Database\Seeder;

class SuccessorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Successor::factory()->count(3)->create();
    }
}
