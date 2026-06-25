<?php

namespace Database\Seeders;

use App\Models\Lubricant;
use Illuminate\Database\Seeder;

class LubricantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'name' => 'Super Servo 20W40 MG',
                'qty_name' => '1 lt',
                'price' => 350.00,
                'stock' => 100,
            ],
            [
                'name' => 'Super Servo 20W40 MG',
                'qty_name' => '500Ml',
                'price' => 200.00,
                'stock' => 100,
            ],
            [
                'name' => 'Servo Eco 4T',
                'qty_name' => '900ml',
                'price' => 280.00,
                'stock' => 80,
            ],
            [
                'name' => 'Ioc clear Blue',
                'qty_name' => '20Lt',
                'price' => 1800.00,
                'stock' => 25,
            ],
            [
                'name' => 'Servo Tropikool',
                'qty_name' => '1lt',
                'price' => 380.00,
                'stock' => 50,
            ]
        ];

        foreach ($items as $item) {
            Lubricant::create($item);
        }
    }
}
