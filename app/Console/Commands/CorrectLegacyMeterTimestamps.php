<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CorrectLegacyMeterTimestamps extends Command
{
    protected $signature = 'cmms:correct-legacy-meter-timestamps
                            {--apply : Apply the correction; without this flag the command only reports}';

    protected $description = 'Correct the 16 Phase 2 legacy meter-reading seed timestamps from UTC-labelled Kuala Lumpur wall clock to true UTC instants (idempotent).';

    /**
     * The eight earlier seed readings stored as 08:00 UTC instead of 08:00 Kuala Lumpur.
     *
     * @var list<string>
     */
    private const EARLY_IDS = [
        '01900000-0000-4000-8000-000000000201',
        '01900000-0000-4000-8000-000000000203',
        '01900000-0000-4000-8000-000000000205',
        '01900000-0000-4000-8000-000000000207',
        '01900000-0000-4000-8000-000000000209',
        '01900000-0000-4000-8000-000000000211',
        '01900000-0000-4000-8000-000000000213',
        '01900000-0000-4000-8000-000000000215',
    ];

    /**
     * The eight later seed readings stored as 09:30 UTC instead of 09:30 Kuala Lumpur.
     *
     * @var list<string>
     */
    private const LATE_IDS = [
        '01900000-0000-4000-8000-000000000202',
        '01900000-0000-4000-8000-000000000204',
        '01900000-0000-4000-8000-000000000206',
        '01900000-0000-4000-8000-000000000208',
        '01900000-0000-4000-8000-000000000210',
        '01900000-0000-4000-8000-000000000212',
        '01900000-0000-4000-8000-000000000214',
        '01900000-0000-4000-8000-000000000216',
    ];

    /**
     * Expected seeded reading hours per identifier, used to prove the row is the expected seed row.
     *
     * @var array<string, string>
     */
    private const EXPECTED_HOURS = [
        '01900000-0000-4000-8000-000000000201' => '1180.00',
        '01900000-0000-4000-8000-000000000202' => '1265.50',
        '01900000-0000-4000-8000-000000000203' => '1190.00',
        '01900000-0000-4000-8000-000000000204' => '1272.00',
        '01900000-0000-4000-8000-000000000205' => '3980.00',
        '01900000-0000-4000-8000-000000000206' => '4110.25',
        '01900000-0000-4000-8000-000000000207' => '640.00',
        '01900000-0000-4000-8000-000000000208' => '702.50',
        '01900000-0000-4000-8000-000000000209' => '210.00',
        '01900000-0000-4000-8000-000000000210' => '218.75',
        '01900000-0000-4000-8000-000000000211' => '95.00',
        '01900000-0000-4000-8000-000000000212' => '97.00',
        '01900000-0000-4000-8000-000000000213' => '1980.00',
        '01900000-0000-4000-8000-000000000214' => '2145.00',
        '01900000-0000-4000-8000-000000000215' => '790.00',
        '01900000-0000-4000-8000-000000000216' => '902.50',
    ];

    public function handle(): int
    {
        $legacy = $this->legacyRows();
        $records = DB::table('meter_readings')
            ->whereIn('id', array_keys($legacy))
            ->get(['id', 'asset_id', 'reading_hours', 'reading_at', 'recorded_by', 'note'])
            ->keyBy('id');

        if ($records->count() !== count($legacy)) {
            $missing = array_diff(array_keys($legacy), $records->keys()->all());

            $this->error('Aborting: expected seed rows are missing: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $pending = [];
        $alreadyCorrected = [];
        $unexpected = [];

        foreach ($legacy as $id => $row) {
            $record = $records[$id];

            if (bccomp((string) $record->reading_hours, self::EXPECTED_HOURS[$id], 2) !== 0) {
                $unexpected[] = "{$id}: reading_hours is {$record->reading_hours}, expected ".self::EXPECTED_HOURS[$id];

                continue;
            }

            if ($this->sameInstant((string) $record->reading_at, $row['from'])) {
                $pending[$id] = $row;

                continue;
            }

            if ($this->sameInstant((string) $record->reading_at, $row['to'])) {
                $alreadyCorrected[$id] = $row;

                continue;
            }

            $unexpected[] = "{$id}: reading_at is {$record->reading_at}, expected {$row['from']} or {$row['to']}";
        }

        $this->info('Legacy seed rows found: '.$records->count());
        $this->info('Needing correction: '.count($pending));
        $this->info('Already corrected: '.count($alreadyCorrected));

        if ($unexpected !== []) {
            $this->error('Aborting: unexpected row state detected; no data were changed.');
            foreach ($unexpected as $line) {
                $this->error('  - '.$line);
            }

            return self::FAILURE;
        }

        if ($pending === []) {
            $this->info('Nothing to do: all legacy rows already hold true UTC instants.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'reading_hours', 'from (UTC)', 'to (UTC)'],
            array_map(
                static fn (string $id, array $row): array => [$id, self::EXPECTED_HOURS[$id], $row['from'], $row['to']],
                array_keys($pending),
                array_values($pending),
            ),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to correct these rows.');

            return self::SUCCESS;
        }

        try {
            $changed = DB::transaction(function () use ($pending): int {
                $locked = DB::table('meter_readings')
                    ->whereIn('id', array_keys($pending))
                    ->lockForUpdate()
                    ->get(['id', 'asset_id', 'reading_hours', 'reading_at', 'recorded_by', 'note'])
                    ->keyBy('id');

                $totalBefore = DB::table('meter_readings')->count();
                $snapshot = $locked->map(static fn (object $row): array => [
                    'asset_id' => $row->asset_id,
                    'reading_hours' => (string) $row->reading_hours,
                    'recorded_by' => $row->recorded_by,
                    'note' => $row->note,
                ])->all();

                foreach ($pending as $id => $row) {
                    if (! $this->sameInstant((string) $locked[$id]->reading_at, $row['from'])) {
                        throw new RuntimeException("Row {$id} changed while the correction was being prepared; aborting.");
                    }
                }

                // The no-decrease trigger compares reading hours across the whole asset and
                // fires on UPDATE, so shifting an earlier row would fail even though the
                // hours are unchanged. Disable it only inside this transaction and always
                // re-enable it before the transaction commits.
                DB::statement('ALTER TABLE meter_readings DISABLE TRIGGER meter_readings_no_decrease');

                try {
                    $changed = 0;
                    foreach ($pending as $id => $row) {
                        $changed += DB::table('meter_readings')
                            ->where('id', $id)
                            ->where('reading_at', $row['from'])
                            ->update(['reading_at' => $row['to']]);
                    }
                } finally {
                    DB::statement('ALTER TABLE meter_readings ENABLE TRIGGER meter_readings_no_decrease');
                }

                $after = DB::table('meter_readings')
                    ->whereIn('id', array_keys($pending))
                    ->get(['id', 'asset_id', 'reading_hours', 'reading_at', 'recorded_by', 'note'])
                    ->keyBy('id');

                foreach ($pending as $id => $row) {
                    if (! $this->sameInstant((string) $after[$id]->reading_at, $row['to'])) {
                        throw new RuntimeException("Row {$id} did not reach the expected corrected instant; aborting.");
                    }

                    $unchanged = [
                        'asset_id' => $after[$id]->asset_id,
                        'reading_hours' => (string) $after[$id]->reading_hours,
                        'recorded_by' => $after[$id]->recorded_by,
                        'note' => $after[$id]->note,
                    ];

                    if ($unchanged !== $snapshot[$id]) {
                        throw new RuntimeException("Row {$id} changed outside reading_at; aborting.");
                    }
                }

                if (DB::table('meter_readings')->count() !== $totalBefore) {
                    throw new RuntimeException('The total meter_reading row count changed; aborting.');
                }

                return $changed;
            });
        } catch (Throwable $exception) {
            $this->error('Correction failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($changed !== count($pending)) {
            $this->error('Expected to update '.count($pending)." rows but updated {$changed}; rolled back.");

            return self::FAILURE;
        }

        $this->info("Corrected {$changed} legacy rows. Trigger re-enabled: ".$this->triggerEnabled());

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{from: string, to: string}>
     */
    private function legacyRows(): array
    {
        $rows = [];

        foreach (self::EARLY_IDS as $id) {
            $rows[$id] = ['from' => '2026-10-01 08:00:00+00', 'to' => '2026-10-01 00:00:00+00'];
        }

        foreach (self::LATE_IDS as $id) {
            $rows[$id] = ['from' => '2026-11-25 09:30:00+00', 'to' => '2026-11-25 01:30:00+00'];
        }

        return $rows;
    }

    private function sameInstant(string $left, string $right): bool
    {
        return CarbonImmutable::parse($left)->utc()->equalTo(CarbonImmutable::parse($right)->utc());
    }

    private function triggerEnabled(): string
    {
        $enabled = DB::selectOne("select tgenabled from pg_trigger where tgname = 'meter_readings_no_decrease'");

        return $enabled?->tgenabled === 'O' ? 'yes' : 'NO ('.($enabled?->tgenabled ?? 'missing').')';
    }
}
