<?php

namespace Database\Seeders;

use App\Models\Occasion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OccasionSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Occasion::truncate();
        Schema::enableForeignKeyConstraints();

        $occasions = [
            ['name' => 'Study Session', 'slug' => 'study-session', 'icon' => '📚', 'display_order' => 1],
            ['name' => 'Date Night',    'slug' => 'date-night',    'icon' => '💕', 'display_order' => 2],
            ['name' => 'Hangout',       'slug' => 'hangout',       'icon' => '🎉', 'display_order' => 3],
            ['name' => 'Office Treat',  'slug' => 'office-treat',  'icon' => '🏢', 'display_order' => 4],
        ];

        foreach ($occasions as $occ) {
            Occasion::create(array_merge($occ, [
                'description' => "Perfect drinks for {$occ['name']}.",
                'is_active'   => true,
            ]));
        }
    }
}
