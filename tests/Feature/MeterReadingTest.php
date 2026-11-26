<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditEvent;
use App\Models\MaintenancePlan;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MeterReadingService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MeterReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', 'Asia/Kuala_Lumpur'));
    }

    public function test_chief_and_technician_can_record_a_reading_with_attribution_and_audit(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $before = MeterReading::count();

        foreach (['chief@cmms.test', 'tech1@cmms.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $response = $this->actingAs($user)->post(route('assets.readings.store', $asset), [
                'reading_hours' => '1300.00',
                'reading_at' => '2026-11-25T11:00',
                'note' => 'Post-maintenance check',
            ]);

            $response->assertRedirect(route('assets.readings.index', $asset));
            $response->assertSessionHas('status', 'Meter reading recorded.');
        }

        $this->assertSame($before + 2, MeterReading::count());

        $latest = $asset->fresh()->latestMeterReading;
        $this->assertSame('1300.00', $latest->reading_hours);
        $this->assertSame('tech1@cmms.test', $latest->recordedBy->email);
        $this->assertSame('Post-maintenance check', $latest->note);

        $this->assertSame(
            1,
            AuditEvent::where('action', 'meter_reading.recorded')
                ->where('entity_id', $latest->id)
                ->count()
        );
        $this->assertSame(2, AuditEvent::where('action', 'meter_reading.recorded')->count());
    }

    public function test_guest_cannot_submit_readings(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $before = MeterReading::count();

        $this->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1300.00',
            'reading_at' => '2026-11-25T11:00',
        ])->assertRedirect('/login');

        $this->assertSame($before, MeterReading::count());
    }

    public function test_viewer_cannot_submit_readings(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $before = MeterReading::count();
        $viewer = User::where('email', 'viewer@cmms.test')->firstOrFail();

        $this->actingAs($viewer)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1300.00',
            'reading_at' => '2026-11-25T11:00',
        ])->assertForbidden();

        $this->assertSame($before, MeterReading::count());
    }

    public function test_non_metered_asset_is_rejected(): void
    {
        $asset = Asset::where('code', 'A04')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $before = MeterReading::count();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '10.00',
            'reading_at' => '2026-11-25T11:00',
        ])->assertSessionHasErrors('reading_hours');

        $this->assertSame($before, MeterReading::count());
    }

    public function test_increasing_and_equal_readings_are_accepted(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1265.50',
            'reading_at' => '2026-11-25T11:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1266.25',
            'reading_at' => '2026-11-25T11:30',
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $asset->meterReadings()->count());
        $this->assertSame('1266.25', $asset->fresh()->latestMeterReading->reading_hours);
    }

    public function test_decreasing_reading_is_rejected_without_side_effects(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $readings = MeterReading::count();
        $audits = AuditEvent::count();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1200.00',
            'reading_at' => '2026-11-25T11:00',
        ])->assertSessionHasErrors('reading_hours');

        $this->assertSame($readings, MeterReading::count());
        $this->assertSame($audits, AuditEvent::count());
        $this->assertSame('1265.50', $asset->fresh()->latestMeterReading->reading_hours);
    }

    public function test_invalid_numeric_values_are_rejected(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $before = MeterReading::count();

        $cases = [
            '-1.00',
            'not-a-number',
            '1266.123',
            '10000000000.00',
        ];

        foreach ($cases as $value) {
            $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
                'reading_hours' => $value,
                'reading_at' => '2026-11-25T11:00',
            ])->assertSessionHasErrors('reading_hours');
        }

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1300.00',
        ])->assertSessionHasErrors('reading_at');

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1300.00',
            'reading_at' => 'not-a-date',
        ])->assertSessionHasErrors('reading_at');

        $this->assertSame($before, MeterReading::count());
    }

    public function test_decimal_precision_is_preserved_at_two_places(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1266.5',
            'reading_at' => '2026-11-25T11:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('1266.50', $asset->fresh()->latestMeterReading->reading_hours);
    }

    public function test_future_timestamps_are_rejected_but_backdated_rules_are_enforced(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1300.00',
            'reading_at' => '2026-11-26T09:00',
        ])->assertSessionHasErrors('reading_at');

        // Backdated duplicate of the current maximum is acceptable.
        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1265.50',
            'reading_at' => '2026-11-25T08:00',
        ])->assertSessionHasNoErrors();

        // A backdated increase would make the chronological sequence decrease.
        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1400.00',
            'reading_at' => '2026-11-25T08:30',
        ])->assertSessionHasErrors('reading_hours');
    }

    public function test_equal_timestamps_accept_duplicates_but_reject_conflicting_values(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $before = $asset->meterReadings()->count();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1270.00',
            'reading_at' => '2026-11-25T10:00',
        ])->assertSessionHasNoErrors();

        // A different value at the same instant is ambiguous: UUIDv7 ids are only
        // millisecond-ordered, so "id DESC" could select the lower value.
        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1275.00',
            'reading_at' => '2026-11-25T10:00',
        ])->assertSessionHasErrors('reading_at');

        // An exact duplicate at the same instant is harmless and accepted.
        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1270.00',
            'reading_at' => '2026-11-25T10:00',
        ])->assertSessionHasNoErrors();

        // A later timestamp resolves the increase deterministically.
        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1275.00',
            'reading_at' => '2026-11-25T10:01',
        ])->assertSessionHasNoErrors();

        $this->assertSame($before + 3, $asset->meterReadings()->count());
        $this->assertSame('1275.00', $asset->fresh()->latestMeterReading->reading_hours);
        $this->assertSame('2026-11-25 10:01:00', $asset->fresh()->latestMeterReading->localReadingAt()->toDateTimeString());

        $this->actingAs($tech)
            ->get(route('assets.readings.index', $asset))
            ->assertOk()
            ->assertSeeInOrder(['1,275.00', '1,270.00']);
    }

    public function test_timestamps_round_trip_as_utc_instants_and_display_in_kuala_lumpur(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '1300.00',
            'reading_at' => '2026-11-25T11:00',
        ])->assertSessionHasNoErrors();

        $latest = $asset->fresh()->latestMeterReading;

        // 11:00 Asia/Kuala_Lumpur is 03:00 UTC.
        $this->assertSame('2026-11-25 03:00:00', $latest->reading_at->utc()->toDateTimeString());
        $this->assertSame('2026-11-25 11:00:00', $latest->localReadingAt()->toDateTimeString());

        $raw = (string) DB::table('meter_readings')->where('id', $latest->id)->value('reading_at');
        $this->assertStringStartsWith('2026-11-25 03:00:00', $raw);

        $this->actingAs($tech)
            ->get(route('assets.readings.index', $asset))
            ->assertOk()
            ->assertSee('2026-11-25 11:00:00')
            ->assertDontSee('2026-11-25 03:00:00');
    }

    public function test_equivalent_instants_are_compared_correctly_across_timezones(): void
    {
        $asset = Asset::factory()->hourMeter()->create(['code' => 'Z96']);
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $service = app(MeterReadingService::class);

        // 11:00 KL and 03:00 UTC are the same instant.
        $service->record($asset, '200.00', CarbonImmutable::parse('2026-11-25 03:00:00', 'UTC'), $tech);

        try {
            $service->record($asset, '300.00', CarbonImmutable::parse('2026-11-25 11:00:00', 'Asia/Kuala_Lumpur'), $tech);
            $this->fail('Expected the equal-instant conflicting value to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reading_at', $exception->errors());
        }

        // Same instant with the same value is an accepted duplicate.
        $service->record($asset, '200.00', CarbonImmutable::parse('2026-11-25 11:00:00', 'Asia/Kuala_Lumpur'), $tech);

        // One second later accepts the increase.
        $service->record($asset, '300.00', CarbonImmutable::parse('2026-11-25 11:00:01', 'Asia/Kuala_Lumpur'), $tech);

        $this->assertSame('300.00', $asset->fresh()->latestMeterReading->reading_hours);
        $this->assertSame('2026-11-25 11:00:01', $asset->fresh()->latestMeterReading->localReadingAt()->toDateTimeString());
    }

    public function test_midnight_and_now_boundaries_in_kuala_lumpur(): void
    {
        $asset = Asset::factory()->hourMeter()->create(['code' => 'Z97']);
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $service = app(MeterReadingService::class);

        // Midnight KL on 25 Nov is 16:00 UTC on 24 Nov.
        $service->record($asset, '100.00', CarbonImmutable::parse('2026-11-25 00:00:00', 'Asia/Kuala_Lumpur'), $tech);
        $this->assertSame('2026-11-25 00:00:00', $asset->fresh()->latestMeterReading->localReadingAt()->toDateTimeString());

        // 23:59 KL on 24 Nov is an earlier instant; an equal value is accepted but
        // must not replace the later midnight reading.
        $service->record($asset, '100.00', CarbonImmutable::parse('2026-11-24 23:59:00', 'Asia/Kuala_Lumpur'), $tech);
        $this->assertSame('2026-11-25 00:00:00', $asset->fresh()->latestMeterReading->localReadingAt()->toDateTimeString());

        // Exactly "now" is accepted; one second ahead is rejected.
        $service->record($asset, '110.00', CarbonImmutable::now(), $tech);

        try {
            $service->record($asset, '120.00', CarbonImmutable::now()->addSecond(), $tech);
            $this->fail('Expected a future timestamp to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reading_at', $exception->errors());
        }
    }

    public function test_readings_page_and_asset_panel_follow_role_permissions(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $viewer = User::where('email', 'viewer@cmms.test')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        $this->actingAs($viewer)->get(route('assets.readings.index', $asset))
            ->assertOk()
            ->assertSee('1,265.50')
            ->assertSee('Synthetic teaching scenario.')
            ->assertDontSee('Record a reading');

        $this->actingAs($viewer)->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('1,265.50')
            ->assertDontSee('Record a reading');

        $this->actingAs($tech)->get(route('assets.readings.index', $asset))
            ->assertOk()
            ->assertSee('Record a reading');

        $this->actingAs($tech)->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Record a reading');
    }

    public function test_new_reading_updates_hours_and_dual_status_but_not_calendar(): void
    {
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $asset = Asset::factory()->hourMeter()->create(['code' => 'Z95']);

        $hoursPlan = MaintenancePlan::factory()->hours()->for($asset)->create([
            'code' => 'T95',
            'last_completion_hours' => '100.00',
            'interval_hours' => '50.00',
            'is_active' => true,
        ]);
        $calendarPlan = MaintenancePlan::factory()->calendar()->for($asset)->create([
            'code' => 'T96',
            'last_completion_date' => '2026-11-15',
            'interval_days' => 30,
            'is_active' => true,
        ]);
        $dualPlan = MaintenancePlan::factory()->dual()->for($asset)->create([
            'code' => 'T97',
            'last_completion_hours' => '100.00',
            'interval_hours' => '50.00',
            'last_completion_date' => '2026-11-15',
            'interval_days' => 30,
            'is_active' => true,
        ]);
        $workOrders = WorkOrder::count();

        $this->actingAs($tech)->get(route('plans.show', $hoursPlan))
            ->assertOk()->assertSee('Incomplete (trigger: None)');

        $this->actingAs($tech)->post(route('assets.readings.store', $asset), [
            'reading_hours' => '160.00',
            'reading_at' => '2026-11-25T11:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($tech)->get(route('plans.show', $hoursPlan))
            ->assertOk()->assertSee('Overdue (trigger: Running hours)');
        $this->actingAs($tech)->get(route('plans.show', $dualPlan))
            ->assertOk()->assertSee('Overdue (trigger: Running hours)');
        $this->actingAs($tech)->get(route('plans.show', $calendarPlan))
            ->assertOk()->assertSee('Not due (trigger: None)');

        $this->assertSame('100.00', $hoursPlan->fresh()->last_completion_hours);
        $this->assertSame($workOrders, WorkOrder::count());
    }

    public function test_service_locks_the_asset_row_and_uses_a_transaction(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(MeterReadingService::class)->record(
            $asset,
            '1270.00',
            CarbonImmutable::parse('2026-11-25 11:00', 'Asia/Kuala_Lumpur'),
            $tech,
            'lock check',
        );

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertTrue($queries->contains(
            fn (string $sql): bool => str_contains($sql, 'from "assets"') && str_contains(strtolower($sql), 'for update')
        ));
    }

    public function test_database_trigger_blocks_decreasing_inserts_that_bypass_the_service(): void
    {
        $asset = Asset::where('code', 'A01')->firstOrFail();
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $before = $asset->meterReadings()->count();

        try {
            DB::transaction(fn () => MeterReading::create([
                'asset_id' => $asset->id,
                'reading_hours' => '10.00',
                'reading_at' => CarbonImmutable::parse('2026-11-25 11:00', 'Asia/Kuala_Lumpur'),
                'recorded_by' => $tech->id,
                'note' => 'bypass attempt',
            ]));

            $this->fail('Expected the database trigger to reject a decreasing reading.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('must not decrease', $exception->getMessage());
        }

        $this->assertSame($before, $asset->meterReadings()->count());
    }

    public function test_returned_model_and_audit_payload_carry_the_true_utc_instant(): void
    {
        $asset = Asset::factory()->hourMeter()->create(['code' => 'Z98']);
        $tech = User::where('email', 'tech1@cmms.test')->firstOrFail();
        $service = app(MeterReadingService::class);
        $at = CarbonImmutable::parse('2026-11-25 11:00:00', 'Asia/Kuala_Lumpur');

        $reading = $service->record($asset, '100.00', $at, $tech, 'instant probe');

        // 11:00 KL is 03:00 UTC; both the returned model and the audit payload must
        // carry that instant rather than a naive wall-clock value.
        $this->assertSame('2026-11-25 03:00:00', $reading->reading_at->utc()->toDateTimeString());
        $this->assertSame('2026-11-25 11:00:00', $reading->localReadingAt()->toDateTimeString());

        $audit = AuditEvent::where('entity_id', $reading->id)->firstOrFail();

        $this->assertSame('2026-11-25T03:00:00+00:00', $audit->after['reading_at']);
        $this->assertTrue(
            CarbonImmutable::parse($audit->after['reading_at'])->utc()->equalTo($at->utc())
        );
    }
}
