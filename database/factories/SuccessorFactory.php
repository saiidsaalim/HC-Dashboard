<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Successor;
use App\Models\Unit;
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
        $department = Department::query()->create([
            'code' => fake()->unique()->bothify('DEPT-####'),
            'name' => fake()->unique()->company(),
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => fake()->unique()->bothify('UNIT-####'),
            'name' => fake()->unique()->companySuffix(),
            'active' => true,
        ]);
        $position = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => fake()->unique()->bothify('POS-####'),
            'name' => fake()->jobTitle(),
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);

        return [
            'target_position_id' => $position->id,
            'name' => fake()->name(),
            'sap' => fake()->unique()->numerify('######'),
            'current_position' => fake()->jobTitle(),
            'position_history' => [[
                'period' => '2023 - 2025',
                'position' => fake()->jobTitle(),
            ]],
            'birth_date' => fake()->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'last_education' => fake()->randomElement(['S1', 'S2', 'S3']),
            'talent_box' => fake()->numberBetween(1, 9),
            'succession_readiness' => fake()->randomElement(Employee::SUCCESSION_READINESS_OPTIONS),
            'recommendation' => fake()->sentence(),
            'targeted_band' => fake()->bothify('Band ##'),
            'performance_year' => now()->year,
            'performance_score' => fake()->randomFloat(2, 0, 100),
            'performance_category' => fake()->randomElement(['Baik', 'Sangat Baik']),
            'mbti_type' => fake()->randomElement(['ENTJ', 'INTJ', 'ENFP']),
            'mbti_description' => fake()->sentence(),
            'top_job_family_1' => fake()->words(2, true),
            'top_job_family_2' => fake()->words(2, true),
            'photo_path' => null,
        ];
    }
}
