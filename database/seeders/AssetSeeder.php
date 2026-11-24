<?php

namespace Database\Seeders;

use App\Enums\Criticality;
use App\Enums\ServiceStatus;
use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $vesselId = '01900000-0000-4000-8000-000000000001';

        $assets = [
            ['code' => 'A01', 'name' => 'Port main engine', 'subsystem' => 'Propulsion', 'location' => 'Engine room, port side', 'criticality' => Criticality::Critical, 'has_hour_meter' => true],
            ['code' => 'A02', 'name' => 'Starboard main engine', 'subsystem' => 'Propulsion', 'location' => 'Engine room, starboard side', 'criticality' => Criticality::Critical, 'has_hour_meter' => true],
            ['code' => 'A03', 'name' => 'Port gearbox', 'subsystem' => 'Transmission', 'location' => 'Engine room, port side', 'criticality' => Criticality::High, 'has_hour_meter' => true],
            ['code' => 'A04', 'name' => 'Starboard gearbox', 'subsystem' => 'Transmission', 'location' => 'Engine room, starboard side', 'criticality' => Criticality::High, 'has_hour_meter' => false],
            ['code' => 'A05', 'name' => 'Generator set', 'subsystem' => 'Electrical power', 'location' => 'Engine room, aft', 'criticality' => Criticality::High, 'has_hour_meter' => true],
            ['code' => 'A06', 'name' => 'Bilge pump', 'subsystem' => 'Bilge system', 'location' => 'Bilge, forward', 'criticality' => Criticality::Medium, 'has_hour_meter' => true],
            ['code' => 'A07', 'name' => 'Fire pump', 'subsystem' => 'Fire main', 'location' => 'Fire pump room', 'criticality' => Criticality::Critical, 'has_hour_meter' => true],
            ['code' => 'A08', 'name' => 'Steering hydraulic unit', 'subsystem' => 'Steering', 'location' => 'Steering gear room', 'criticality' => Criticality::High, 'has_hour_meter' => false],
            ['code' => 'A09', 'name' => 'Sea-water cooling pump', 'subsystem' => 'Cooling', 'location' => 'Engine room, centre', 'criticality' => Criticality::Medium, 'has_hour_meter' => true],
            ['code' => 'A10', 'name' => 'Air compressor', 'subsystem' => 'Compressed air', 'location' => 'Compressor room', 'criticality' => Criticality::Low, 'has_hour_meter' => true],
        ];

        $i = 1;
        foreach ($assets as $asset) {
            Asset::updateOrCreate(
                ['code' => $asset['code']],
                [
                    'id' => sprintf('01900000-0000-4000-8000-0000000001%02d', $i),
                    'vessel_id' => $vesselId,
                    'name' => $asset['name'],
                    'subsystem' => $asset['subsystem'],
                    'location' => $asset['location'],
                    'criticality' => $asset['criticality'],
                    'service_status' => ServiceStatus::InService,
                    'has_hour_meter' => $asset['has_hour_meter'],
                    'meter_unit' => 'hours',
                ]
            );
            $i++;
        }
    }
}
