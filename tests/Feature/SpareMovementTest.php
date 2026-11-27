<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpareMovementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function chief(): User
    {
        return User::where('email', 'chief@cmms.test')->firstOrFail();
    }

    private function tech1(): User
    {
        return User::where('email', 'tech1@cmms.test')->firstOrFail();
    }

    private function tech2(): User
    {
        return User::where('email', 'tech2@cmms.test')->firstOrFail();
    }

    private function viewer(): User
    {
        return User::where('email', 'viewer@cmms.test')->firstOrFail();
    }

    private function item(string $code): SpareItem
    {
        return SpareItem::where('code', $code)->firstOrFail();
    }

    private function workOrder(string $reference): WorkOrder
    {
        return WorkOrder::where('reference', $reference)->firstOrFail();
    }

    public function test_chief_engineer_can_receive_stock(): void
    {
        $item = $this->item('SP-001');
        $before = (string) $item->quantity_on_hand;

        $this->actingAs($this->chief())
            ->post(route('spares.receipt', $item), [
                'quantity' => '2.50',
                'reason' => 'Opening receipt',
            ])
            ->assertRedirect(route('spares.show', $item));

        $item->refresh();
        $this->assertSame('12.50', (string) $item->quantity_on_hand);

        $movement = SpareMovement::where('spare_item_id', $item->id)
            ->where('movement_type', 'receipt')->latest('occurred_at')->firstOrFail();
        $this->assertSame('2.50', (string) $movement->quantity_delta);
        $this->assertSame('12.50', (string) $movement->balance_after);
        $this->assertNull($movement->work_order_id);

        $this->assertTrue(AuditEvent::where('action', 'spare.received')
            ->where('entity_id', $item->id)->exists());
    }

    public function test_chief_engineer_can_adjust_stock_positive_and_negative(): void
    {
        $item = $this->item('SP-004');

        $this->actingAs($this->chief())
            ->post(route('spares.adjustment', $item), [
                'quantity_delta' => '-1.00',
                'reason' => 'Damaged stock removed',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('2.00', (string) $item->quantity_on_hand);

        $this->actingAs($this->chief())
            ->post(route('spares.adjustment', $item), [
                'quantity_delta' => '0.50',
                'reason' => 'Stock count correction',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('2.50', (string) $item->quantity_on_hand);
    }

    public function test_adjustment_rejects_zero_and_negative_balance(): void
    {
        $item = $this->item('SP-006');

        $this->actingAs($this->chief())
            ->post(route('spares.adjustment', $item), [
                'quantity_delta' => '0.00',
                'reason' => 'No change',
            ])
            ->assertSessionHasErrors('quantity_delta');

        $this->actingAs($this->chief())
            ->post(route('spares.adjustment', $item), [
                'quantity_delta' => '-2.00',
                'reason' => 'Too much',
            ])
            ->assertSessionHasErrors('quantity_delta');

        $this->assertSame('1.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_assigned_technician_can_issue_stock_to_active_work_order(): void
    {
        $item = $this->item('SP-002');
        $workOrder = $this->workOrder('WO-0002'); // assigned to tech2, in progress
        $before = (string) $item->quantity_on_hand;

        $this->actingAs($this->tech2())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '2.00',
                'reason' => 'Issued during repair',
            ])
            ->assertRedirect(route('work-orders.show', $workOrder));

        $item->refresh();
        $this->assertSame('5.00', (string) $item->quantity_on_hand);

        $movement = SpareMovement::where('work_order_id', $workOrder->id)
            ->where('spare_item_id', $item->id)
            ->where('movement_type', 'issue')->sole();
        $this->assertSame('-2.00', (string) $movement->quantity_delta);
        $this->assertSame('5.00', (string) $movement->balance_after);

        $this->assertTrue(AuditEvent::where('action', 'spare.issued')
            ->where('entity_id', $workOrder->id)->exists());
    }

    public function test_chief_engineer_can_issue_stock(): void
    {
        $item = $this->item('SP-001');
        $workOrder = $this->workOrder('WO-0001');

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('9.00', (string) $item->quantity_on_hand);
    }

    public function test_issue_rejects_insufficient_stock(): void
    {
        $item = $this->item('SP-006'); // 1.00 on hand
        $workOrder = $this->workOrder('WO-0002');

        $movementsBefore = SpareMovement::count();
        $auditsBefore = AuditEvent::count();

        $this->actingAs($this->tech2())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '2.00',
            ])
            ->assertSessionHasErrors('quantity');

        $item->refresh();
        $this->assertSame('1.00', (string) $item->quantity_on_hand);
        $this->assertSame($movementsBefore, SpareMovement::count());
        $this->assertSame($auditsBefore, AuditEvent::count());
    }

    public function test_issue_exact_remaining_stock_is_allowed(): void
    {
        $item = $this->item('SP-006');

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $this->workOrder('WO-0002')), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('0.00', (string) $item->quantity_on_hand);
    }

    public function test_issue_rejects_invalid_quantities(): void
    {
        $item = $this->item('SP-001');
        $workOrder = $this->workOrder('WO-0002');

        foreach (['0', '-1', '1.234'] as $quantity) {
            $this->actingAs($this->chief())
                ->post(route('work-orders.spares.store', $workOrder), [
                    'spare_item_id' => $item->id,
                    'quantity' => $quantity,
                ])
                ->assertSessionHasErrors('quantity');
        }

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_inactive_item_cannot_be_issued(): void
    {
        $item = $this->item('SP-001');
        $item->update(['is_active' => false]);
        $workOrder = $this->workOrder('WO-0002');

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertSessionHasErrors('spare_item_id');

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_issue_is_rejected_for_closed_work_order(): void
    {
        $item = $this->item('SP-001');
        $workOrder = $this->workOrder('WO-0005'); // closed

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertForbidden();

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_unassigned_technician_cannot_issue_stock(): void
    {
        $item = $this->item('SP-001');
        $workOrder = $this->workOrder('WO-0002'); // assigned to tech2

        $this->actingAs($this->tech1())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertForbidden();

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_viewer_cannot_receive_adjust_or_issue_stock(): void
    {
        $item = $this->item('SP-001');
        $workOrder = $this->workOrder('WO-0002');

        $this->actingAs($this->viewer())
            ->post(route('spares.receipt', $item), ['quantity' => '1.00'])
            ->assertForbidden();

        $this->actingAs($this->viewer())
            ->post(route('spares.adjustment', $item), ['quantity_delta' => '1.00', 'reason' => 'Not allowed'])
            ->assertForbidden();

        $this->actingAs($this->viewer())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_stock_operations(): void
    {
        $item = $this->item('SP-001');
        $workOrder = $this->workOrder('WO-0002');

        $this->post(route('spares.receipt', $item), ['quantity' => '1.00'])->assertRedirect('/login');
        $this->post(route('work-orders.spares.store', $workOrder), [
            'spare_item_id' => $item->id,
            'quantity' => '1.00',
        ])->assertRedirect('/login');
    }

    public function test_stock_movements_do_not_change_work_order_lifecycle_or_baselines(): void
    {
        $item = $this->item('SP-002');
        $workOrder = $this->workOrder('WO-0002');
        $plan = $workOrder->plan;

        $baselineDate = $plan->last_completion_date?->toDateString();
        $baselineHours = $plan->last_completion_hours !== null ? (string) $plan->last_completion_hours : null;
        $stateBefore = $workOrder->state;
        $closedBefore = $workOrder->closed_at;

        $this->actingAs($this->tech2())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $plan->refresh();

        $this->assertSame($stateBefore, $workOrder->state);
        $this->assertEquals($closedBefore, $workOrder->closed_at);
        $this->assertSame($baselineDate, $plan->last_completion_date?->toDateString());
        $this->assertSame($baselineHours, $plan->last_completion_hours !== null ? (string) $plan->last_completion_hours : null);
    }

    public function test_receipt_rejects_inactive_items(): void
    {
        $item = $this->item('SP-001');
        $item->update(['is_active' => false]);

        $this->actingAs($this->chief())
            ->post(route('spares.receipt', $item), ['quantity' => '1.00'])
            ->assertSessionHasErrors('spare_item_id');

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }
}
