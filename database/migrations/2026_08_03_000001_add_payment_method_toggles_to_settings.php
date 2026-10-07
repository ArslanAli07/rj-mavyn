<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $toggles = [
            'payment_cod_enabled',
            'payment_easypaisa_enabled',
            'payment_jazzcash_enabled',
            'payment_bank_transfer_enabled',
        ];

        foreach ($toggles as $key) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => '1',
                    'group' => 'payment',
                    'type' => 'boolean',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'payment_cod_enabled',
            'payment_easypaisa_enabled',
            'payment_jazzcash_enabled',
            'payment_bank_transfer_enabled',
        ])->delete();
    }
};
