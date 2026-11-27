<?php

namespace App\Services;

use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function create(MaintenancePlan $plan, User $actor, User $assignee): WorkOrder
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => 'Work orders can only be created for active maintenance plans.',
            ]);
        }

        if ($plan->workOrders()->active()->exists()) {
            throw ValidationException::withMessages([
                'plan_id' => 'An active work order already exists for this plan.',
            ]);
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(function () use ($plan, $actor, $assignee): WorkOrder {
                    $workOrder = WorkOrder::create([
                        'reference' => $this->nextReference(),
                        'plan_id' => $plan->id,
                        'state' => WorkOrderState::Open,
                        'assigned_to' => $assignee->id,
                        'created_by' => $actor->id,
                    ]);

                    WorkOrderEvent::create([
                        'work_order_id' => $workOrder->id,
                        'event_type' => WorkOrderEventType::Created,
                        'from_state' => null,
                        'to_state' => WorkOrderState::Open,
                        'actor_id' => $actor->id,
                        'reason' => null,
                        'snapshot' => null,
                        'occurred_at' => now(),
                    ]);

                    AuditEvent::create([
                        'actor_id' => $actor->id,
                        'action' => 'work_order.created',
                        'entity_type' => 'work_order',
                        'entity_id' => $workOrder->id,
                        'before' => null,
                        'after' => [
                            'reference' => $workOrder->reference,
                            'plan_id' => $plan->id,
                            'asset_id' => $plan->asset_id,
                            'state' => $workOrder->state->value,
                            'assigned_to' => $assignee->id,
                        ],
                        'reason' => null,
                    ]);

                    return $workOrder;
                });
            } catch (QueryException $exception) {
                if ($this->violates($exception, 'one_active_work_order_per_plan')) {
                    throw ValidationException::withMessages([
                        'plan_id' => 'An active work order already exists for this plan.',
                    ]);
                }

                if ($this->violates($exception, 'work_orders_reference_unique')) {
                    if ($attempt < 3) {
                        continue;
                    }

                    throw ValidationException::withMessages([
                        'plan_id' => 'A unique work-order reference could not be allocated. Please try again.',
                    ]);
                }

                throw $exception;
            }
        }
    }

    private function violates(QueryException $exception, string $constraint): bool
    {
        return str_contains($exception->getMessage(), $constraint);
    }

    private function nextReference(): string
    {
        $max = WorkOrder::query()
            ->where('reference', 'like', 'WO-%')
            ->pluck('reference')
            ->map(fn (string $ref): int => (int) preg_replace('/\D/', '', $ref))
            ->max() ?? 0;

        return 'WO-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
