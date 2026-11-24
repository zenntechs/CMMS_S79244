<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
{
    protected $model = MeterReading::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory()->hourMeter(),
            'reading_hours' => fake()->randomFloat(2, 100, 5000),
            'reading_at' => now(),
            'recorded_by' => User::factory(),
            'note' => null,
        ];
    }
}
