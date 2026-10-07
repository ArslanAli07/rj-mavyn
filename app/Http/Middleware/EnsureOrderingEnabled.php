<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrderingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ordering_enabled()) {
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
