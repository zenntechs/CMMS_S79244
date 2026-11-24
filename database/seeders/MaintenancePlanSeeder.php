<?php

namespace Database\Seeders;

use App\Enums\PlanTrigger;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use Illuminate\Database\Seeder;

class MaintenancePlanSeeder extends Seeder
{
    public function run(): void
    {
        $assetIds = Asset::pluck('id', 'code');

        $plans = [
            ['code' => 'P01', 'asset' => 'A05', 'trigger' => PlanTrigger::Calendar, 'days' => 30, 'hours' => null, 'date' => '2026-11-01', 'hrs' => null],
            ['code' => 'P02', 'asset' => 'A07', 'trigger' => PlanTrigger::Calendar, 'days' => 90, 'hours' => null, 'date' => '2026-10-15', 'hrs' => null],
            ['code' => 'P03', 'asset' => 'A08', 'trigger' => PlanTrigger::Calendar, 'days' => 180, 'hours' => null, 'date' => '2026-09-01', 'hrs' => null],
            ['code' => 'P04', 'asset' => 'A01', 'trigger' => PlanTrigger::Hours, 'days' => null, 'hours' => 250.00, 'date' => null, 'hrs' => 1200.00],
            ['code' => 'P05', 'asset' => 'A03', 'trigger' => PlanTrigger::Hours, 'days' => null, 'hours' => 500.00, 'date' => null, 'hrs' => 4000.00],
            ['code' => 'P06', 'asset' => 'A10', 'trigger' => PlanTrigger::Hours, 'days' => null, 'hours' => 200.00, 'date' => null, 'hrs' => 800.00],
            ['code' => 'P07', 'asset' => 'A02', 'trigger' => PlanTrigger::Dual, 'days' => 30, 'hours' => 250.00, 'date' => '2026-11-01', 'hrs' => 1200.00],
            ['code' => 'P08', 'asset' => 'A09', 'trigger' => PlanTrigger::Dual, 'days' => 60, 'hours' => 400.00, 'date' => '2026-10-20', 'hrs' => 2000.00],
        ];

        $i = 1;
        foreach ($plans as $plan) {
            MaintenancePlan::updateOrCreate(
                ['code' => $plan['code']],
                [
                    'id' => sprintf('01900000-0000-4000-8000-0000000003%02d', $i),
                    'asset_id' => $assetIds[$plan['asset']],
                    'task_description' => 'Synthetic teaching scenario maintenance task for '.$plan['asset'].'.',
                    'trigger_type' => $plan['trigger'],
                    'interval_days' => $plan['days'],
                    'interval_hours' => $plan['hours'],
                    'last_completion_date' => $plan['date'],
                    'last_completion_hours' => $plan['hrs'],
                    'source_label' => 'Synthetic teaching scenario',
                    'is_active' => true,
                ]
            );
            $i++;
        }
    }
}
