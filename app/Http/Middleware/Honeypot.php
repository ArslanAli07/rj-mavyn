<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Honeypot
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('honeypot.enabled', true)) {
            return $next($request);
        }

        if ($this->isSpam($request)) {
            Log::info('Honeypot blocked form submission', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->spamResponse($request);
        }

        return $next($request);
    }

    protected function isSpam(Request $request): bool
    {
        $nameField = config('honeypot.name_field', 'website');
        $timeField = config('honeypot.valid_from_field', '_form_started_at');
        $minSeconds = (int) config('honeypot.min_seconds', 3);

        // Bots fill tempting fields; humans never see this input.
        if (filled($request->input($nameField))) {
            return true;
        }

        $encryptedStartedAt = $request->input($timeField);

        // Missing timestamp is treated as spam (bots often strip unknown fields).
        if (! is_string($encryptedStartedAt) || $encryptedStartedAt === '') {
            return true;
        }

        try {
            $startedAt = (int) Crypt::decryptString($encryptedStartedAt);
        } catch (Throwable) {
            return true;
        }

        // Reject impossibly old tokens (e.g. replayed / forged).
        if ($startedAt < now()->subDay()->timestamp) {
            return true;
        }

        return (now()->timestamp - $startedAt) < $minSeconds;
    }

    protected function spamResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to process your request. Please try again.',
            ], 422);
        }

        // Silent redirect — bots get no useful signal; rare false positives can retry.
        return redirect()
            ->back()
            ->withInput($request->except([
                config('honeypot.name_field'),
                config('honeypot.valid_from_field'),
                'password',
                'password_confirmation',
            ]));
    }
}
