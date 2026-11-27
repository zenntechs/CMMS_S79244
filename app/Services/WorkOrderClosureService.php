<?php

namespace App\Services;

use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\PlanBaselineHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderClosureService
{
    public function __construct(private readonly WorkOrderLifecycleService $lifecycle) {}

    public function close(string $workOrderId, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($workOrderId, $actor): WorkOrder {
            $workOrder = WorkOrder::query()
                ->with('plan.asset')
                ->lockForUpdate()
                ->findOrFail($workOrderId);

            if (! $actor->isChiefEngineer()) {
                throw new AuthorizationException('Only the Chief Engineer may close a work order.');
            }

            if ($workOrder->state !== WorkOrderState::Submitted) {
                throw ValidationException::withMessages([
                    'work_order' => 'Only a submitted work order can be closed.',
                ]);
            }

            $plan = MaintenancePlan::query()
                ->lockForUpdate()
                ->findOrFail($workOrder->plan_id);

            $completion = $this->lifecycle->validatedCompletionForClosure($plan, $workOrder);

            $previousDate = $plan->last_completion_date?->toDateString();
            $previousHours = $plan->last_completion_hours !== null
                ? (string) $plan->last_completion_hours
                : null;

            $newDate = $plan->last_completion_date?->toDateString();
            $newHours = $previousHours;

            if ($plan->usesCalendar()) {
                $newDate = $completion['actual_completion_date'];
            }

            if ($plan->usesHours()) {
                $newHours = $completion['actual_completion_hours'];
            }

            $workOrder->forceFill([
                'state' => WorkOrderState::Closed,
                'closed_at' => now(),
                'approved_by' => $actor->id,
                'actual_completion_date' => $completion['actual_completion_date'],
                'actual_completion_hours' => $completion['actual_completion_hours'],
                'completion_notes' => $completion['completion_notes'],
                'evidence_ref' => $completion['evidence_ref'],
            ])->save();

            $plan->forceFill([
                'last_completion_date' => $newDate,
                'last_completion_hours' => $newHours,
                'last_completion_work_order_id' => $workOrder->id,
            ])->save();

            PlanBaselineHistory::create([
                'plan_id' => $plan->id,
                'work_order_id' => $workOrder->id,
                'previous_date' => $previousDate,
                'new_date' => $plan->usesCalendar() ? $newDate : null,
                'previous_hours' => $previousHours,
                'new_hours' => $plan->usesHours() ? $newHours : null,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            WorkOrderEvent::create([
                'work_order_id' => $workOrder->id,
                'event_type' => WorkOrderEventType::Closed,
                'from_state' => WorkOrderState::Submitted,
                'to_state' => WorkOrderState::Closed,
                'actor_id' => $actor->id,
                'reason' => null,
                'snapshot' => [
                    'actual_completion_date' => $completion['actual_completion_date'],
                    'actual_completion_hours' => $completion['actual_completion_hours'],
                    'closed_at' => $workOrder->closed_at?->toIso8601String(),
                    'approved_by' => $actor->id,
                ],
                'occurred_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'work_order.closed',
                'entity_type' => 'work_order',
                'entity_id' => $workOrder->id,
                'before' => [
                    'state' => WorkOrderState::Submitted->value,
                    'plan_last_completion_date' => $previousDate,
                    'plan_last_completion_hours' => $previousHours,
                ],
                'after' => [
                    'state' => WorkOrderState::Closed->value,
                    'plan_last_completion_date' => $plan->usesCalendar() ? $newDate : $previousDate,
                    'plan_last_completion_hours' => $plan->usesHours() ? $newHours : $previousHours,
                    'last_completion_work_order_id' => $plan->last_completion_work_order_id,
                ],
                'reason' => null,
            ]);

            return $workOrder->refresh();
        });
    }
}
