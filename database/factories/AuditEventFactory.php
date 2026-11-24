<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->chiefEngineer(),
            'action' => 'work_order.closed',
            'entity_type' => 'work_order',
            'entity_id' => '00000000-0000-0000-0000-000000000000',
            'before' => null,
            'after' => null,
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}
