<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditEvent;
use App\Models\MeterReading;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeterReadingService
{
    /**
     * Record a cumulative running-hour reading for a metered asset.
     *
     * The asset row is locked so concurrent submissions for the same asset are
     * serialised; the PostgreSQL trigger remains the final integrity backstop.
     *
     * @throws ValidationException
     */
    public function record(
        Asset $asset,
        string $hours,
        CarbonImmutable $at,
        User $actor,
        ?string $note = null,
    ): MeterReading {
        $at = $at->utc();
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($asset, $hours, $at, $now, $actor, $note): MeterReading {
            $locked = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->has_hour_meter) {
                throw ValidationException::withMessages([
                    'reading_hours' => 'This asset does not have a running-hour meter.',
                ]);
            }

            if ($at->greaterThan($now)) {
                throw ValidationException::withMessages([
                    'reading_at' => 'The reading timestamp cannot be in the future.',
                ]);
            }

            $latest = MeterReading::query()
                ->where('asset_id', $locked->id)
                ->orderByDesc('reading_at')
                ->orderByDesc('id')
                ->first();

            $highest = MeterReading::query()->where('asset_id', $locked->id)->max('reading_hours');

            if ($highest !== null && bccomp($hours, (string) $highest, 2) < 0) {
                throw ValidationException::withMessages([
                    'reading_hours' => "The reading must not be lower than the highest accepted reading ({$highest} h).",
                ]);
            }

            // A backdated reading may only repeat the current maximum; a higher value
            // at an earlier instant would make the chronological sequence decrease.
            if ($latest !== null && $at->lessThan($latest->reading_at)
                && bccomp($hours, (string) $highest, 2) > 0) {
                throw ValidationException::withMessages([
                    'reading_hours' => 'A backdated reading cannot exceed the latest accepted reading; record the increase with a current or later timestamp.',
                ]);
            }

            // UUIDv7 ids are time-ordered only to the millisecond; two different values
            // at the same instant cannot be deterministically ordered. Reject the
            // ambiguity and require a later timestamp.
            if ($latest !== null && $at->equalTo($latest->reading_at)
                && bccomp($hours, (string) $latest->reading_hours, 2) !== 0) {
                throw ValidationException::withMessages([
                    'reading_at' => 'Another reading already exists at this timestamp; use a later timestamp for a different value.',
                ]);
            }

            $reading = MeterReading::create([
                'asset_id' => $locked->id,
                'reading_hours' => $hours,
                'reading_at' => $at,
                'recorded_by' => $actor->id,
                'note' => $note,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'meter_reading.recorded',
                'entity_type' => 'meter_reading',
                'entity_id' => $reading->id,
                'before' => null,
                'after' => [
                    'asset_id' => $locked->id,
                    'reading_hours' => (string) $reading->reading_hours,
                    'reading_at' => $at->toIso8601String(),
                    'recorded_by' => $actor->id,
                    'note' => $note,
                ],
                'reason' => null,
            ]);

            // Re-read the row so the returned model carries the timestamptz instant;
            // the freshly inserted attributes hold a naive UTC string that the
            // datetime cast would otherwise interpret in the application timezone.
            return $reading->refresh();
        });
    }
}
