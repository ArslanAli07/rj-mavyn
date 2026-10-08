<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePlugin
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $actor = $request->user('admin');
        abort_unless($actor?->hasPermission($permission), 403);
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $actor) {
            $request->attributes->set('audit_admin', $actor);
            try {
                $response = $next($request);
                if ($response->getStatusCode() >= 400 || ($response->exception ?? null)) {
                    throw new HttpResponseException($response);
                }
                AdminAuditLog::create(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'route' => $request->route()->getName(), 'action' => 'executed', 'entity_type' => 'plugin_operations', 'entity_id' => $request->route()->getName(), 'changes' => []]);

                return $response;
            } finally {
                $request->attributes->remove('audit_admin');
            }
        });
    }
}
