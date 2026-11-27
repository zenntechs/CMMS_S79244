<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\AuditEvent;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpareMovementService
{
    public function receipt(SpareItem $spareItem, string $quantity, User $actor, ?string $reason = null): SpareMovement
    {
        return DB::transaction(function () use ($spareItem, $quantity, $actor, $reason): SpareMovement {
            $item = SpareItem::query()->lockForUpdate()->findOrFail($spareItem->id);

            if (! $item->is_active) {
                throw ValidationException::withMessages([
                    'spare_item_id' => 'Stock cannot be received for an inactive spare item.',
                ]);
            }

            $delta = bcadd($quantity, '0', 2);

            if (bccomp($delta, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Receipt quantity must be greater than zero.',
                ]);
            }

            $balance = bcadd((string) $item->quantity_on_hand, $delta, 2);

            $item->forceFill(['quantity_on_hand' => $balance])->save();

            $movement = SpareMovement::create([
                'spare_item_id' => $item->id,
                'work_order_id' => null,
                'movement_type' => MovementType::Receipt,
                'quantity_delta' => $delta,
                'balance_after' => $balance,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'spare.received',
                'entity_type' => 'spare_item',
                'entity_id' => $item->id,
                'before' => ['quantity_on_hand' => (string) $item->getOriginal('quantity_on_hand')],
                'after' => ['quantity_on_hand' => $balance],
                'reason' => $reason,
            ]);

            return $movement;
        });
    }

    public function adjust(SpareItem $spareItem, string $quantityDelta, User $actor, string $reason): SpareMovement
    {
        return DB::transaction(function () use ($spareItem, $quantityDelta, $actor, $reason): SpareMovement {
            $item = SpareItem::query()->lockForUpdate()->findOrFail($spareItem->id);

            if (! $item->is_active) {
                throw ValidationException::withMessages([
                    'spare_item_id' => 'Stock cannot be adjusted for an inactive spare item.',
                ]);
            }

            $delta = bcadd($quantityDelta, '0', 2);

            if (bccomp($delta, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => 'Adjustment quantity must not be zero.',
                ]);
            }

            $reason = trim($reason);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required for a stock adjustment.',
                ]);
            }

            $balance = bcadd((string) $item->quantity_on_hand, $delta, 2);

            if (bccomp($balance, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => 'Adjustment would result in negative stock.',
                ]);
            }

            $item->forceFill(['quantity_on_hand' => $balance])->save();

            $movement = SpareMovement::create([
                'spare_item_id' => $item->id,
                'work_order_id' => null,
                'movement_type' => MovementType::Adjustment,
                'quantity_delta' => $delta,
                'balance_after' => $balance,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'spare.adjusted',
                'entity_type' => 'spare_item',
                'entity_id' => $item->id,
                'before' => ['quantity_on_hand' => (string) $item->getOriginal('quantity_on_hand')],
                'after' => ['quantity_on_hand' => $balance],
                'reason' => $reason,
            ]);

            return $movement;
        });
    }

    public function issue(SpareItem $spareItem, WorkOrder $workOrder, string $quantity, User $actor, ?string $reason = null): SpareMovement
    {
        return DB::transaction(function () use ($spareItem, $workOrder, $quantity, $actor, $reason): SpareMovement {
            $lockedWorkOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);

            if (! $lockedWorkOrder->state->isActive()) {
                throw ValidationException::withMessages([
                    'work_order' => 'Spare parts can only be issued to an active work order.',
                ]);
            }

            if (! $actor->isChiefEngineer() && $lockedWorkOrder->assigned_to !== $actor->id) {
                throw new AuthorizationException('Only the assigned technician or the Chief Engineer may issue spares to this work order.');
            }

            $item = SpareItem::query()->lockForUpdate()->findOrFail($spareItem->id);

            if (! $item->is_active) {
                throw ValidationException::withMessages([
                    'spare_item_id' => 'Inactive spare items cannot be issued.',
                ]);
            }

            $qty = bcadd($quantity, '0', 2);

            if (bccomp($qty, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Issue quantity must be greater than zero.',
                ]);
            }

            $delta = bcsub('0', $qty, 2);
            $balance = bcadd((string) $item->quantity_on_hand, $delta, 2);

            if (bccomp($balance, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock for this issue.',
                ]);
            }

            $item->forceFill(['quantity_on_hand' => $balance])->save();

            $movement = SpareMovement::create([
                'spare_item_id' => $item->id,
                'work_order_id' => $lockedWorkOrder->id,
                'movement_type' => MovementType::Issue,
                'quantity_delta' => $delta,
                'balance_after' => $balance,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'spare.issued',
                'entity_type' => 'work_order',
                'entity_id' => $lockedWorkOrder->id,
                'before' => ['quantity_on_hand' => (string) $item->getOriginal('quantity_on_hand')],
                'after' => ['quantity_on_hand' => $balance, 'spare_item_id' => $item->id],
                'reason' => $reason,
            ]);

            return $movement;
        });
    }
}
