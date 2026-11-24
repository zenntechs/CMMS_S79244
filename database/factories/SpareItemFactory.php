<?php

namespace Database\Factories;

use App\Models\SpareItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpareItem>
 */
class SpareItemFactory extends Factory
{
    protected $model = SpareItem::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SP-###'),
            'description' => fake()->words(3, true),
            'unit' => 'piece',
            'quantity_on_hand' => 10,
            'reorder_level' => 2,
            'is_active' => true,
        ];
    }
}
