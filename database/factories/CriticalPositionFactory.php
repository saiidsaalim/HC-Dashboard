<?php

namespace Database\Factories;

use App\Enums\CriticalPositionCriticality;
use App\Models\CriticalPosition;
use App\Models\Department;
use App\Models\Position;
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
            'position_id' => fn (): int => $this->createPosition()->id,
            'criticality' => fake()->randomElement(CriticalPositionCriticality::cases()),
            'assessment_reason' => fake()->sentence(),
            'assessed_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'review_due_at' => fake()->optional()->dateTimeBetween('now', '+1 year')?->format('Y-m-d'),
            'assessed_by' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    private function createPosition(): Position
    {
        $department = Department::query()->create([
            'code' => fake()->unique()->bothify('DPT-########'),
            'name' => fake()->words(2, true),
            'active' => true,
        ]);
        $unit = $department->units()->create([
            'code' => fake()->unique()->bothify('UNT-########'),
            'name' => fake()->words(2, true),
            'active' => true,
        ]);

        return $unit->positions()->create([
            'code' => fake()->unique()->bothify('POS-########'),
            'external_position_id' => fake()->optional()->numerify('########'),
            'name' => fake()->jobTitle(),
            'job_level' => Position::JOB_LEVEL_STAFF,
            'formation_count' => 1,
            'active' => true,
        ]);
    }
}
