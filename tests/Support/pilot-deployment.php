<?php

// Offline, synthetic two-client release rehearsal. Never copies live client data or connects to the merchant database.
use App\Models\DeliveryZone;
use App\Models\NotificationOutbox;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Deployment\ReleaseManifest;
use App\Services\Deployment\ReleasePreflight;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Pilots\PilotCatalog;

if (($argv[1] ?? '') === 'worker') {
    $release = $argv[2];
    $client = $argv[3];
    $profile = $argv[4];
    $operation = $argv[5];
    require $release.'/vendor/autoload.php';
    $app = require $release.'/bootstrap/app.php';
    $app->useEnvironmentPath($client);
    $app->useStoragePath($client.'/storage');
    $app->usePublicPath($client.'/public');
    $app->make(Kernel::class)->bootstrap();
    require dirname(__DIR__).'/Fixtures/Pilots/PilotCatalog.php';
    $assert = function (bool $condition, string $message): void {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    };
    $cookies = [];
    $http = function (string $path, string $method = 'GET', array $data = [], array $files = [], ?array $foreignCookies = null) use ($app, &$cookies) {
        $app->forgetScopedInstances();
        $app['auth']->forgetGuards();
        $app['auth']->shouldUse('web');
        $app['session']->forgetDrivers();
        $request = Request::create(config('app.url').$path, $method, $data, $foreignCookies ?? $cookies, $files, ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1']);
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        if ($foreignCookies === null) {
            foreach ($response->headers->getCookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    };
    $view = function (string $path) use ($http, $assert): string {
        $response = $http($path);
        $assert($response->getStatusCode() === 200, 'Page failed: '.$path.' / '.$response->getStatusCode());

        return $response->getContent();
    };
    $order = function (int $sequence) use ($http, $assert, $view, $profile) {
        $product = Product::sole();
        $values = $product->optionGroups()->with('values')->get()->map(fn ($group) => $group->values->first()->id)->all();
        $response = $http('/cart/add', 'POST', ['product_id' => $product->id, 'product_variant_id' => $product->variants()->first()->id, 'option_value_ids' => $values, 'quantity' => 2]);
        $assert($response->getStatusCode() === 200, 'Cart failed');
        $assert($http('/cart/coupon', 'POST', ['code' => 'PILOT'])->getStatusCode() === 200, 'Coupon failed');
        $view('/checkout');
        // The persisted session token comes from the real checkout GET.
        $key = session('checkout_key');
        $receiptFile = tempnam(sys_get_temp_dir(), 'mavyn-pilot-receipt-');
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $receiptFile);
        imagedestroy($image);
        $receipt = new UploadedFile($receiptFile, 'receipt.png', 'image/png', null, true);
        $data = ['idempotency_key' => $key, 'first_name' => 'Pilot', 'last_name' => 'Buyer', 'email' => $profile.'-'.$sequence.'@example.test', 'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot', 'delivery_zone_id' => DeliveryZone::sole()->id, 'address_line' => 'Fixture Street', 'payment_method' => 'easypaisa', config('honeypot.name_field', 'website') => '', config('honeypot.valid_from_field', '_form_started_at') => Crypt::encryptString((string) now()->subSeconds(5)->timestamp)];
        try {
            $response = $http('/checkout/place-order', 'POST', $data, ['payment_receipt' => $receipt]);
        } finally {
            unlink($receiptFile);
        }
        $assert($response->getStatusCode() === 200, 'Checkout failed: '.$response->getContent());
        $created = Order::latest('id')->firstOrFail();
        $assert($created->grand_total === '261.10', 'Checkout total changed');
        $assert($http('/checkout/place-order', 'POST', $data)->getStatusCode() === 200, 'Checkout replay failed');
        $assert(Order::count() === $sequence, 'Replay duplicated an order');
        $assert(str_contains($view('/orders/track/'.$created->tracking_token), $product->title), 'Purchased snapshot missing');

        return $created;
    };
    try {
        if ($operation === 'seal') {
            $manifest = app(ReleaseManifest::class)->build($argv[6]);
            file_put_contents($release.'/bootstrap/cache/release.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            echo json_encode(['source_hash' => $manifest['source_hash']]);
        } elseif ($operation === 'init') {
            File::copyDirectory($release.'/public/build', $client.'/public/build');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('platform:themes:assets');
            ['admin' => $admin, 'category' => $category] = PilotCatalog::configure($profile);
            $assert($http('/admin/login', 'POST', ['email' => $admin->email, 'password' => 'fixture-password-only'])->getStatusCode() < 400, 'Admin login failed');
            $response = $http('/admin/products', 'POST', PilotCatalog::product($profile, $category->id));
            $assert($response->getStatusCode() < 400 && Product::count() === 1, 'Product create failed: '.$response->getContent());
            $assert(str_contains($view('/shop'), Product::sole()->title), 'Catalog missing');
            $first = $order(1);
            $assert(Product::sole()->reserved_quantity === 2, 'Stock reservation missing');
            file_put_contents($client.'/proof.json', json_encode(['tracking_token' => $first->tracking_token, 'cookies' => $cookies, 'receipt' => $first->payment_receipt]));
            echo json_encode(['profile' => $profile, 'orders' => 1, 'display' => format_price('125.55')]);
        } elseif ($operation === 'backup') {
            $directory = $client.'/backup';
            File::ensureDirectoryExists($directory);
            $database = $directory.'/database.sqlite';
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($database));
            $restored = new PDO('sqlite:'.$database);
            $assert((int) $restored->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1, 'Backup restore did not retain baseline order');
            $assert($restored->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'Backup integrity failed');
            $proof = json_decode(file_get_contents($client.'/proof.json'), true);
            $receipt = Storage::disk('local')->get($proof['receipt']);
            file_put_contents($directory.'/receipt.png', $receipt);
            copy($client.'/.env', $directory.'/environment.backup');
            $files = [];
            foreach (['database.sqlite', 'receipt.png', 'environment.backup'] as $name) {
                $files[$name] = hash_file('sha256', $directory.'/'.$name);
            }
            file_put_contents($directory.'/backup.json', json_encode(['format' => 1, 'client' => config('platform.client'), 'scope_hash' => app(ReleasePreflight::class)->scopeHash(), 'created_at' => time(), 'restore_verified' => true, 'files' => $files]));
            echo json_encode(['restored_orders' => 1, 'receipt_preserved' => true]);
        } elseif ($operation === 'upgrade') {
            File::copyDirectory($release.'/public/build', $client.'/public/build');
            Artisan::call('platform:themes:assets');
            $assert(format_price('125.55') === 'Rs. 125.55', 'Shared price fix missing');
            $assert(str_contains($view('/products/'.Product::sole()->slug), 'Rs. 125.55'), 'Storefront price fix missing');
            $order(2);
            $assert(Product::sole()->reserved_quantity === 4, 'Stock changed during deployment');
            $assert(NotificationOutbox::count() === 2, 'Outbox changed during deployment');
            $report = app(ReleasePreflight::class)->check($release.'/bootstrap/cache/release.json', $client.'/backup/backup.json');
            $assert($report['outcome'] === 'verified', 'Preflight failed: '.json_encode($report['checks']));
            app(ReleasePreflight::class)->record($report);
            echo json_encode(['source_hash' => $report['source_hash'], 'release' => $report['release'], 'orders' => 2, 'theme' => DB::table('theme_selection')->value('active_theme'), 'brand' => Setting::get('site_name')]);
        } elseif ($operation === 'recover') {
            File::copyDirectory($release.'/public/build', $client.'/public/build');
            $assert(Order::count() === 2, 'Code recovery lost post-backup order');
            $assert(NotificationOutbox::count() === 2, 'Code recovery lost messages');
            $proof = json_decode(file_get_contents($client.'/proof.json'), true);
            $assert(Storage::disk('local')->exists($proof['receipt']), 'Code recovery lost receipt');
            $report = app(ReleasePreflight::class)->check($release.'/bootstrap/cache/release.json', $client.'/backup/backup.json');
            $assert($report['outcome'] === 'verified', 'Recovered release failed preflight');
            app(ReleasePreflight::class)->record($report);
            $restored = new PDO('sqlite:'.$client.'/backup/database.sqlite');
            $assert((int) $restored->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1, 'Backup did not expose missing newer transaction');
            echo json_encode(['release' => $report['release'], 'retained_orders' => 2, 'backup_orders' => 1, 'uncovered_orders' => 1]);
        } elseif ($operation === 'isolation') {
            $other = json_decode(file_get_contents($argv[6].'/proof.json'), true);
            $assert($http('/orders/track/'.$other['tracking_token'])->getStatusCode() === 404, 'Other-client tracking token worked');
            $assert($http('/checkout/payment-instructions/1', foreignCookies: $other['cookies'])->getStatusCode() === 403, 'Other-client cookie accessed an order');
            $renamedCookies = [];
            foreach ($other['cookies'] as $name => $value) {
                $renamedCookies[str_ends_with($name, '_session') ? config('session.cookie') : $name] = $value;
            }
            $assert($http('/checkout/payment-instructions/1', foreignCookies: $renamedCookies)->getStatusCode() === 403, 'Renamed other-client cookie accessed an order');
            $assert(! Storage::disk('local')->exists($other['receipt']), 'Other-client receipt shared');
            echo json_encode(['cross_client_access' => 'rejected']);
        } elseif ($operation === 'failed') {
            $manifest = json_decode(file_get_contents($release.'/bootstrap/cache/release.json'), true);
            $manifest['files']['app/Helpers/helpers.php'] = str_repeat('0', 64);
            $path = $client.'/invalid-release.json';
            file_put_contents($path, json_encode($manifest));
            $report = app(ReleasePreflight::class)->check($path, $client.'/backup/backup.json');
            $assert($report['outcome'] === 'failed' && $report['release'] === null, 'Unknown release reported successful');
            app(ReleasePreflight::class)->record($report);
            $assert(Order::count() === 2, 'Failed preflight changed orders');
            echo json_encode(['outcome' => 'failed', 'checks' => $report['checks']]);
        } else {
            throw new RuntimeException('Unknown rehearsal operation');
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, $operation.': '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
    exit(0);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = dirname(__DIR__, 2);
$directory = sys_get_temp_dir().'/mavyn-pilot-rehearsal-'.bin2hex(random_bytes(8));
mkdir($directory, 0700, true);
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$clients = [];
foreach (['beverage', 'stationery'] as $profile) {
    $client = $directory.'/'.$profile;
    $clients[$profile] = $client;
    foreach (['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/private', 'storage/app/public', 'public'] as $path) {
        mkdir($client.'/'.$path, 0700, true);
    }
    touch($client.'/database.sqlite');
    $id = $profile.'-pilot';
    $env = ['APP_NAME' => $id, 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_URL' => 'http://'.$id.'.test', 'MAVYN_CLIENT_ID' => $id, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $client.'/database.sqlite', 'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => $id.'_session', 'SESSION_DOMAIN' => 'null', 'CACHE_STORE' => 'database', 'CACHE_PREFIX' => $id.':', 'QUEUE_CONNECTION' => 'database', 'DB_QUEUE' => $id, 'NOTIFICATION_QUEUE_CONNECTION' => 'database', 'NOTIFICATION_QUEUE' => $id, 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'single', 'APP_CONFIG_CACHE' => $client.'/bootstrap/cache/config.php', 'APP_ROUTES_CACHE' => $client.'/bootstrap/cache/routes.php', 'APP_EVENTS_CACHE' => $client.'/bootstrap/cache/events.php', 'VIEW_COMPILED_PATH' => $client.'/storage/framework/views'];
    $env += ['DB_URL' => '', 'DB_CACHE_CONNECTION' => 'sqlite', 'DB_CACHE_LOCK_CONNECTION' => 'sqlite', 'DB_QUEUE_CONNECTION' => 'sqlite', 'SESSION_CONNECTION' => 'sqlite', 'APP_PREVIOUS_KEYS' => '', 'FILESYSTEM_DISK' => 'local'];
    file_put_contents($client.'/.env', implode("\n", array_map(fn ($key, $value) => $key.'='.$value, array_keys($env), $env))."\n");
    chmod($client.'/.env', 0600);
}
$run = function (string $release, string $profile, string $operation, ?string $argument = null) use ($clients, $root): array {
    $client = $clients[$profile];
    $environment = [];
    foreach (file($client.'/.env', FILE_IGNORE_NEW_LINES) as $line) {
        [$name, $value] = explode('=', $line, 2);
        $environment[$name] = $value;
    }
    // Explicit environment overrides ensure no shell-provided live connection/mail configuration is used.
    $environment['SESSION_DOMAIN'] = '(null)';
    $command = [PHP_BINARY, __FILE__, 'worker', $release, $client, $profile, $operation];
    if ($argument !== null) {
        $command[] = $argument;
    }
    $process = new Process($command, $root, $environment);
    $process->setTimeout(90);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
};
$exitCode = 0;
try {
    // Inventory is built by the current host, without importing its database or environment into either client.
    $inventoryPath = $directory.'/inventory.json';
    $process = new Process([PHP_BINARY, $root.'/artisan', 'platform:release:manifest', 'pilot-candidate', '--output='.$inventoryPath], $root);
    $process->run();
    $check($process->isSuccessful(), 'Inventory build failed');
    $inventory = json_decode(file_get_contents($inventoryPath), true);
    foreach (['baseline', 'candidate'] as $kind) {
        $target = $directory.'/'.$kind;
        foreach ($inventory['files'] as $name => $hash) {
            $destination = $target.'/'.$name;
            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0700, true);
            }
            copy($root.'/'.$name, $destination);
        }
        mkdir($target.'/bootstrap/cache', 0700, true);
        foreach (['extensions.json', 'themes.json'] as $name) {
            copy($root.'/bootstrap/cache/'.$name, $target.'/bootstrap/cache/'.$name);
        }
        $copy = new Process(['cp', '-a', $root.'/vendor', $target.'/vendor']);
        $copy->setTimeout(90);
        $copy->mustRun();
    }
    // Fixture baseline reproduces the previous rounding defect; no client source customization is applied.
    copy($root.'/tests/Fixtures/Pilots/baseline-money.stub', $directory.'/baseline/packages/core/src/Money.php');
    $helper = $directory.'/baseline/app/Helpers/helpers.php';
    $source = file_get_contents($helper);
    $source = preg_replace('/return (?:[A-Za-z\\\\]+)?Money::format\([^;]+;/', "return 'Rs. '.number_format((float) \$amount, 0);", $source, 1, $replaced);
    $check($replaced === 1, 'Baseline display adapter did not match');
    file_put_contents($helper, $source);
    $baseline = $directory.'/baseline';
    $candidate = $directory.'/candidate';
    $run($baseline, 'beverage', 'seal', 'pilot-baseline');
    $run($candidate, 'beverage', 'seal', 'pilot-candidate');
    foreach (array_keys($clients) as $profile) {
        $init[$profile] = $run($baseline, $profile, 'init');
        $check($init[$profile]['display'] === 'Rs. 126', 'Baseline does not reproduce rounding');
        $run($baseline, $profile, 'backup');
    }
    $upgrades = [];
    foreach (array_keys($clients) as $profile) {
        $upgrades[$profile] = $run($candidate, $profile, 'upgrade');
    }
    $check($upgrades['beverage']['source_hash'] === $upgrades['stationery']['source_hash'], 'Pilots received different source releases');
    foreach (array_keys($clients) as $profile) {
        $run($candidate, $profile, 'isolation', $clients[$profile === 'beverage' ? 'stationery' : 'beverage']);
        $run($candidate, $profile, 'failed');
        $recovery[$profile] = $run($baseline, $profile, 'recover');
    }
    echo json_encode(['result' => 'passed', 'scope' => 'offline isolated SQLite clients; synthetic data; mirrored release artifacts', 'shared_source_hash' => $upgrades['beverage']['source_hash'], 'profiles' => $upgrades, 'recovery' => $recovery, 'checks' => ['shared cents fix', 'client brand/theme/plugin preservation', 'configured checkout/receipt/coupon/stock/outbox', 'cross-client cookies/tracking/storage rejected', 'failed preflight recorded', 'code recovery retains post-backup orders', 'restored backup identifies uncovered transaction']], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    if (in_array('--keep', $argv, true)) {
        fwrite(STDERR, 'Synthetic rehearsal files retained at '.$directory.PHP_EOL);
    } else {
        (new Filesystem)->deleteDirectory($directory);
    }
}
exit($exitCode);
