<?php

namespace Database\Seeders;

use App\Models\Vessel;
use Illuminate\Database\Seeder;

class VesselSeeder extends Seeder
{
    public function run(): void
    {
        Vessel::updateOrCreate(
            ['code' => 'TRAIN-01'],
            [
                'id' => '01900000-0000-4000-8000-000000000001',
                'name' => 'Training Vessel Seri Ilmu',
                'description' => 'Fictional training vessel created for coursework; not a real vessel.',
            ]
        );
    }
}
