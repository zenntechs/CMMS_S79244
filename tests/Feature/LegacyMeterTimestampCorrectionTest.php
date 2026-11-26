<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyMeterTimestampCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', 'Asia/Kuala_Lumpur'));
    }

    public function test_fresh_seed_stores_true_utc_instants_and_the_correction_is_a_noop(): void
    {
        $expected = [
            '01900000-0000-4000-8000-000000000201' => ['2026-10-01 00:00:00', '2026-10-01 08:00:00'],
            '01900000-0000-4000-8000-000000000202' => ['2026-11-25 01:30:00', '2026-11-25 09:30:00'],
            '01900000-0000-4000-8000-000000000215' => ['2026-10-01 00:00:00', '2026-10-01 08:00:00'],
            '01900000-0000-4000-8000-000000000216' => ['2026-11-25 01:30:00', '2026-11-25 09:30:00'],
        ];

        foreach ($expected as $id => [$utc, $local]) {
            $reading = MeterReading::whereKey($id)->firstOrFail();

            $this->assertSame($utc, $reading->reading_at->utc()->toDateTimeString());
            $this->assertSame($local, $reading->localReadingAt()->toDateTimeString());
        }

        $before = MeterReading::orderBy('id')->get(['id', 'reading_at'])->toJson();

        $this->artisan('cmms:correct-legacy-meter-timestamps', ['--apply' => true])
            ->expectsOutputToContain('Nothing to do')
            ->assertSuccessful();

        $this->assertSame($before, MeterReading::orderBy('id')->get(['id', 'reading_at'])->toJson());
        $this->assertSame(16, MeterReading::count());
    }
}
