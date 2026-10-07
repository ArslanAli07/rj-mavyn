<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'ordering_enabled')->exists();

        if (! $exists) {
            $now = now();
            DB::table('settings')->insert([
                'key' => 'ordering_enabled',
                'value' => '0',
                'group' => 'ordering',
                'type' => 'boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('all_settings');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'ordering_enabled')->delete();
        Cache::forget('all_settings');
    }
};
