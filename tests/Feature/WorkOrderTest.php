<?php

namespace Tests\Feature;

use App\Enums\WorkOrderEventType;
use App\Enums\WorkOrderState;
use App\Models\Asset;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', 'Asia/Kuala_Lumpur'));
    }

    public function test_registry_lists_seeded_work_orders_for_chief_engineer(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();

        $this->actingAs($chief)
            ->get(route('work-orders.index'))
            ->assertOk()
            ->assertSee('WO-0001')
            ->assertSee('WO-0006');
    }

    public function test_guest_is_redirected_from_registry(): void
    {
        $this->get(route('work-orders.index'))->assertRedirect('/login');
    }

    public function test_viewer_can_view_registry_read_only(): void
    {
        $viewer = User::where('email', 'viewer@cmms.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('work-orders.index'))
            ->assertOk();
    }

    public function test_technician_sees_only_assigned_work_orders(): void
    {
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $response = $this->actingAs($tech)->get(route('work-orders.index'));
        $response->assertOk();

        // tech1 is assigned to WO-0001 and WO-0003.
        $response->assertSee('WO-0001');
        $response->assertDontSee('WO-0002'); // assigned to tech2
    }

    public function test_chief_engineer_can_create_and_assign_a_work_order(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        // P01 is inactive by seed; activate it first via the model to make it eligible.
        $plan->update(['is_active' => true]);

        $before = WorkOrder::count();

        $this->actingAs($chief)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertRedirect();

        $this->assertSame($before + 1, WorkOrder::count());

        $wo = WorkOrder::where('plan_id', $plan->id)->firstOrFail();
        $this->assertSame('open', $wo->state->value);
        $this->assertSame($tech->id, $wo->assigned_to);
        $this->assertSame($chief->id, $wo->created_by);

        $event = WorkOrderEvent::where('work_order_id', $wo->id)
            ->where('event_type', WorkOrderEventType::Created)->sole();
        $this->assertSame($chief->id, $event->actor_id);
        $this->assertSame(WorkOrderState::Open, $event->to_state);

        $audit = AuditEvent::where('action', 'work_order.created')
            ->where('entity_id', $wo->id)->sole();
        $this->assertSame($chief->id, $audit->actor_id);
        $this->assertSame($tech->id, $audit->after['assigned_to']);
        $this->assertSame('open', $audit->after['state']);
    }

    public function test_chief_engineer_can_open_the_create_form(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();

        $this->actingAs($chief)
            ->get(route('work-orders.create'))
            ->assertOk()
            ->assertSee('New work order');
    }

    public function test_viewer_cannot_open_the_create_form(): void
    {
        $viewer = User::where('email', 'viewer@cmms.test')->firstOrFail();

        $this->actingAs($viewer)->get(route('work-orders.create'))->assertForbidden();
    }

    public function test_technician_cannot_open_the_create_form(): void
    {
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($tech)->get(route('work-orders.create'))->assertForbidden();
    }

    public function test_viewer_can_view_work_order_detail(): void
    {
        $viewer = User::where('email', 'viewer@cmms.test')->firstOrFail();
        $workOrder = WorkOrder::where('reference', 'WO-0001')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('work-orders.show', $workOrder))
            ->assertOk()
            ->assertSee('WO-0001');
    }

    public function test_technician_cannot_view_an_unassigned_work_order_detail(): void
    {
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $workOrder = WorkOrder::where('reference', 'WO-0002')->firstOrFail(); // assigned to tech2

        $this->actingAs($tech)
            ->get(route('work-orders.show', $workOrder))
            ->assertForbidden();
    }

    public function test_registry_supports_search_and_state_filtering(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();

        $this->actingAs($chief)
            ->get(route('work-orders.index', ['search' => 'WO-0002']))
            ->assertOk()
            ->assertSee('WO-0002')
            ->assertDontSee('WO-0001');

        $this->actingAs($chief)
            ->get(route('work-orders.index', ['state' => 'closed']))
            ->assertOk()
            ->assertSee('WO-0005')
            ->assertSee('WO-0006')
            ->assertDontSee('WO-0001');
    }

    public function test_registry_paginates_at_twenty_per_page(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();

        WorkOrder::factory()->closed()->count(15)->create(['plan_id' => $plan->id]);

        $first = $this->actingAs($chief)->get(route('work-orders.index'));
        $first->assertOk();
        $this->assertSame(20, $first->viewData('workOrders')->perPage());
        $this->assertSame(21, $first->viewData('workOrders')->total());
        $this->assertSame(2, $first->viewData('workOrders')->lastPage());

        $second = $this->actingAs($chief)->get(route('work-orders.index', ['page' => 2]));
        $second->assertOk();
        $this->assertCount(1, $second->viewData('workOrders')->items());
    }

    public function test_duplicate_active_work_order_is_prevented(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P07')->firstOrFail(); // already has an open WO

        $before = WorkOrder::count();

        $this->actingAs($chief)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertSessionHasErrors('plan_id');

        $this->assertSame($before, WorkOrder::count());
    }

    public function test_inactive_plan_cannot_receive_a_work_order(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $plan->update(['is_active' => false]);

        $before = WorkOrder::count();

        $this->actingAs($chief)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertSessionHasErrors('plan_id');

        $this->assertSame($before, WorkOrder::count());
    }

    public function test_plan_asset_mismatch_is_rejected(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P02')->firstOrFail();
        $otherAsset = Asset::where('code', 'A02')->firstOrFail();
        $plan->update(['is_active' => true]);

        $before = WorkOrder::count();

        $this->actingAs($chief)->post(route('work-orders.store'), [
            'asset_id' => $otherAsset->id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertSessionHasErrors('plan_id');

        $this->assertSame($before, WorkOrder::count());
    }

    public function test_inactive_technician_cannot_be_assigned(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $tech->update(['is_active' => false]);
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $plan->update(['is_active' => true]);

        $before = WorkOrder::count();

        $this->actingAs($chief)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->assertSame($before, WorkOrder::count());
    }

    public function test_viewer_cannot_create_work_order_via_direct_post(): void
    {
        $viewer = User::where('email', 'viewer@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $plan->update(['is_active' => true]);
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($viewer)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertForbidden();
    }

    public function test_technician_cannot_create_work_order_via_direct_post(): void
    {
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $plan->update(['is_active' => true]);

        $this->actingAs($tech)->post(route('work-orders.store'), [
            'asset_id' => $plan->asset_id,
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
        ])->assertForbidden();
    }
}
