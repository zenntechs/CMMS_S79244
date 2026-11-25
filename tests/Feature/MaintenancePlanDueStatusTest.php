<?php

namespace Tests\Feature;

use App\Enums\ServiceStatus;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenancePlanDueStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_latest_reading_uses_timestamp_primary_and_id_tie_break(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $asset = Asset::factory()->hourMeter()->create();

        // Creation order gives increasing UUIDv7 identifiers. The reading with the
        // newest id has the OLDEST timestamp, proving id must not be the primary key.
        $this->reading($asset, $chief, '100.00', '2026-11-01 08:00');
        $this->reading($asset, $chief, '300.00', '2026-11-01 08:00');
        $this->reading($asset, $chief, '400.00', '2026-10-01 08:00');

        $latest = $asset->fresh()->latestMeterReading;
        $this->assertNotNull($latest);
        $this->assertSame('300.00', $latest->reading_hours);

        $plan = MaintenancePlan::factory()->hours()->for($asset)->create([
            'code' => 'T91',
            'last_completion_hours' => '0.00',
            'interval_hours' => '350.00',
            'is_active' => true,
        ]);

        // 300.00 h is below the 350.00 h threshold. Selecting the 400.00 h reading
        // (largest id, oldest timestamp) would wrongly report Overdue.
        $this->actingAs($chief)
            ->get(route('plans.show', $plan))
            ->assertOk()
            ->assertSee('Not due (trigger: None)');

        $this->actingAs($chief)
            ->get(route('plans.index'))
            ->assertOk()
            ->assertSeeInOrder([$plan->code, 'Not due']);
    }

    public function test_equal_timestamps_use_id_descending_tie_break(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $asset = Asset::factory()->hourMeter()->create();

        $this->reading($asset, $chief, '100.00', '2026-11-15 08:00');
        $this->reading($asset, $chief, '300.00', '2026-11-15 08:00');

        $plan = MaintenancePlan::factory()->hours()->for($asset)->create([
            'code' => 'T92',
            'last_completion_hours' => '0.00',
            'interval_hours' => '250.00',
            'is_active' => true,
        ]);

        $this->assertSame('300.00', $asset->fresh()->latestMeterReading->reading_hours);

        $this->actingAs($chief)
            ->get(route('plans.show', $plan))
            ->assertOk()
            ->assertSee('Overdue (trigger: Running hours)');
    }

    public function test_all_roles_see_calculated_status_for_seeded_plans(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-02 09:00:00', 'Asia/Kuala_Lumpur'));

        $p01 = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $p04 = MaintenancePlan::where('code', 'P04')->firstOrFail();

        foreach (['chief@cmms.test', 'tech1@cmms.test', 'viewer@cmms.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->actingAs($user)->get(route('plans.index'))->assertOk()->assertSee('Overdue');

            $this->actingAs($user)
                ->get(route('plans.show', $p01))
                ->assertOk()
                ->assertSee('Overdue (trigger: Calendar)')
                ->assertSee('2026-12-01');

            $this->actingAs($user)
                ->get(route('plans.show', $p04))
                ->assertOk()
                ->assertSee('Not due (trigger: None)')
                ->assertSee('1450.00 h');
        }
    }

    public function test_calculation_does_not_mutate_baselines_or_create_work_orders(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $plan = MaintenancePlan::where('code', 'P01')->firstOrFail();
        $attributes = [
            'last_completion_date' => $plan->last_completion_date?->toDateString(),
            'last_completion_hours' => $plan->last_completion_hours,
            'interval_days' => $plan->interval_days,
            'interval_hours' => $plan->interval_hours,
            'trigger_type' => $plan->trigger_type->value,
        ];
        $workOrders = WorkOrder::count();

        $this->actingAs($chief)->get(route('plans.index'))->assertOk();
        $this->actingAs($chief)->get(route('plans.show', $plan))->assertOk();

        $fresh = $plan->fresh();
        $this->assertSame($attributes, [
            'last_completion_date' => $fresh->last_completion_date?->toDateString(),
            'last_completion_hours' => $fresh->last_completion_hours,
            'interval_days' => $fresh->interval_days,
            'interval_hours' => $fresh->interval_hours,
            'trigger_type' => $fresh->trigger_type->value,
        ]);
        $this->assertSame($workOrders, WorkOrder::count());
    }

    public function test_plan_list_does_not_introduce_an_n_plus_one_on_meter_readings(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($chief)->get(route('plans.index'))->assertOk();

        $readingQueries = collect(DB::getQueryLog())
            ->filter(fn (array $entry): bool => str_contains($entry['query'], 'meter_readings'));

        DB::disableQueryLog();

        $this->assertCount(1, $readingQueries);
    }

    public function test_incomplete_and_inactive_indicators_are_shown(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $asset = Asset::factory()->create(['has_hour_meter' => false]);
        $plan = MaintenancePlan::factory()->hours()->for($asset)->create([
            'code' => 'T93',
            'is_active' => false,
        ]);

        $this->actingAs($chief)
            ->get(route('plans.show', $plan))
            ->assertOk()
            ->assertSee('Running-hour check is incomplete')
            ->assertSee('Plan is inactive');
    }

    public function test_plan_on_withdrawn_asset_still_calculates_and_shows_asset_status(): void
    {
        $chief = User::where('email', 'chief@cmms.test')->firstOrFail();
        $asset = Asset::factory()->hourMeter()->create([
            'service_status' => ServiceStatus::Withdrawn,
        ]);
        $this->reading($asset, $chief, '100.00', '2026-11-15 08:00');

        $plan = MaintenancePlan::factory()->hours()->for($asset)->create([
            'code' => 'T94',
            'last_completion_hours' => '0.00',
            'interval_hours' => '50.00',
            'is_active' => true,
        ]);

        $this->actingAs($chief)
            ->get(route('plans.show', $plan))
            ->assertOk()
            ->assertSee('Withdrawn')
            ->assertSee('Overdue (trigger: Running hours)');
    }

    private function reading(Asset $asset, User $user, string $hours, string $at): MeterReading
    {
        return MeterReading::factory()->create([
            'asset_id' => $asset->id,
            'recorded_by' => $user->id,
            'reading_hours' => $hours,
            'reading_at' => CarbonImmutable::parse($at, 'Asia/Kuala_Lumpur'),
        ]);
    }
}
