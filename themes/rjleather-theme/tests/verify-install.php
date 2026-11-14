<?php

// Run against the configured MySQL store only after the isolated browser checks.
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

$tables = ['products', 'categories', 'orders', 'users', 'media_assets'];
$counts = static fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
$before = $counts();
if (DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('This installation verification expects MySQL.');
}
$cacheDriver = config('cache.default');
Cache::setDefaultDriver('array');
try {
    // Temporary tables shadow the real catalog only on this connection. Their
    // records never enter the merchant tables, caches, media or audit log.
    DB::statement('CREATE TEMPORARY TABLE categories (id BIGINT PRIMARY KEY, name VARCHAR(255), slug VARCHAR(255), is_active BOOLEAN, display_order INT DEFAULT 0)');
    DB::statement('CREATE TEMPORARY TABLE products (id BIGINT PRIMARY KEY, category_id BIGINT, title VARCHAR(255), slug VARCHAR(255), price DECIMAL(10,2), discount_price DECIMAL(10,2) NULL, is_active BOOLEAN, in_stock BOOLEAN, display_order INT DEFAULT 0, created_at TIMESTAMP NULL)');
    DB::table('categories')->insert(['id' => 1, 'name' => 'Filter fixture', 'slug' => 'filter-fixture', 'is_active' => true, 'display_order' => 1]);
    foreach ([160, 170, 180, 190, 200, 210] as $index => $price) {
        DB::table('products')->insert(['id' => $index + 1, 'category_id' => 1, 'title' => 'Filter fixture '.$index, 'slug' => 'filter-fixture-'.$index, 'price' => $price, 'is_active' => true, 'in_stock' => true]);
    }
    $request = Illuminate\Http\Request::create('/shop', 'GET', ['category' => 'filter-fixture', 'min_price' => '170', 'max_price' => '200', 'in_stock' => '1']);
    $products = (new App\Http\Controllers\ShopController)->index($request)->getData()['products'];
    if ($products->total() !== 4 || ! $products->every(fn ($product) => $product->effective_price >= 170 && $product->effective_price <= 200)) {
        throw new RuntimeException('MySQL price-filter verification failed.');
    }
    echo "MySQL category, price and stock filtering verified using temporary tables.\n";
} finally {
    DB::statement('DROP TEMPORARY TABLE IF EXISTS products, categories');
    Cache::setDefaultDriver($cacheDriver);
}
if ($before !== $counts()) {
    throw new RuntimeException('Merchant record counts changed unexpectedly.');
}
App\Models\Setting::set('site_name', 'RJ Motorbike');
if (Artisan::call('platform:themes:activate', ['theme' => 'rjleather.theme-storefront']) !== 0) {
    throw new RuntimeException(Artisan::output());
}
echo Artisan::output();
if (Artisan::call('platform:extensions:validate') !== 0) {
    throw new RuntimeException(Artisan::output());
}
echo Artisan::output();
$compiler = $app->make('blade.compiler');
foreach (['default', 'rjleather'] as $theme) {
    $manifest = json_decode(file_get_contents($root.'/themes/'.$theme.'/composer.json'), true);
    foreach ($manifest['extra']['mavyn']['templates'] as $template => $path) {
        $path = $root.'/themes/'.$theme.'/'.$path;
        $compiler->compile($path);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($compiler->getCompiledPath($path)), $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Invalid compiled Blade: '.$template);
        }
    }
}
echo "Every default and RJ Motorbike template compiled and passed PHP syntax validation.\n";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
foreach (['/', '/shop', '/search', '/login', '/admin/login'] as $path) {
    $request = Illuminate\Http\Request::create('http://localhost'.$path);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Installed page verification failed: '.$path.' '.$response->getStatusCode());
    }
    $kernel->terminate($request, $response);
}
echo "Installed empty-store storefront, auth and admin pages returned HTTP 200.\n";
echo 'Merchant records preserved: '.json_encode($counts())."\n";
