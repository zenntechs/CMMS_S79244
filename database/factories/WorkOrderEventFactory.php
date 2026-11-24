<?php

namespace Database\Factories;

use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderEvent>
 */
class WorkOrderEventFactory extends Factory
{
    protected $model = WorkOrderEvent::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'event_type' => WorkOrderEventType::Created,
            'from_state' => null,
            'to_state' => WorkOrderState::Open,
            'actor_id' => User::factory()->chiefEngineer(),
            'reason' => null,
            'snapshot' => null,
            'occurred_at' => now(),
        ];
    }
}
