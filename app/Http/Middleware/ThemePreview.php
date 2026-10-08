<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\Themes\ThemeRuntime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ThemePreview
{
    public const ROUTES = ['home', 'shop', 'product.show', 'category.show', 'search', 'about', 'gallery', 'faq', 'contact', 'privacy', 'terms', 'returns', 'shipping'];

    public function handle(Request $request, Closure $next): Response
    {
        $runtime = app(ThemeRuntime::class);
        $runtime->reset();
        if (! $request->has('_theme_preview')) {
            return $next($request);
        }
        abort_unless($request->isMethod('GET') && in_array($request->route()?->getName(), self::ROUTES, true), 403);
        $actor = auth('admin')->id() ? Admin::find(auth('admin')->id()) : null;
        abort_unless($actor && $actor->is_active && $actor->hasPermission('themes.manage'), 403);
        try {
            $payload = json_decode(Crypt::decryptString($request->query('_theme_preview')), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'Invalid theme preview.');
        }
        abort_unless(($payload['actor'] ?? null) === $actor->id && ($payload['expires'] ?? 0) > now()->timestamp && is_string($payload['theme'] ?? null) && is_array($payload['draft'] ?? null), 403);
        $runtime->preview($payload['theme'], $payload['draft']);
        // Keep the customer guard independent from the admin preview credential.
        auth()->shouldUse('web');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
