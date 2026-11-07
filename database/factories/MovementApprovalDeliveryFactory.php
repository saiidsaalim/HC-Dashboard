<?php

namespace Database\Factories;

use App\Models\MovementApprovalDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovementApprovalDelivery>
 */
class MovementApprovalDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movement_type' => 'mutation',
            'movement_id' => 1,
            'approval_round' => 1,
            'step_order' => 1,
            'recipient_id' => User::factory(),
            'status' => 'pending',
        ];
    }
}
