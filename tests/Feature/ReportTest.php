<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MaintenancePlan;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DueStatusCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', 'Asia/Kuala_Lumpur'));
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

    public function test_guest_is_redirected_from_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect('/login');
        $this->get(route('reports.maintenance'))->assertRedirect('/login');
    }

    public function test_inactive_user_is_redirected_from_reports(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Viewer,
            'is_active' => false,
        ]);

        $this->actingAs($user)->get(route('reports.index'))->assertRedirect('/login');
    }

    public function test_reports_index_is_available_to_authenticated_roles(): void
    {
        $this->actingAs($this->chief())->get(route('reports.index'))->assertOk();
        $this->actingAs($this->tech1())->get(route('reports.index'))->assertOk();
        $this->actingAs($this->viewer())->get(route('reports.index'))->assertOk();
    }

    public function test_maintenance_report_matches_due_status_calculator(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.maintenance', [
            'status' => 'overdue',
        ]));

        $response->assertOk();

        $expected = MaintenancePlan::where('is_active', true)->with('asset.latestMeterReading')->get()
            ->filter(function ($plan) {
                $status = (new DueStatusCalculator)->calculateForPlan(
                    $plan,
                    $plan->asset->latestMeterReading ? (string) $plan->asset->latestMeterReading->reading_hours : null,
                    CarbonImmutable::parse('2026-11-25', 'Asia/Kuala_Lumpur'),
                );

                return $status->state->value === 'overdue';
            })->count();

        $this->assertSame($expected, $response->viewData('rows')->total());
    }

    public function test_maintenance_report_filters_are_applied(): void
    {
        $plan = MaintenancePlan::where('code', 'P04')->firstOrFail();

        $response = $this->actingAs($this->chief())->get(route('reports.maintenance', [
            'trigger_type' => 'hours',
            'asset_id' => $plan->asset_id,
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('rows')->total());
        $this->assertSame($plan->code, $response->viewData('rows')->first()['plan']->code);
    }

    public function test_maintenance_report_rejects_invalid_filters(): void
    {
        $this->actingAs($this->chief())
            ->get(route('reports.maintenance', ['status' => 'invalid']))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->chief())
            ->get(route('reports.maintenance', ['due_from' => '2026-11-25', 'due_to' => '2026-11-20']))
            ->assertRedirect();
    }

    public function test_work_order_report_is_scoped_for_technicians(): void
    {
        $response = $this->actingAs($this->tech1())->get(route('reports.work-orders'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('workOrders')->total());

        foreach ($response->viewData('workOrders') as $workOrder) {
            $this->assertSame($this->tech1()->id, $workOrder->assigned_to);
        }
    }

    public function test_work_order_report_filters_and_date_boundaries(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.work-orders', [
            'state' => 'submitted',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
        ]));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('workOrders')->total());

        $this->actingAs($this->chief())
            ->get(route('reports.work-orders', [
                'date_from' => '2026-11-30',
                'date_to' => '2026-11-01',
            ]))
            ->assertRedirect();
    }

    public function test_work_order_report_rejects_invalid_identifiers(): void
    {
        $this->actingAs($this->chief())
            ->get(route('reports.work-orders', ['asset_id' => 'not-a-uuid']))
            ->assertSessionHasErrors('asset_id');
    }

    public function test_work_order_report_paginates(): void
    {
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $chief = $this->chief();
        $tech = $this->tech1();

        WorkOrder::factory()->closed()->count(25)->create([
            'plan_id' => $plan->id,
            'assigned_to' => $tech->id,
            'created_by' => $chief->id,
        ]);

        $response = $this->actingAs($chief)->get(route('reports.work-orders'));
        $response->assertOk();
        $this->assertSame(20, $response->viewData('workOrders')->perPage());
        $this->assertSame(31, $response->viewData('workOrders')->total());
    }

    public function test_spare_inventory_report_filters(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.spares', [
            'status' => 'low',
        ]));

        $response->assertOk();
        $codes = $response->viewData('items')->pluck('code')->all();

        $this->assertContains('SP-005', $codes);
        $this->assertContains('SP-006', $codes);
        $this->assertNotContains('SP-001', $codes);
    }

    public function test_spare_movement_report_filters_and_technician_visibility(): void
    {
        $response = $this->actingAs($this->tech2())->get(route('reports.movements', [
            'movement_type' => 'issue',
        ]));

        $response->assertOk();

        foreach ($response->viewData('movements') as $movement) {
            $this->assertSame($this->tech2()->id, $movement->workOrder->assigned_to);
        }

        $response = $this->actingAs($this->chief())->get(route('reports.movements', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'movement_type' => 'issue',
        ]));

        $response->assertOk();
        $this->assertGreaterThan(0, $response->viewData('movements')->total());
    }

    public function test_spare_movement_report_rejects_invalid_filters(): void
    {
        $this->actingAs($this->chief())
            ->get(route('reports.movements', ['movement_type' => 'invalid']))
            ->assertSessionHasErrors('movement_type');

        $this->actingAs($this->chief())
            ->get(route('reports.movements', [
                'date_from' => '2026-11-30',
                'date_to' => '2026-11-01',
            ]))
            ->assertRedirect();
    }

    public function test_reports_are_read_only(): void
    {
        $workOrderCount = WorkOrder::count();
        $movementCount = SpareMovement::count();

        $this->actingAs($this->chief())->get(route('reports.work-orders'))->assertOk();
        $this->actingAs($this->chief())->get(route('reports.movements'))->assertOk();

        $this->assertSame($workOrderCount, WorkOrder::count());
        $this->assertSame($movementCount, SpareMovement::count());
    }

    public function test_empty_report_states_render(): void
    {
        $response = $this->actingAs($this->chief())->get(route('reports.spares', [
            'search' => 'ZZZ-NO-MATCH',
        ]));

        $response->assertOk();
        $response->assertSee('No spare items match the selected filters.');
    }

    public function test_mixed_unit_dashboard_metric_uses_item_count(): void
    {
        $response = $this->actingAs($this->chief())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('active item types');
        $response->assertDontSee('total units');
    }
}
