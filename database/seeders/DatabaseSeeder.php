<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DeliveryZoneSeeder::class,
            AdminSeeder::class,
            CategorySeeder::class,
            OccasionSeeder::class,
            ProductSeeder::class,
            HomeSlideSeeder::class,
            TestimonialSeeder::class,
            FaqSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
