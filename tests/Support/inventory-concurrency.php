<?php

// Explicit integration check: creates and drops only its randomly named local test database.
// Run: php tests/Support/inventory-concurrency.php
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutAttemptService;
use App\Services\InventoryService;
use App\Services\OrderPlacementService;
use App\Services\OrderStateService;
use App\Services\SitemapService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);

$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'mavyn_inventory_check_'.bin2hex(random_bytes(8));
if (! preg_match('/^mavyn_inventory_check_[a-f0-9]{16}$/', $database)) {
    throw new RuntimeException('Refusing an unsafe test database name.');
}
$directory = $worker ? $argv[3] : sys_get_temp_dir().'/'.$database;
if (! $worker) {
    File::makeDirectory($directory, 0700, true);
}
$app->instance(SitemapService::class, new class($directory) extends SitemapService
{
    public function __construct(private string $directory) {}

    public function sitemapPath(): string
    {
        return $this->directory.'/sitemap.xml';
    }

    public function robotsPath(): string
    {
        return $this->directory.'/robots.txt';
    }
});
$serverConfig = config('database.connections.mysql');
$serverConfig['database'] = null;
config(['database.connections.inventory_server' => $serverConfig]);
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
DB::purge('mysql');

if ($worker) {
    $operation = $argv[4];
    $id = (int) $argv[5];
    $slot = $argv[6];
    $app->instance(InventoryService::class, new class extends InventoryService
    {
        public function lockProducts(array $ids): Collection
        {
            $products = parent::lockProducts($ids);
            usleep(300000); // Hold the row locks so the competing process must contend.

            return $products;
        }
    });
    file_put_contents($directory.'/ready-'.$slot, 'ready');
    $deadline = microtime(true) + 15;
    while (! is_file($directory.'/go-'.$operation) && microtime(true) < $deadline) {
        usleep(10000);
    }
    try {
        if ($operation === 'checkout') {
            auth('web')->loginUsingId($id);
            $zone = DeliveryZone::firstOrFail();
            app(OrderPlacementService::class)->place(['idempotency_key' => app(CheckoutAttemptService::class)->issue(),
                'first_name' => 'Concurrent', 'last_name' => 'Buyer',
                'email' => 'buyer@example.com', 'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot',
                'delivery_zone_id' => $zone->id, 'address_line' => 'Street', 'payment_method' => 'cod']);
            echo 'placed';
        } else {
            app(OrderStateService::class)->updateMany([$id], ['status' => 'cancelled'], null);
            echo 'cancelled';
        }
    } catch (ValidationException $exception) {
        if ($operation !== 'checkout' || ! isset($exception->errors()['cart'])) {
            throw $exception;
        }
        echo 'stock_rejected';
    }
    exit(0);
}

$server = DB::connection('inventory_server');
$created = false;
$processes = [];
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$race = function (string $operation, array $ids, string $phase) use ($database, $directory, &$processes, $check): array {
    $pair = [];
    foreach ($ids as $index => $id) {
        $slot = $phase.'-'.$index;
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $database, $directory, $operation, (string) $id, $slot]);
        $process->setTimeout(20)->start();
        $pair[$slot] = $process;
        $processes[] = $process;
    }
    $deadline = microtime(true) + 15;
    foreach ($pair as $slot => $process) {
        while (! is_file($directory.'/ready-'.$slot) && $process->isRunning() && microtime(true) < $deadline) {
            usleep(10000);
        }
        $check(is_file($directory.'/ready-'.$slot), 'A concurrency worker failed to reach the barrier.');
    }
    file_put_contents($directory.'/go-'.$operation, 'go');
    $results = [];
    foreach ($pair as $process) {
        $process->wait();
        $check($process->isSuccessful(), 'Concurrency worker failed: '.$process->getErrorOutput());
        $results[] = trim($process->getOutput());
    }
    unlink($directory.'/go-'.$operation);
    sort($results);

    return $results;
};

$exitCode = 0;
try {
    $server->statement('CREATE DATABASE `'.$database.'`');
    $created = true;
    Artisan::call('migrate', ['--force' => true]);
    $category = Category::create(['name' => 'Concurrency fixture']);
    DeliveryZone::create(['name' => 'Test zone', 'delivery_fee' => 0, 'is_active' => true]);
    Setting::set('email_order_confirmation', '0', 'email', 'boolean');
    foreach (['shared', 'variant'] as $mode) {
        $product = Product::create(['category_id' => $category->id, 'title' => 'Last unit '.$mode, 'price' => 500,
            'inventory_mode' => $mode, 'stock_quantity' => 1, 'is_active' => true, 'in_stock' => true]);
        $variant = $product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 500,
            'is_active' => true, 'is_default' => true, 'track_inventory' => true, 'stock_quantity' => 1]);
        $ids = [];
        foreach ([0, 1] as $index) {
            $user = User::create(['name' => 'Fixture', 'email' => $mode.$index.'@example.com', 'password' => 'temporary-fixture-password']);
            $ids[] = $user->id;
            CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
                'quantity' => 1, 'price' => 500, 'weight' => 'Standard']);
        }
        $check($race('checkout', $ids, $mode.'-buy') === ['placed', 'stock_rejected'], 'Exactly one purchase must win the last unit.');
        $order = Order::whereHas('items', fn ($items) => $items->where('product_id', $product->id))->firstOrFail();
        $source = $mode === 'shared' ? $product->fresh() : $variant->fresh();
        $check($source->stock_quantity === 1 && $source->reserved_quantity === 1, 'Last-unit stock counters are incorrect.');
        $check(InventoryReservation::where('order_id', $order->id)->count() === 1, 'Reservation was duplicated.');
        $check(CartItem::where('product_id', $product->id)->count() === 1, 'Rejected customer lost the cart.');
        $check($race('cancel', [$order->id, $order->id], $mode.'-cancel') === ['cancelled', 'cancelled'], 'Concurrent cancellation failed.');
        $source->refresh();
        $reservation = InventoryReservation::where('order_id', $order->id)->firstOrFail();
        $check($source->stock_quantity === 1 && $source->reserved_quantity === 0, 'Concurrent cancellation changed stock twice.');
        $check(InventoryMovement::where('inventory_reservation_id', $reservation->id)->where('kind', 'release')->count() === 1, 'Cancellation was audited twice.');
    }
    echo json_encode(['result' => 'passed', 'database' => 'disposable MySQL', 'scenarios' => [
        'concurrent shared-stock checkout', 'concurrent variant-stock checkout', 'concurrent cancellation for both stock modes'],
    ], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
        }
    }
    DB::purge('mysql');
    if ($created) {
        $server->statement('DROP DATABASE `'.$database.'`');
    }
    File::deleteDirectory($directory);
}
exit($exitCode);
