<?php

// Explicit integration check: creates and drops only its randomly named local test database.
// Run: php tests/Support/checkout-concurrency.php
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Coupon;
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
use App\Services\SitemapService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);

$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'mavyn_checkout_check_'.bin2hex(random_bytes(8));
if (! preg_match('/^mavyn_checkout_check_[a-f0-9]{16}$/', $database)) {
    throw new RuntimeException('Refusing an unsafe test database name.');
}
$directory = $worker ? $argv[3] : sys_get_temp_dir().'/'.$database;
if (! $worker) {
    File::makeDirectory($directory, 0700, true);
}
config(['filesystems.disks.local.root' => $directory.'/receipts']);
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
config(['database.connections.checkout_server' => $serverConfig]);
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
        auth('web')->loginUsingId($id);
        session(['coupon_code' => 'SAVE']);
        $zone = DeliveryZone::firstOrFail();
        $result = app(OrderPlacementService::class)->place(['idempotency_key' => $argv[7],
            'first_name' => 'Concurrent', 'last_name' => 'Buyer', 'email' => 'buyer@example.com',
            'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot',
            'delivery_zone_id' => $zone->id, 'address_line' => 'Street', 'payment_method' => 'bank_transfer'],
            new UploadedFile($directory.'/receipt.png', 'receipt.png', 'image/png', null, true));
        echo json_encode(['order_id' => $result->order->id, 'replayed' => $result->replayed], JSON_THROW_ON_ERROR);
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['cart'])) {
            throw $exception;
        }
        echo json_encode(['cart_rejected' => true], JSON_THROW_ON_ERROR);
    }
    exit(0);
}

$server = DB::connection('checkout_server');
$created = false;
$processes = [];
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$race = function (string $operation, array $keys, int $customerId) use ($database, $directory, &$processes, $check): array {
    $pair = [];
    foreach ($keys as $index => $key) {
        $slot = $operation.'-'.$index;
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $database, $directory, $operation, (string) $customerId, $slot, $key]);
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
        $results[] = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    }
    unlink($directory.'/go-'.$operation);

    return $results;
};

$exitCode = 0;
try {
    $server->statement('CREATE DATABASE `'.$database.'`');
    $created = true;
    Artisan::call('migrate', ['--force' => true]);
    $category = Category::create(['name' => 'Checkout fixture']);
    $zone = DeliveryZone::create(['name' => 'Test zone', 'delivery_fee' => 0, 'is_active' => true]);
    Setting::set('email_order_confirmation', '0', 'email', 'boolean');
    $coupon = Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => 10, 'minimum_order' => 0,
        'is_active' => true, 'usage_limit' => 10, 'per_user_limit' => 10]);
    file_put_contents($directory.'/receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
    $fixture = function (string $phase) use ($category): array {
        $product = Product::create(['category_id' => $category->id, 'title' => 'Checkout '.$phase, 'price' => 500,
            'inventory_mode' => 'shared', 'stock_quantity' => 5, 'is_active' => true, 'in_stock' => true]);
        $variant = $product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 500, 'is_default' => true]);
        $user = User::create(['name' => 'Fixture', 'email' => $phase.'@example.com', 'password' => 'temporary-fixture-password']);
        auth('web')->login($user);
        session(['coupon_code' => 'SAVE']);
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'quantity' => 2, 'price' => 500, 'weight' => 'Standard']);

        return [$product, $user, app(CheckoutAttemptService::class)->issue()];
    };
    foreach (['same-key', 'different-keys'] as $phase) {
        [$product, $user, $key] = $fixture($phase);
        $secondKey = $key;
        if ($phase === 'different-keys') {
            session()->forget('checkout_key');
            $secondKey = app(CheckoutAttemptService::class)->issue();
        }
        $results = $race($phase, [$key, $secondKey], $user->id);
        $orders = Order::where('user_id', $user->id)->get();
        $check($orders->count() === 1, 'Concurrent checkout created duplicate orders.');
        $order = $orders->first();
        if ($phase === 'same-key') {
            $check(array_column($results, 'order_id') === [$order->id, $order->id], 'Same-key retries did not return the same order.');
            $check(count(array_filter($results, fn ($result) => $result['replayed'])) === 1, 'Exactly one request must replay.');
        } else {
            $check(count(array_filter($results, fn ($result) => $result['cart_rejected'] ?? false)) === 1, 'Second draft did not reject the consumed cart.');
        }
        $check($product->fresh()->reserved_quantity === 2, 'Stock was reserved twice.');
        $check($product->fresh()->sales_count === 2, 'Sales count was duplicated.');
        $check(InventoryReservation::where('order_id', $order->id)->count() === 1, 'Allocation was duplicated.');
        $check(InventoryMovement::where('stock_type', 'product')->where('stock_id', $product->id)->count() === 1, 'Movement was duplicated.');
        $check($coupon->usages()->where('order_id', $order->id)->count() === 1, 'Coupon usage was duplicated.');
        $check(CartItem::where('user_id', $user->id)->count() === 0, 'Committed cart was not removed.');
    }
    // Force an exception after the database commits, before HTTP response/notification work.
    [$product, $user, $key] = $fixture('after-commit');
    $data = ['idempotency_key' => $key, 'first_name' => 'Concurrent', 'last_name' => 'Buyer',
        'email' => 'buyer@example.com', 'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot',
        'delivery_zone_id' => $zone->id, 'address_line' => 'Street', 'payment_method' => 'bank_transfer'];
    $app->instance(InventoryService::class, new class extends InventoryService
    {
        public function lockProducts(array $ids): Collection
        {
            $products = parent::lockProducts($ids);
            DB::afterCommit(fn () => throw new RuntimeException('Injected failure after commit'));

            return $products;
        }
    });
    try {
        app(OrderPlacementService::class)->place($data, new UploadedFile($directory.'/receipt.png', 'receipt.png', 'image/png', null, true));
        throw new RuntimeException('Expected the after-commit interruption.');
    } catch (RuntimeException $exception) {
        $check($exception->getMessage() === 'Injected failure after commit', 'Unexpected checkout failure.');
    }
    $order = Order::where('user_id', $user->id)->firstOrFail();
    $check(CheckoutAttempt::where('order_id', $order->id)->whereNotNull('committed_at')->exists(), 'Commit marker was lost.');
    $check(Storage::disk('local')->exists($order->payment_receipt), 'Committed receipt was deleted after response failure.');
    $app->instance(InventoryService::class, new InventoryService);
    $replay = app(OrderPlacementService::class)->place($data);
    $check($replay->replayed && $replay->order->id === $order->id, 'Retry after commit did not recover the order.');
    $check(Order::count() === 3 && $coupon->fresh()->usage_count === 3, 'Order/coupon counts differ from successful checkouts.');
    $check(count(Storage::disk('local')->allFiles()) === 3, 'Receipts were duplicated or lost.');
    echo json_encode(['result' => 'passed', 'database' => 'disposable MySQL', 'scenarios' => [
        'simultaneous requests using the same checkout key', 'different keys competing for one cart',
        'exception after commit preserves receipt and replays original order'],
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
