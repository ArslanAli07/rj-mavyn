<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Services\AdminPermissions;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = AdminPermissions::required($request->route()->getName(), $request);
        $admin = $request->user('admin');
        abort_unless($required !== null && collect($required)->every(fn ($permission) => $admin->hasPermission($permission)), 403);
        if ($request->isMethodSafe()) {
            $response = $next($request);
            $name = $request->route()->getName();
            if ($response->getStatusCode() < 400 && (in_array($name, ['admin.orders.receipt', 'admin.newsletter.export'], true)
                || ($name === 'admin.media.file' && $request->route('asset')->visibility === 'private'))) {
                AdminAuditLog::create(['actor_id' => $admin->id, 'actor_name' => $admin->name,
                    'route' => $name, 'action' => 'accessed', 'entity_type' => $name === 'admin.orders.receipt' ? 'orders' : ($name === 'admin.media.file' ? 'media_assets' : 'newsletter_subscribers'),
                    'entity_id' => (string) ($request->route('order')?->id ?? $request->route('asset')?->id ?? 'export'), 'changes' => []]);
            }

            return $response;
        }

        return DB::transaction(function () use ($request, $next, $admin) {
            $request->attributes->set('audit_admin', $admin);
            try {
                $response = $next($request);
                if ($response->getStatusCode() >= 400 || ($response->exception ?? null)) {
                    throw new HttpResponseException($response);
                }

                AdminAuditLog::create(['actor_id' => $admin->id, 'actor_name' => $admin->name,
                    'route' => $request->route()->getName(), 'action' => 'executed',
                    'entity_type' => 'admin_operations', 'entity_id' => $request->route()->getName(), 'changes' => []]);

                return $response;
            } finally {
                $request->attributes->remove('audit_admin');
            }
        });
    }
}
