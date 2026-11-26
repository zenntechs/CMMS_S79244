<?php

namespace Database\Factories;

use App\Enums\PlanTrigger;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'code' => fake()->unique()->bothify('PLN-####'),
            'task_description' => fake()->sentence(),
            'trigger_type' => PlanTrigger::Calendar,
            'interval_days' => 30,
            'interval_hours' => null,
            'last_completion_date' => now()->subDays(10)->toDateString(),
            'last_completion_hours' => null,
            'last_completion_work_order_id' => null,
            'source_label' => 'Synthetic teaching scenario',
            'is_active' => false,
        ];
    }

    public function calendar(): static
    {
        return $this->state(fn (array $a): array => [
            'trigger_type' => PlanTrigger::Calendar,
            'interval_days' => 30,
            'interval_hours' => null,
            'last_completion_date' => now()->subDays(10)->toDateString(),
            'last_completion_hours' => null,
        ]);
    }

    public function hours(): static
    {
        return $this->state(fn (array $a): array => [
            'trigger_type' => PlanTrigger::Hours,
            'interval_days' => null,
            'interval_hours' => 250.00,
            'last_completion_date' => null,
            'last_completion_hours' => 1000.00,
        ]);
    }

    public function dual(): static
    {
        return $this->state(fn (array $a): array => [
            'trigger_type' => PlanTrigger::Dual,
            'interval_days' => 30,
            'interval_hours' => 250.00,
            'last_completion_date' => now()->subDays(10)->toDateString(),
            'last_completion_hours' => 1000.00,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $a): array => ['is_active' => true]);
    }
}
