<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\Vessel;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_vessel_has_assets(): void
    {
        $vessel = Vessel::factory()->create();
        $asset = Asset::factory()->create(['vessel_id' => $vessel->id]);

        $this->assertTrue($vessel->assets->contains($asset));
        $this->assertSame($vessel->id, $asset->vessel->id);
    }

    public function test_asset_has_maintenance_plans_and_meter_readings(): void
    {
        $asset = Asset::factory()->create();
        $plan = MaintenancePlan::factory()->create(['asset_id' => $asset->id]);

        $this->assertTrue($asset->maintenancePlans->contains($plan));
        $this->assertSame($asset->id, $plan->asset->id);
    }

    public function test_maintenance_plan_has_work_orders(): void
    {
        $plan = MaintenancePlan::factory()->create();
        $wo = WorkOrder::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($plan->workOrders->contains($wo));
        $this->assertSame($plan->id, $wo->plan->id);
    }

    public function test_spare_item_has_movements(): void
    {
        $item = SpareItem::factory()->create();
        $movement = SpareMovement::factory()->create(['spare_item_id' => $item->id]);

        $this->assertTrue($item->movements->contains($movement));
        $this->assertSame($item->id, $movement->spareItem->id);
    }

    public function test_work_order_state_cast_and_active_scope(): void
    {
        $wo = WorkOrder::factory()->create(['state' => 'open']);
        $this->assertSame('open', $wo->state->value);
        $this->assertTrue($wo->isActive());
    }
}
