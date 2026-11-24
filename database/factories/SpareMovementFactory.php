<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpareMovement>
 */
class SpareMovementFactory extends Factory
{
    protected $model = SpareMovement::class;

    public function definition(): array
    {
        return [
            'spare_item_id' => SpareItem::factory(),
            'work_order_id' => null,
            'movement_type' => MovementType::Receipt,
            'quantity_delta' => 5,
            'balance_after' => 5,
            'actor_id' => User::factory()->chiefEngineer(),
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}
