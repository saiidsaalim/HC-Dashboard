<?php

namespace Database\Factories;

use App\Models\SuccessorStructureDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuccessorStructureDepartment>
 */
class SuccessorStructureDepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'number' => fake()->unique()->numberBetween(20, 999),
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numerify('###'),
            'sort_order' => fake()->numberBetween(20, 999),
        ];
    }
}
