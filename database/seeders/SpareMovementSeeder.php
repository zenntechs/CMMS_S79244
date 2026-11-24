<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\AuditEvent;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class SpareMovementSeeder extends Seeder
{
    public function run(): void
    {
        $spares = SpareItem::pluck('id', 'code');
        $workOrders = WorkOrder::pluck('id', 'reference');
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech1 = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $tech2 = User::where('email', 'tech2@cmms.test')->firstOrFail();

        $movements = [
            // spare, type, delta, balance, actor, work_order, reason, occurred_at
            ['SP-002', MovementType::Receipt, 8.00, 8.00, 'chief', null, 'Opening stock receipt.', '2026-10-05 09:00:00'],
            ['SP-002', MovementType::Issue, -2.00, 6.00, 'tech2', 'WO-0006', 'Issued to work order.', '2026-11-06 10:00:00'],
            ['SP-002', MovementType::Return, 1.00, 7.00, 'tech2', 'WO-0006', 'Unused part returned.', '2026-11-13 09:00:00'],
            ['SP-003', MovementType::Receipt, 200.00, 200.00, 'chief', null, 'Opening stock receipt.', '2026-10-01 09:00:00'],
            ['SP-003', MovementType::Issue, -15.00, 185.00, 'tech1', 'WO-0005', 'Issued to work order.', '2026-11-13 09:00:00'],
            ['SP-004', MovementType::Receipt, 6.00, 6.00, 'chief', null, 'Opening stock receipt.', '2026-10-01 09:00:00'],
            ['SP-004', MovementType::Issue, -2.00, 4.00, 'tech2', 'WO-0006', 'Issued to work order.', '2026-11-06 10:00:00'],
            ['SP-004', MovementType::Adjustment, -1.00, 3.00, 'chief', null, 'Damaged seal removed from stock.', '2026-11-14 09:00:00'],
            ['SP-005', MovementType::Receipt, 5.00, 5.00, 'chief', null, 'Opening stock receipt.', '2026-10-01 09:00:00'],
            ['SP-005', MovementType::Issue, -2.00, 3.00, 'tech2', 'WO-0002', 'Issued to work order.', '2026-11-21 09:00:00'],
            ['SP-001', MovementType::Issue, -2.00, 10.00, 'tech1', 'WO-0005', 'Issued to work order.', '2026-11-13 09:00:00'],
        ];

        $actors = ['chief' => $chief->id, 'tech1' => $tech1->id, 'tech2' => $tech2->id];

        $counter = 1;
        foreach ($movements as $m) {
            [$spareCode, $type, $delta, $balance, $actorKey, $woRef, $reason, $at] = $m;
            SpareMovement::firstOrCreate(
                ['id' => sprintf('01900000-0000-4000-8000-0000000007%02d', $counter)],
                [
                    'spare_item_id' => $spares[$spareCode],
                    'work_order_id' => $woRef ? $workOrders[$woRef] : null,
                    'movement_type' => $type,
                    'quantity_delta' => $delta,
                    'balance_after' => $balance,
                    'actor_id' => $actors[$actorKey],
                    'reason' => $reason,
                    'occurred_at' => $at,
                ]
            );
            $counter++;
        }

        // Audit events for stock movements.
        AuditEvent::firstOrCreate(
            ['action' => 'spare.issued', 'entity_type' => 'spare_item', 'entity_id' => $spares['SP-001'], 'occurred_at' => '2026-11-13 09:00:00'],
            ['actor_id' => $actors['tech1'], 'before' => ['quantity_on_hand' => 12.00], 'after' => ['quantity_on_hand' => 10.00], 'reason' => 'Issued to work order.']
        );
    }
}
