<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MaintenancePlan;
use App\Models\PlanBaselineHistory;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DueStatusCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function viewer(): User
    {
        return User::where('email', 'viewer@cmms.test')->firstOrFail();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
    }

    public function test_inactive_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Viewer,
            'is_active' => false,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect('/login');
    }

    public function test_chief_engineer_dashboard_shows_all_counts(): void
    {
        $response = $this->actingAs($this->chief())->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(10, $response->viewData('assetCount'));
        $this->assertSame(8, $response->viewData('planCount'));
        $this->assertSame(6, $response->viewData('workOrderCounts')['open'] + $response->viewData('workOrderCounts')['in_progress'] + $response->viewData('workOrderCounts')['submitted'] + $response->viewData('workOrderCounts')['closed']);
        $this->assertSame(6, $response->viewData('spareItemCount'));
    }

    public function test_technician_dashboard_is_scoped_to_assigned_work_orders(): void
    {
        $response = $this->actingAs($this->tech1())->get(route('dashboard'));

        $response->assertOk();
        $counts = $response->viewData('workOrderCounts');

        $this->assertSame(3, $counts['open'] + $counts['in_progress'] + $counts['submitted'] + $counts['closed']);
        $this->assertSame(2, $counts['active']);
    }

    public function test_viewer_dashboard_is_read_only_and_shows_all_counts(): void
    {
        $response = $this->actingAs($this->viewer())->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(6, $response->viewData('workOrderCounts')['open'] + $response->viewData('workOrderCounts')['in_progress'] + $response->viewData('workOrderCounts')['submitted'] + $response->viewData('workOrderCounts')['closed']);
        $response->assertSee('Assets');
        $response->assertDontSee('New work order');
    }

    public function test_maintenance_counts_match_due_status_calculator(): void
    {
        $plans = MaintenancePlan::where('is_active', true)->with('asset.latestMeterReading')->get();
        $calculator = new DueStatusCalculator;
        $today = CarbonImmutable::parse('2026-11-25', 'Asia/Kuala_Lumpur');

        $expected = [
            'not_due' => 0,
            'due' => 0,
            'overdue' => 0,
            'incomplete' => 0,
        ];

        foreach ($plans as $plan) {
            $reading = $plan->asset->latestMeterReading;
            $status = $calculator->calculateForPlan(
                $plan,
                $reading ? (string) $reading->reading_hours : null,
                $today,
            );

            $expected[$status->state->value === 'not_due' ? 'not_due' : $status->state->value]++;
        }

        $response = $this->actingAs($this->chief())->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame($expected, $response->viewData('maintenanceCounts'));
    }

    public function test_inventory_low_stock_count_matches_reorder_threshold(): void
    {
        $expected = SpareItem::where('is_active', true)
            ->whereColumn('quantity_on_hand', '<', 'reorder_level')
            ->count();

        $response = $this->actingAs($this->chief())->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame($expected, $response->viewData('lowStockCount'));
    }

    public function test_dashboard_does_not_mutate_data(): void
    {
        $workOrderCount = WorkOrder::count();
        $movementCount = SpareMovement::count();
        $baselineCount = PlanBaselineHistory::count();

        $this->actingAs($this->chief())->get(route('dashboard'))->assertOk();

        $this->assertSame($workOrderCount, WorkOrder::count());
        $this->assertSame($movementCount, SpareMovement::count());
        $this->assertSame($baselineCount, PlanBaselineHistory::count());
    }
}
