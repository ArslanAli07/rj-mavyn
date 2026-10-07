<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'ordering_start_time' => '09:00',
            'ordering_end_time' => '21:00',
        ] as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'group' => 'ordering',
                    'type' => 'text',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Cache::forget('all_settings');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['ordering_start_time', 'ordering_end_time'])->delete();
        Cache::forget('all_settings');
    }
};
