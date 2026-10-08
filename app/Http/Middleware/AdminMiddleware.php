<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('admin')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Please log in to access the admin panel.');
        }

        $current = Admin::find(auth('admin')->id());
        if (! $current || ! $current->is_active) {
            auth('admin')->logout();

            return redirect()->route('admin.login')
                ->with('error', 'Your admin account has been deactivated.');
        }

        auth('admin')->setUser($current);

        return $next($request);
    }
}
