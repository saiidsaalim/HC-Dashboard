<?php

namespace Database\Factories;

use App\Models\EmployeePromotionBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployeePromotionBatch>
 */
class EmployeePromotionBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_number' => 'PRO-BATCH-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
            'name' => fake()->words(4, true),
            'description' => fake()->optional()->sentence(),
            'default_tmt' => fake()->dateTimeBetween('now', '+3 months'),
            'created_by' => User::factory(),
        ];
    }
}
