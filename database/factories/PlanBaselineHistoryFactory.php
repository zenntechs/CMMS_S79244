<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\PlanBaselineHistory;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanBaselineHistory>
 */
class PlanBaselineHistoryFactory extends Factory
{
    protected $model = PlanBaselineHistory::class;

    public function definition(): array
    {
        return [
            'plan_id' => MaintenancePlan::factory(),
            'work_order_id' => WorkOrder::factory(),
            'previous_date' => now()->subDays(30)->toDateString(),
            'new_date' => now()->toDateString(),
            'previous_hours' => 1000.00,
            'new_hours' => 1250.00,
            'changed_by' => User::factory()->chiefEngineer(),
            'changed_at' => now(),
        ];
    }
}
