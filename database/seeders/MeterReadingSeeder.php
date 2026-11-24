<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Database\Seeder;

class MeterReadingSeeder extends Seeder
{
    public function run(): void
    {
        $readings = [
            'A01' => [[1180.00, '2026-10-01 08:00:00'], [1265.50, '2026-11-25 09:30:00']],
            'A02' => [[1190.00, '2026-10-01 08:00:00'], [1272.00, '2026-11-25 09:30:00']],
            'A03' => [[3980.00, '2026-10-01 08:00:00'], [4110.25, '2026-11-25 09:30:00']],
            'A05' => [[640.00, '2026-10-01 08:00:00'], [702.50, '2026-11-25 09:30:00']],
            'A06' => [[210.00, '2026-10-01 08:00:00'], [218.75, '2026-11-25 09:30:00']],
            'A07' => [[95.00, '2026-10-01 08:00:00'], [97.00, '2026-11-25 09:30:00']],
            'A09' => [[1980.00, '2026-10-01 08:00:00'], [2145.00, '2026-11-25 09:30:00']],
            'A10' => [[790.00, '2026-10-01 08:00:00'], [902.50, '2026-11-25 09:30:00']],
        ];

        $assetIds = Asset::pluck('id', 'code');
        $techId = '00000000-0000-0000-0000-000000000000'; // placeholder, replaced below
        $actors = User::whereIn('email', ['tech1@cmms.test', 'chief@cmms.test'])->pluck('id', 'email');
        $actorId = $actors['tech1@cmms.test'] ?? $actors->first();

        $counter = 1;
        foreach ($readings as $code => $rows) {
            $assetId = $assetIds[$code];
            foreach ($rows as [$hours, $at]) {
                MeterReading::firstOrCreate(
                    ['id' => sprintf('01900000-0000-4000-8000-0000000002%02d', $counter)],
                    [
                        'asset_id' => $assetId,
                        'reading_hours' => $hours,
                        'reading_at' => $at,
                        'recorded_by' => $actorId,
                        'note' => 'Synthetic teaching scenario.',
                    ]
                );
                $counter++;
            }
        }
    }
}
