<?php

namespace Database\Factories;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vessel>
 */
class VesselFactory extends Factory
{
    protected $model = Vessel::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('VSL-###'),
            'name' => 'Training Vessel '.fake()->lastName(),
            'description' => 'Fictional vessel used for coursework.',
        ];
    }
}
