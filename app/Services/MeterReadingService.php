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
        return DB::transaction(function () use ($asset, $hours, $at, $actor, $note): MeterReading {
            $locked = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->has_hour_meter) {
                throw ValidationException::withMessages([
                    'reading_hours' => 'This asset does not have a running-hour meter.',
                ]);
            }

            if ($at->greaterThan(CarbonImmutable::now())) {
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

            // Reading timestamps are recorded as Asia/Kuala_Lumpur wall-clock values
            // (the existing Phase 2 seed convention for the timestamptz column), so the
            // chronological comparison uses the stored wall-clock digits, not an offset.
            $latestAt = $latest?->reading_at->format('Y-m-d H:i:s');
            $newAt = $at->format('Y-m-d H:i:s');

            // A backdated reading may only repeat the current maximum; a higher value
            // at an earlier timestamp would make the chronological sequence decrease.
            if ($latestAt !== null && $newAt < $latestAt
                && bccomp($hours, (string) $highest, 2) > 0) {
                throw ValidationException::withMessages([
                    'reading_hours' => 'A backdated reading cannot exceed the latest accepted reading; record the increase with a current or later timestamp.',
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
                    'reading_at' => $reading->reading_at->toIso8601String(),
                    'recorded_by' => $actor->id,
                    'note' => $note,
                ],
                'reason' => null,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            return $reading;
        });
    }
}
