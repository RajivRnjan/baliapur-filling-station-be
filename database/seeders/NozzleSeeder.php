<?php

namespace Database\Seeders;

use App\Models\Nozzle;
use Illuminate\Database\Seeder;

class NozzleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // MS (Petrol) nozzles
        Nozzle::updateOrCreate(
            ['nozzle_no' => 'NOZZEL-2'],
            ['fuel_type' => 'MS', 'opening_reading' => 1284847.19]
        );
        Nozzle::updateOrCreate(
            ['nozzle_no' => 'NOZZEL-3'],
            ['fuel_type' => 'MS', 'opening_reading' => 314891.49]
        );
        Nozzle::updateOrCreate(
            ['nozzle_no' => 'NOZZEL-6'],
            ['fuel_type' => 'MS', 'opening_reading' => 291235.17]
        );

        // HSD (Diesel) nozzles
        Nozzle::updateOrCreate(
            ['nozzle_no' => 'NOZZEL-1'],
            ['fuel_type' => 'HSD', 'opening_reading' => 312027.97]
        );
        Nozzle::updateOrCreate(
            ['nozzle_no' => 'NOZZEL-5'],
            ['fuel_type' => 'HSD', 'opening_reading' => 170732.07]
        );

        // XP (Extra Premium) nozzle
        Nozzle::updateOrCreate(
            ['nozzle_no' => 'NOZZEL-4'],
            ['fuel_type' => 'XP', 'opening_reading' => 266498.04]
        );
    }
}
