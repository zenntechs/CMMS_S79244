<?php

namespace App\Services;

use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderLifecycleService
{
    public function start(string $workOrderId, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($workOrderId, $actor): WorkOrder {
            $workOrder = $this->lockWorkOrder($workOrderId);

            if ($workOrder->state !== WorkOrderState::Open) {
                throw ValidationException::withMessages([
                    'work_order' => 'Only an open work order can be started.',
                ]);
            }

            $this->assertActorMayWork($workOrder, $actor);

            $startedAt = now();
            $workOrder->forceFill([
                'state' => WorkOrderState::InProgress,
                'started_at' => $workOrder->started_at ?? $startedAt,
            ])->save();

            $this->recordEvent(
                workOrder: $workOrder,
                actor: $actor,
                type: WorkOrderEventType::Started,
                from: WorkOrderState::Open,
                to: WorkOrderState::InProgress,
            );

            $this->recordAudit(
                workOrder: $workOrder,
                actor: $actor,
                action: 'work_order.started',
                before: ['state' => WorkOrderState::Open->value],
                after: [
                    'state' => WorkOrderState::InProgress->value,
                    'started_at' => $workOrder->started_at?->toIso8601String(),
                ],
            );

            return $workOrder->refresh();
        });
    }

    /**
     * @param  array{actual_completion_date: string, actual_completion_hours?: string|int|float|null, completion_notes: string, evidence_ref?: string|null}  $data
     */
    public function submit(string $workOrderId, User $actor, array $data): WorkOrder
    {
        return DB::transaction(function () use ($workOrderId, $actor, $data): WorkOrder {
            $workOrder = $this->lockWorkOrder($workOrderId);

            if ($workOrder->state !== WorkOrderState::InProgress) {
                throw ValidationException::withMessages([
                    'work_order' => 'Only an in-progress work order can be submitted for approval.',
                ]);
            }

            $this->assertActorMayWork($workOrder, $actor);

            $completion = $this->validatedCompletion($workOrder->plan, $data);

            $workOrder->forceFill([
                'state' => WorkOrderState::Submitted,
                'submitted_at' => now(),
                'actual_completion_date' => $completion['actual_completion_date'],
                'actual_completion_hours' => $completion['actual_completion_hours'],
                'completion_notes' => $completion['completion_notes'],
                'evidence_ref' => $completion['evidence_ref'],
            ])->save();

            $snapshot = [
                'actual_completion_date' => $completion['actual_completion_date'],
                'actual_completion_hours' => $completion['actual_completion_hours'],
                'completion_notes' => $completion['completion_notes'],
                'evidence_ref' => $completion['evidence_ref'],
                'submitted_at' => $workOrder->submitted_at?->toIso8601String(),
            ];

            $this->recordEvent(
                workOrder: $workOrder,
                actor: $actor,
                type: WorkOrderEventType::Submitted,
                from: WorkOrderState::InProgress,
                to: WorkOrderState::Submitted,
                snapshot: $snapshot,
            );

            $this->recordAudit(
                workOrder: $workOrder,
                actor: $actor,
                action: 'work_order.submitted',
                before: ['state' => WorkOrderState::InProgress->value],
                after: ['state' => WorkOrderState::Submitted->value] + $snapshot,
            );

            return $workOrder->refresh();
        });
    }

    public function returnToTechnician(string $workOrderId, User $actor, string $reason): WorkOrder
    {
        return DB::transaction(function () use ($workOrderId, $actor, $reason): WorkOrder {
            $workOrder = $this->lockWorkOrder($workOrderId);

            if (! $actor->isChiefEngineer()) {
                throw new AuthorizationException('Only the Chief Engineer may return submitted work.');
            }

            if ($workOrder->state !== WorkOrderState::Submitted) {
                throw ValidationException::withMessages([
                    'work_order' => 'Only a submitted work order can be returned.',
                ]);
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'reason' => 'A return reason of at least 5 characters is required.',
                ]);
            }

            $snapshot = [
                'actual_completion_date' => $workOrder->actual_completion_date?->toDateString(),
                'actual_completion_hours' => $workOrder->actual_completion_hours !== null
                    ? (string) $workOrder->actual_completion_hours
                    : null,
                'completion_notes' => $workOrder->completion_notes,
                'evidence_ref' => $workOrder->evidence_ref,
                'submitted_at' => $workOrder->submitted_at?->toIso8601String(),
            ];

            $workOrder->forceFill(['state' => WorkOrderState::InProgress])->save();

            $this->recordEvent(
                workOrder: $workOrder,
                actor: $actor,
                type: WorkOrderEventType::Returned,
                from: WorkOrderState::Submitted,
                to: WorkOrderState::InProgress,
                reason: $reason,
                snapshot: $snapshot,
            );

            $this->recordAudit(
                workOrder: $workOrder,
                actor: $actor,
                action: 'work_order.returned',
                before: ['state' => WorkOrderState::Submitted->value] + $snapshot,
                after: ['state' => WorkOrderState::InProgress->value],
                reason: $reason,
            );

            return $workOrder->refresh();
        });
    }

    private function lockWorkOrder(string $workOrderId): WorkOrder
    {
        return WorkOrder::query()
            ->with('plan.asset')
            ->lockForUpdate()
            ->findOrFail($workOrderId);
    }

    private function assertActorMayWork(WorkOrder $workOrder, User $actor): void
    {
        if ($actor->isChiefEngineer()) {
            return;
        }

        if ($actor->isTechnician() && $workOrder->assigned_to === $actor->id) {
            return;
        }

        throw new AuthorizationException('Only the assigned technician or the Chief Engineer may perform this action.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{actual_completion_date: string, actual_completion_hours: string|null, completion_notes: string, evidence_ref: string|null}
     */
    public function validatedCompletionForClosure(MaintenancePlan $plan, WorkOrder $workOrder): array
    {
        return $this->validatedCompletion($plan, [
            'actual_completion_date' => $workOrder->actual_completion_date?->toDateString(),
            'actual_completion_hours' => $workOrder->actual_completion_hours !== null
                ? (string) $workOrder->actual_completion_hours
                : null,
            'completion_notes' => $workOrder->completion_notes,
            'evidence_ref' => $workOrder->evidence_ref,
        ]);
    }

    private function validatedCompletion(MaintenancePlan $plan, array $data): array
    {
        $dateInput = trim((string) ($data['actual_completion_date'] ?? ''));
        $today = CarbonImmutable::now('Asia/Kuala_Lumpur')->startOfDay();

        if ($dateInput === '') {
            throw ValidationException::withMessages([
                'actual_completion_date' => 'The actual completion date is required.',
            ]);
        }

        try {
            $completionDate = CarbonImmutable::parse($dateInput, 'Asia/Kuala_Lumpur')->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'actual_completion_date' => 'The actual completion date is not a valid date.',
            ]);
        }

        if ($completionDate->gt($today)) {
            throw ValidationException::withMessages([
                'actual_completion_date' => 'The actual completion date cannot be in the future.',
            ]);
        }

        if ($plan->usesCalendar()
            && $plan->last_completion_date !== null
            && $completionDate->lt($plan->last_completion_date->startOfDay())) {
            throw ValidationException::withMessages([
                'actual_completion_date' => 'The actual completion date cannot be earlier than the plan baseline.',
            ]);
        }

        $hoursInput = $data['actual_completion_hours'] ?? null;
        $hours = null;

        if ($plan->usesHours()) {
            if ($hoursInput === null || $hoursInput === '') {
                throw ValidationException::withMessages([
                    'actual_completion_hours' => 'Actual completion running hours are required for this plan.',
                ]);
            }

            $hours = number_format((float) $hoursInput, 2, '.', '');

            if (bccomp($hours, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'actual_completion_hours' => 'Actual completion hours cannot be negative.',
                ]);
            }

            if ($plan->last_completion_hours !== null
                && bccomp($hours, (string) $plan->last_completion_hours, 2) < 0) {
                throw ValidationException::withMessages([
                    'actual_completion_hours' => 'Actual completion hours cannot be lower than the plan baseline.',
                ]);
            }
        }

        $notes = trim((string) ($data['completion_notes'] ?? ''));

        if (mb_strlen($notes) < 10) {
            throw ValidationException::withMessages([
                'completion_notes' => 'Completion notes must describe the work performed (at least 10 characters).',
            ]);
        }

        $evidence = $data['evidence_ref'] ?? null;
        $evidence = is_string($evidence) ? trim($evidence) : null;

        return [
            'actual_completion_date' => $completionDate->toDateString(),
            'actual_completion_hours' => $hours,
            'completion_notes' => $notes,
            'evidence_ref' => $evidence === '' ? null : $evidence,
        ];
    }

    private function recordEvent(
        WorkOrder $workOrder,
        User $actor,
        WorkOrderEventType $type,
        ?WorkOrderState $from,
        ?WorkOrderState $to,
        ?string $reason = null,
        ?array $snapshot = null,
    ): void {
        WorkOrderEvent::create([
            'work_order_id' => $workOrder->id,
            'event_type' => $type,
            'from_state' => $from,
            'to_state' => $to,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'snapshot' => $snapshot,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
        WorkOrder $workOrder,
        User $actor,
        string $action,
        array $before,
        array $after,
        ?string $reason = null,
    ): void {
        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }
}
