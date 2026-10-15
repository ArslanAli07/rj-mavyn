<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use App\Services\Pricing\PricingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pin one currency and serialize monetary writes for the whole rendered response. */
final class PricingContext
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethodSafe()) {
            $request->attributes->set('pricing_operation_id', (string) Str::uuid());
        }
        $pricing = app(PricingService::class);
        $pricing->forget();
        if (app()->resolved(CartService::class)) {
            app(CartService::class)->forgetCachedState();
        }
        if (! $pricing->installed()) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $pricing) {
            $context = $pricing->lock(! $request->isMethodSafe());
            if (! $request->isMethodSafe() && $request->is('admin/*') && $request->has('pricing_revision')) {
                abort_unless(ctype_digit((string) $request->input('pricing_revision')) && (int) $request->input('pricing_revision') === $context->revision, 409, 'Pricing changed. Reload the editor before saving.');
            }
            // Always precedes checkout/cart/catalog locks, including plugin settings writes.
            $query = DB::table('extension_runtime')->where('id', 1);
            $request->isMethodSafe() ? $query->sharedLock()->first() : $query->lockForUpdate()->first();

            return $next($request);
        }, 3);
    }
}
