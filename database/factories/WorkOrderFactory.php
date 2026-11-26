<?php

namespace Database\Factories;

use App\Enums\WorkOrderState;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'reference' => fake()->unique()->bothify('TST-WO-####'),
            'plan_id' => MaintenancePlan::factory(),
            'state' => WorkOrderState::Open,
            'assigned_to' => null,
            'created_by' => User::factory()->chiefEngineer(),
            'started_at' => null,
            'submitted_at' => null,
            'closed_at' => null,
            'actual_completion_date' => null,
            'actual_completion_hours' => null,
            'completion_notes' => null,
            'evidence_ref' => null,
            'approved_by' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $a): array => ['state' => WorkOrderState::Open]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $a): array => [
            'state' => WorkOrderState::InProgress,
            'started_at' => now(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $a): array => [
            'state' => WorkOrderState::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now(),
            'actual_completion_date' => now()->toDateString(),
            'actual_completion_hours' => 1500.00,
            'completion_notes' => 'Completed during inspection.',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $a): array => [
            'state' => WorkOrderState::Closed,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDay(),
            'closed_at' => now(),
            'actual_completion_date' => now()->subDay()->toDateString(),
            'actual_completion_hours' => 1500.00,
            'completion_notes' => 'Closed by chief engineer.',
            'approved_by' => User::factory()->chiefEngineer(),
        ]);
    }
}
