<?php

use App\Services\StoreIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No older per-order brand record exists; freeze the migration-time client identity.
        $brand = StoreIdentity::snapshot();
        DB::table('orders')->whereNull('brand_snapshot')->update(['brand_snapshot' => json_encode($brand, JSON_THROW_ON_ERROR)]);
        DB::table('notification_outbox')->whereNotNull('order_id')->whereNull('sent_at')->orderBy('id')->chunkById(100, function ($entries) use ($brand) {
            foreach ($entries as $entry) {
                $payload = json_decode($entry->payload, true, 512, JSON_THROW_ON_ERROR);
                if (! isset($payload['order'])) {
                    continue;
                }
                // Outbox order snapshots contain raw Eloquent attributes, including JSON strings.
                $payload['order'] += ['brand_snapshot' => json_encode($brand, JSON_THROW_ON_ERROR), 'currency' => 'PKR', 'currency_symbol' => 'Rs.'];
                DB::table('notification_outbox')->where('id', $entry->id)->whereNull('sent_at')->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void
    {
        // Keep historical identities; removing a snapshot would reinterpret older orders.
    }
};
