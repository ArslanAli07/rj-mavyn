<?php

namespace App\Services;

use App\Services\Plugins\PluginRuntime;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CartOwnerLockService
{
    public function synchronized(array $owners, callable $operation): mixed
    {
        return DB::transaction(function () use ($owners, $operation) {
            app(PricingService::class)->lock();
            app(PluginRuntime::class)->refresh();
            $this->lock($owners);

            return $operation();
        }, 3);
    }

    /** Caller holds the transaction; always lock owners before cart/product rows. */
    public function lock(array $owners): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Cart owner locks require a transaction.');
        }
        $hashes = array_values(array_unique(array_map(fn ($owner) => hash('sha256', $owner), $owners)));
        sort($hashes, SORT_STRING);
        foreach ($hashes as $hash) {
            DB::table('cart_owner_locks')->insertOrIgnore(['owner_hash' => $hash, 'created_at' => now()]);
            DB::table('cart_owner_locks')->where('owner_hash', $hash)->lockForUpdate()->first();
        }
    }
}
