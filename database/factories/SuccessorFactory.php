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
            'job_level' => 'Manager',
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
        $employee = Employee::query()->forceCreate([
            'sap' => fake()->unique()->numerify('SAP-########'),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'education' => fake()->randomElement(['S1', 'S2', 'S3']),
            'hiring_date' => now()->subYears(fake()->numberBetween(1, 25)),
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);

        return [
            'employee_id' => $employee->id,
            'target_position_id' => $position->id,
            'name' => $employee->name,
            'position' => $employee->position,
            'talent_mapping' => fake()->randomElement(['High Potential', 'Key Contributor', 'Solid Performer']),
            'succession_readiness' => fake()->randomElement(['Ready Now', '1-2 Tahun', '3-5 Tahun']),
            'idp' => fake()->sentence(),
            'education' => $employee->education,
            'tenure' => fake()->numberBetween(1, 25).' tahun',
            'directorate' => fake()->words(2, true),
            'photo_path' => null,
        ];
    }
}
