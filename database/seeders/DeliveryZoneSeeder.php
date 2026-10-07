<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['name' => 'Model Town',                    'delivery_fee' => 100.00, 'is_active' => true, 'display_order' => 1],
            ['name' => 'Cantt Sialkot',                 'delivery_fee' => 150.00, 'is_active' => true, 'display_order' => 2],
            ['name' => 'Kashmir Road',                  'delivery_fee' => 120.00, 'is_active' => true, 'display_order' => 3],
            ['name' => 'Defence Road',                  'delivery_fee' => 200.00, 'is_active' => true, 'display_order' => 4],
            ['name' => 'Sialkot Bypass / Outskirts',    'delivery_fee' => 300.00, 'is_active' => true, 'display_order' => 5],
            ['name' => 'Pasrur Road',                   'delivery_fee' => 250.00, 'is_active' => true, 'display_order' => 6],
            ['name' => 'Allama Iqbal Town',             'delivery_fee' => 150.00, 'is_active' => true, 'display_order' => 7],
            ['name' => 'Shahabpura',                    'delivery_fee' => 180.00, 'is_active' => true, 'display_order' => 8],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::updateOrCreate(['name' => $zone['name']], $zone);
        }
    }
}
