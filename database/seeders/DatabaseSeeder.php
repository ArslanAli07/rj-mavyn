<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([AdminSeeder::class, InitialSettingsSeeder::class]);
        });
        $this->command?->info('Required platform data initialized. No store catalog or media content was added.');
    }
}
