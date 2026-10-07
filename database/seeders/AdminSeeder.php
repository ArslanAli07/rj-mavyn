<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('No default admin is seeded. Run php artisan admin:provision your-email@example.com.');
    }
}
