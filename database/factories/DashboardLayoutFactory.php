<?php

namespace Database\Factories;

use App\Models\DashboardLayout;
use App\Models\User;
use App\Services\DashboardLayoutService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardLayout>
 */
class DashboardLayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace' => DashboardLayoutService::Workspace,
            'slots' => ['compact-1' => null, 'compact-2' => null, 'main-1' => null, 'main-2' => null, 'right-1' => null, 'right-2' => null],
        ];
    }
}
