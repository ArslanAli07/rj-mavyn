<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravelRootFile = __DIR__.'/.laravel-root';
$laravelRootFromFile = is_file($laravelRootFile)
    ? __DIR__.'/..'.'/'.trim(file_get_contents($laravelRootFile))
    : null;

$laravelRoot = match (true) {
    is_file(__DIR__.'/../vendor/autoload.php') => __DIR__.'/..',
    is_file(__DIR__.'/../happytea/vendor/autoload.php') => __DIR__.'/../happytea',
    $laravelRootFromFile !== null && is_file($laravelRootFromFile.'/vendor/autoload.php') => $laravelRootFromFile,
    default => __DIR__.'/..',
};

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $laravelRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

// When public/ contents live in public_html (split deployment), index.php's
// directory is the real web root—not {laravelRoot}/public.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
