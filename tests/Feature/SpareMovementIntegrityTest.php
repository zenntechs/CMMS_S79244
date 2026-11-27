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

class SpareMovementIntegrityTest extends TestCase
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

    private function tech2(): User
    {
        return User::where('email', 'tech2@cmms.test')->firstOrFail();
    }

    public function test_fresh_item_movements_reconcile_to_stored_balance(): void
    {
        $item = SpareItem::factory()->create([
            'code' => 'TST-RECON',
            'quantity_on_hand' => 0,
            'reorder_level' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($this->chief())->post(route('spares.receipt', $item), [
            'quantity' => '10.00',
            'reason' => 'Opening receipt',
        ])->assertRedirect();

        $this->actingAs($this->chief())->post(route('spares.adjustment', $item), [
            'quantity_delta' => '-1.25',
            'reason' => 'Count correction',
        ])->assertRedirect();

        $this->actingAs($this->chief())->post(route('work-orders.spares.store', $this->workOrder('WO-0002')), [
            'spare_item_id' => $item->id,
            'quantity' => '3.75',
        ])->assertRedirect();

        $item->refresh();
        $ledgerBalance = (string) $item->movements()->sum('quantity_delta');

        $this->assertSame($ledgerBalance, (string) $item->quantity_on_hand);
        $this->assertSame('5.00', (string) $item->quantity_on_hand);
    }

    public function test_decimal_quantities_are_exact(): void
    {
        $item = SpareItem::factory()->create([
            'code' => 'TST-DECIMAL',
            'quantity_on_hand' => 0,
            'reorder_level' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->chief())->post(route('spares.receipt', $item), [
            'quantity' => '0.10',
        ])->assertRedirect();
        $this->actingAs($this->chief())->post(route('spares.receipt', $item), [
            'quantity' => '0.20',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('0.30', (string) $item->quantity_on_hand);
    }

    public function test_excessive_precision_is_rejected(): void
    {
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();

        $this->actingAs($this->chief())
            ->post(route('spares.receipt', $item), ['quantity' => '1.234'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_quantity_overflow_is_rejected(): void
    {
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();

        $this->actingAs($this->chief())
            ->post(route('spares.receipt', $item), ['quantity' => '99999999999.99'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('10.00', (string) $item->refresh()->quantity_on_hand);
    }

    public function test_repeated_valid_requests_create_separate_movements(): void
    {
        $item = SpareItem::where('code', 'SP-006')->firstOrFail(); // 1.00
        $workOrder = WorkOrder::where('reference', 'WO-0002')->firstOrFail();

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '0.50',
            ])
            ->assertRedirect();

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '0.50',
            ])
            ->assertRedirect();

        $this->assertSame('0.00', (string) $item->refresh()->quantity_on_hand);
        $this->assertSame(
            2,
            SpareMovement::where('spare_item_id', $item->id)
                ->where('movement_type', 'issue')->count(),
        );
    }

    public function test_issue_is_allowed_for_submitted_work_order(): void
    {
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();
        $workOrder = WorkOrder::where('reference', 'WO-0003')->firstOrFail(); // submitted

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '1.00',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('9.00', (string) $item->quantity_on_hand);
    }

    public function test_failed_issue_leaves_no_partial_movement_or_audit(): void
    {
        $item = SpareItem::where('code', 'SP-006')->firstOrFail(); // 1.00
        $workOrder = WorkOrder::where('reference', 'WO-0002')->firstOrFail();

        $movementsBefore = SpareMovement::count();
        $auditsBefore = AuditEvent::count();

        $this->actingAs($this->chief())
            ->post(route('work-orders.spares.store', $workOrder), [
                'spare_item_id' => $item->id,
                'quantity' => '2.00',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('1.00', (string) $item->refresh()->quantity_on_hand);
        $this->assertSame($movementsBefore, SpareMovement::count());
        $this->assertSame($auditsBefore, AuditEvent::count());
    }

    public function test_master_data_edit_cannot_change_stock_balance(): void
    {
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();
        $stockBefore = (string) $item->quantity_on_hand;

        $this->actingAs($this->chief())->put(route('spares.update', $item), [
            'code' => $item->code,
            'description' => 'Updated description',
            'unit' => $item->unit,
            'reorder_level' => '9.00',
            'is_active' => '1',
            'quantity_on_hand' => '999.00',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame($stockBefore, (string) $item->quantity_on_hand);
    }

    public function test_movement_records_are_append_only(): void
    {
        $movement = SpareMovement::firstOrFail();

        try {
            $movement->update(['reason' => 'tampered']);
            $this->fail('Expected an exception when updating an append-only movement.');
        } catch (\Throwable $exception) {
            $this->assertTrue(true);
        }

        try {
            $movement->delete();
            $this->fail('Expected an exception when deleting an append-only movement.');
        } catch (\Throwable $exception) {
            $this->assertTrue(true);
        }
    }

    private function workOrder(string $reference): WorkOrder
    {
        return WorkOrder::where('reference', $reference)->firstOrFail();
    }
}
