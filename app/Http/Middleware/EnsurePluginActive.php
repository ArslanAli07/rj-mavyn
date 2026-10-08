<?php

namespace App\Http\Middleware;

use App\Services\Plugins\InstalledPluginCatalog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePluginActive
{
    public function handle(Request $request, Closure $next, string $id): Response
    {
        abort_unless(isset(app(InstalledPluginCatalog::class)->load()->all()[$id]) && DB::table('plugin_states')->where('plugin_id', $id)->where('active', true)->exists(), 404);

        return $next($request);
    }
}
