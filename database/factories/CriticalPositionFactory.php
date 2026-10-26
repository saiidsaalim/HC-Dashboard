<?php

namespace Database\Factories;

use App\Enums\CriticalPositionCriticality;
use App\Enums\CriticalPositionStatus;
use App\Models\CriticalPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CriticalPosition>
 */
class CriticalPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => fake()->jobTitle(),
            'level' => fake()->randomElement(['Corporate', 'Division', 'Department']),
            'department' => fake()->words(2, true),
            'directorate' => 'Direktorat '.fake()->word(),
            'criticality' => fake()->randomElement(CriticalPositionCriticality::cases()),
            'incumbent' => fake()->numerify('00######'),
            'sap' => fake()->bothify('SAP-######'),
            'status' => CriticalPositionStatus::Covered,
        ];
    }
}
