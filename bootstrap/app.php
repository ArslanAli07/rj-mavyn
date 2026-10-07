<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureOrderingEnabled;
use App\Http\Middleware\Honeypot;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'honeypot' => Honeypot::class,
            'ordering' => EnsureOrderingEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );
    })->create();

// Split hosting (e.g. Hostinger): the Laravel app lives in /happytea while the
// document root is sibling public_html. DomPDF and artisan both need this path.
if (! is_dir($app->publicPath())) {
    $publicHtml = dirname($app->basePath()).DIRECTORY_SEPARATOR.'public_html';
    if (is_dir($publicHtml)) {
        $app->usePublicPath($publicHtml);
    }
}

return $app;
