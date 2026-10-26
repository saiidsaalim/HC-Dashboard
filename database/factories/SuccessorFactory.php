<?php

namespace Database\Factories;

use App\Models\Successor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Successor>
 */
class SuccessorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'structure_position_key' => 'market-planning-development.general-manager',
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'talent_mapping' => fake()->randomElement(['High Potential', 'Key Contributor', 'Solid Performer']),
            'succession_readiness' => fake()->randomElement(['Ready Now', '1-2 Tahun', '3-5 Tahun']),
            'idp' => fake()->sentence(),
            'education' => fake()->randomElement(['S1', 'S2', 'S3']),
            'tenure' => fake()->numberBetween(1, 25).' tahun',
            'directorate' => fake()->words(2, true),
            'photo_path' => null,
        ];
    }
}
