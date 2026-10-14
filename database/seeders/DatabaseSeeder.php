<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('platform.demo_seed', false)) {
            $this->command?->info('No client demo data was seeded. Use admin:provision and configure the store. Set MAVYN_DEMO_SEED=true only for the Happy Tea demo catalog.');

            return;
        }
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
