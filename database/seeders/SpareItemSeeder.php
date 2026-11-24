<?php

namespace Database\Seeders;

use App\Models\SpareItem;
use Illuminate\Database\Seeder;

class SpareItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'SP-001', 'description' => 'Oil filter element', 'unit' => 'piece', 'qty' => 10.00, 'reorder' => 6.00],
            ['code' => 'SP-002', 'description' => 'Fuel injector', 'unit' => 'piece', 'qty' => 7.00, 'reorder' => 2.00],
            ['code' => 'SP-003', 'description' => 'Lubricating oil', 'unit' => 'litre', 'qty' => 185.00, 'reorder' => 60.00],
            ['code' => 'SP-004', 'description' => 'Cooling water pump seal', 'unit' => 'piece', 'qty' => 3.00, 'reorder' => 2.00],
            ['code' => 'SP-005', 'description' => 'Air compressor belt', 'unit' => 'piece', 'qty' => 3.00, 'reorder' => 5.00],
            ['code' => 'SP-006', 'description' => 'Hydraulic hose assembly', 'unit' => 'piece', 'qty' => 1.00, 'reorder' => 2.00],
        ];

        $i = 1;
        foreach ($items as $item) {
            SpareItem::updateOrCreate(
                ['code' => $item['code']],
                [
                    'id' => sprintf('01900000-0000-4000-8000-0000000006%02d', $i),
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'quantity_on_hand' => $item['qty'],
                    'reorder_level' => $item['reorder'],
                    'is_active' => true,
                ]
            );
            $i++;
        }
    }
}
