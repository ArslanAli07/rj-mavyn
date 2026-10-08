<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrderingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // A completed checkout is a read of an existing order, even after ordering closes.
        $recovering = $request->routeIs('checkout.place-order')
            ? app(CheckoutAttemptService::class)->completedForOwner($request->input('idempotency_key'))
            : ($request->routeIs('checkout.index') && app(CartService::class)->count() === 0
                && app(CheckoutAttemptService::class)->completedForOwner(session('checkout_key')));
        if (ordering_enabled() || $recovering) {
            return $next($request);
        }

        $message = ordering_manually_enabled()
            ? 'The shop is currently closed. Online ordering is available from '.ordering_hours_label().'.'
            : "Online ordering is temporarily paused. We'll start accepting orders again soon on our website. Checkout on Foodpanda.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->route('shop')
            ->with('error', $message);
    }
}
