<?php

namespace Database\Factories;

use App\Models\SuccessorStructureDepartment;
use App\Models\SuccessorStructurePosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuccessorStructurePosition>
 */
class SuccessorStructurePositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'department_id' => SuccessorStructureDepartment::factory(),
            'parent_id' => null,
            'key' => 'test.'.str($name)->slug().'-'.fake()->unique()->numerify('###'),
            'level' => fake()->randomElement(['General Manager', 'Senior Manager', 'Staff']),
            'name' => $name,
            'formation_count' => fake()->numberBetween(1, 10),
            'sort_order' => fake()->numberBetween(1, 20),
            'align_with_grandchildren' => false,
        ];
    }
}
