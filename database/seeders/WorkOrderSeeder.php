<?php

namespace Database\Seeders;

use App\Enums\WorkOrderState;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\PlanBaselineHistory;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech1 = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $tech2 = User::where('email', 'tech2@cmms.test')->firstOrFail();

        $plans = MaintenancePlan::pluck('id', 'code');
        $users = ['chief' => $chief->id, 'tech1' => $tech1->id, 'tech2' => $tech2->id];

        $workOrders = [
            // code, plan, state, assignee, created_by, started_at, submitted_at, closed_at, actual_date, actual_hours, notes
            ['WO-0001', 'P07', WorkOrderState::Open, 'tech1', 'chief', null, null, null, null, null, null],
            ['WO-0002', 'P02', WorkOrderState::InProgress, 'tech2', 'chief', '2026-11-20 09:00:00', null, null, null, null, null],
            ['WO-0003', 'P04', WorkOrderState::Submitted, 'tech1', 'chief', '2026-11-15 09:00:00', '2026-11-20 16:00:00', null, '2026-11-20', 1420.00, 'Completed inspection and filter change.'],
            ['WO-0004', 'P08', WorkOrderState::Submitted, 'tech2', 'chief', '2026-11-18 09:00:00', '2026-11-22 15:00:00', null, '2026-11-22', 2050.00, 'Cooling pump serviced.'],
            ['WO-0005', 'P03', WorkOrderState::Closed, 'tech1', 'chief', '2026-11-12 09:00:00', '2026-11-17 16:00:00', '2026-11-18 10:00:00', '2026-11-18', 0, 'Steering unit serviced and tested.'],
            ['WO-0006', 'P05', WorkOrderState::Closed, 'tech2', 'chief', '2026-11-05 09:00:00', '2026-11-11 16:00:00', '2026-11-12 10:00:00', '2026-11-12', 4100.00, 'Gearbox inspected and oil changed.'],
        ];

        $i = 1;
        foreach ($workOrders as $wo) {
            [$ref, $planCode, $state, $assigneeKey, $creatorKey, $started, $submitted, $closed, $actualDate, $actualHours, $notes] = $wo;

            WorkOrder::updateOrCreate(
                ['reference' => $ref],
                [
                    'id' => sprintf('01900000-0000-4000-8000-0000000004%02d', $i),
                    'plan_id' => $plans[$planCode],
                    'state' => $state,
                    'assigned_to' => $users[$assigneeKey],
                    'created_by' => $users[$creatorKey],
                    'started_at' => $started,
                    'submitted_at' => $submitted,
                    'closed_at' => $closed,
                    'actual_completion_date' => $actualDate,
                    'actual_completion_hours' => $actualHours,
                    'completion_notes' => $notes,
                    'evidence_ref' => $notes ? 'EVD-'.$ref : null,
                    'approved_by' => $state === WorkOrderState::Closed ? $users['chief'] : null,
                ]
            );
            $i++;
        }

        // Work order events + audit + baseline history for closed orders.
        $eventCounter = 1;
        $auditCounter = 0;

        $addEvent = function (string $woRef, string $type, ?string $from, ?string $to, string $actorKey, ?string $reason = null, ?array $snapshot = null, string $at = '2026-11-10 10:00:00') use (&$eventCounter, $users) {
            $woId = WorkOrder::where('reference', $woRef)->value('id');
            WorkOrderEvent::firstOrCreate(
                ['id' => sprintf('01900000-0000-4000-8000-0000000005%02d', $eventCounter)],
                [
                    'work_order_id' => $woId,
                    'event_type' => $type,
                    'from_state' => $from,
                    'to_state' => $to,
                    'actor_id' => $users[$actorKey],
                    'reason' => $reason,
                    'snapshot' => $snapshot,
                    'occurred_at' => $at,
                ]
            );
            $eventCounter++;
        };

        $addAudit = function (string $action, string $entityType, string $entityId, ?string $actorKey, ?array $before, ?array $after, ?string $reason, string $at) use (&$auditCounter, $users) {
            AuditEvent::firstOrCreate(
                ['action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId, 'occurred_at' => $at],
                [
                    'actor_id' => $actorKey ? $users[$actorKey] : null,
                    'before' => $before,
                    'after' => $after,
                    'reason' => $reason,
                ]
            );
        };

        // WO-0001 (open): created
        $addEvent('WO-0001', 'created', null, 'open', 'chief', null, null, '2026-11-24 09:00:00');

        // WO-0002 (in progress): created, started
        $addEvent('WO-0002', 'created', null, 'open', 'chief', null, null, '2026-11-19 09:00:00');
        $addEvent('WO-0002', 'started', 'open', 'in_progress', 'tech2', null, null, '2026-11-20 09:00:00');

        // WO-0003 (submitted): created, started, submitted (first), returned, submitted (final)
        $addEvent('WO-0003', 'created', null, 'open', 'chief', null, null, '2026-11-14 09:00:00');
        $addEvent('WO-0003', 'started', 'open', 'in_progress', 'tech1', null, null, '2026-11-15 09:00:00');
        $addEvent('WO-0003', 'submitted', 'in_progress', 'submitted', 'tech1', null, ['actual_completion_hours' => 1380.00], '2026-11-16 16:00:00');
        $addEvent('WO-0003', 'returned', 'submitted', 'in_progress', 'chief', 'Re-check filter torque values.', null, '2026-11-17 10:00:00');
        $addEvent('WO-0003', 'submitted', 'in_progress', 'submitted', 'tech1', null, ['actual_completion_hours' => 1420.00], '2026-11-20 16:00:00');

        // WO-0004 (submitted)
        $addEvent('WO-0004', 'created', null, 'open', 'chief', null, null, '2026-11-17 09:00:00');
        $addEvent('WO-0004', 'started', 'open', 'in_progress', 'tech2', null, null, '2026-11-18 09:00:00');
        $addEvent('WO-0004', 'submitted', 'in_progress', 'submitted', 'tech2', null, ['actual_completion_hours' => 2050.00], '2026-11-22 15:00:00');

        // WO-0005 (closed)
        $addEvent('WO-0005', 'created', null, 'open', 'chief', null, null, '2026-11-10 09:00:00');
        $addEvent('WO-0005', 'started', 'open', 'in_progress', 'tech1', null, null, '2026-11-12 09:00:00');
        $addEvent('WO-0005', 'submitted', 'in_progress', 'submitted', 'tech1', null, ['actual_completion_date' => '2026-11-18'], '2026-11-17 16:00:00');
        $addEvent('WO-0005', 'closed', 'submitted', 'closed', 'chief', 'Approved completion.', null, '2026-11-18 10:00:00');

        // WO-0006 (closed)
        $addEvent('WO-0006', 'created', null, 'open', 'chief', null, null, '2026-11-04 09:00:00');
        $addEvent('WO-0006', 'started', 'open', 'in_progress', 'tech2', null, null, '2026-11-05 09:00:00');
        $addEvent('WO-0006', 'submitted', 'in_progress', 'submitted', 'tech2', null, ['actual_completion_hours' => 4100.00], '2026-11-11 16:00:00');
        $addEvent('WO-0006', 'closed', 'submitted', 'closed', 'chief', 'Approved completion.', null, '2026-11-12 10:00:00');

        // Baseline history for closed orders.
        $p03 = $plans['P03'];
        $p05 = $plans['P05'];
        $wo5 = WorkOrder::where('reference', 'WO-0005')->first();
        $wo6 = WorkOrder::where('reference', 'WO-0006')->first();

        PlanBaselineHistory::firstOrCreate(
            ['plan_id' => $p03, 'work_order_id' => $wo5->id, 'changed_at' => '2026-11-18 10:00:00+00'],
            [
                'previous_date' => '2026-09-01',
                'new_date' => '2026-11-18',
                'previous_hours' => null,
                'new_hours' => null,
                'changed_by' => $users['chief'],
            ]
        );

        PlanBaselineHistory::firstOrCreate(
            ['plan_id' => $p05, 'work_order_id' => $wo6->id, 'changed_at' => '2026-11-12 10:00:00+00'],
            [
                'previous_date' => null,
                'new_date' => null,
                'previous_hours' => 4000.00,
                'new_hours' => 4100.00,
                'changed_by' => $users['chief'],
            ]
        );

        // Advance plan baselines for closed orders.
        MaintenancePlan::where('id', $p03)->update([
            'last_completion_date' => '2026-11-18',
            'last_completion_work_order_id' => $wo5->id,
        ]);
        MaintenancePlan::where('id', $p05)->update([
            'last_completion_hours' => 4100.00,
            'last_completion_work_order_id' => $wo6->id,
        ]);

        // Audit events for closed orders.
        $addAudit('work_order.closed', 'work_order', $wo5->id, 'chief', ['state' => 'submitted'], ['state' => 'closed'], 'Approved completion.', '2026-11-18 10:00:00');
        $addAudit('plan.baseline_advanced', 'maintenance_plan', $p03, 'chief', ['last_completion_date' => '2026-09-01'], ['last_completion_date' => '2026-11-18'], 'Approved completion.', '2026-11-18 10:00:00');
        $addAudit('work_order.closed', 'work_order', $wo6->id, 'chief', ['state' => 'submitted'], ['state' => 'closed'], 'Approved completion.', '2026-11-12 10:00:00');
        $addAudit('plan.baseline_advanced', 'maintenance_plan', $p05, 'chief', ['last_completion_hours' => 4000.00], ['last_completion_hours' => 4100.00], 'Approved completion.', '2026-11-12 10:00:00');
    }
}
