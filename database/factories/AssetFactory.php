<?php

namespace Database\Factories;

use App\Enums\Criticality;
use App\Enums\ServiceStatus;
use App\Models\Asset;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'vessel_id' => Vessel::factory(),
            'code' => fake()->unique()->bothify('AST-####'),
            'name' => fake()->words(3, true),
            'subsystem' => fake()->randomElement([
                'Propulsion', 'Transmission', 'Electrical power', 'Bilge system',
                'Fire main', 'Steering', 'Cooling', 'Compressed air',
            ]),
            'location' => fake()->randomElement(['Engine room', 'Upper deck', 'Lower deck']),
            'criticality' => fake()->randomElement(Criticality::cases()),
            'service_status' => ServiceStatus::InService,
            'has_hour_meter' => fake()->boolean(),
            'meter_unit' => 'hours',
        ];
    }

    public function hourMeter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_hour_meter' => true,
        ]);
    }
}
