<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\MeterReading;
use App\Models\PlanBaselineHistory;
use App\Models\SpareItem;
use App\Models\SpareMovement;
use App\Models\User;
use App\Models\Vessel;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seed_counts_match_specification(): void
    {
        $this->assertSame(1, Vessel::count());
        $this->assertSame(10, Asset::count());
        $this->assertSame(8, MaintenancePlan::count());
        $this->assertSame(6, WorkOrder::count());
        $this->assertSame(6, SpareItem::count());
        $this->assertSame(5, User::count());
    }

    public function test_plan_trigger_distribution(): void
    {
        $this->assertSame(3, MaintenancePlan::where('trigger_type', 'calendar')->count());
        $this->assertSame(3, MaintenancePlan::where('trigger_type', 'hours')->count());
        $this->assertSame(2, MaintenancePlan::where('trigger_type', 'dual')->count());
    }

    public function test_work_order_state_distribution(): void
    {
        $this->assertSame(1, WorkOrder::where('state', 'open')->count());
        $this->assertSame(1, WorkOrder::where('state', 'in_progress')->count());
        $this->assertSame(2, WorkOrder::where('state', 'submitted')->count());
        $this->assertSame(2, WorkOrder::where('state', 'closed')->count());
    }

    public function test_assets_cover_at_least_three_subsystems(): void
    {
        $this->assertGreaterThanOrEqual(3, Asset::distinct('subsystem')->count('subsystem'));
        $this->assertSame(8, Asset::distinct('subsystem')->count('subsystem'));
    }

    public function test_supporting_records_exist(): void
    {
        $this->assertGreaterThan(0, MeterReading::count());
        $this->assertGreaterThan(0, WorkOrderEvent::count());
        $this->assertSame(2, PlanBaselineHistory::count());
        $this->assertGreaterThan(0, SpareMovement::count());
        $this->assertGreaterThan(0, AuditEvent::count());
    }

    public function test_seed_is_repeatable(): void
    {
        $before = [
            Vessel::count(), Asset::count(), MaintenancePlan::count(),
            WorkOrder::count(), SpareItem::count(), SpareMovement::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $after = [
            Vessel::count(), Asset::count(), MaintenancePlan::count(),
            WorkOrder::count(), SpareItem::count(), SpareMovement::count(),
        ];

        $this->assertSame($before, $after);
    }

    public function test_no_duplicate_active_work_orders_per_plan(): void
    {
        $activeCount = WorkOrder::whereIn('state', ['open', 'in_progress', 'submitted'])
            ->select('plan_id')
            ->groupBy('plan_id')
            ->havingRaw('count(*) > 1')
            ->count();

        $this->assertSame(0, $activeCount);
    }
}
