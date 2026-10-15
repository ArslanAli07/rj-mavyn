<?php

// Creates and drops only a randomly named disposable database. Never runs against merchant tables.
use App\Models\Admin;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\OrderPlacementService;
use App\Services\Pricing\CheckoutQuoteService;
use App\Services\Pricing\PricingService;
use App\Services\SitemapService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Pricing\PriceBook;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Pricing\PricingPlugin;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
});
$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'mavyn_currency_check_'.bin2hex(random_bytes(8));
if (! preg_match('/^mavyn_currency_check_[a-f0-9]{16}$/D', $database)) {
    throw new RuntimeException('Unsafe test database name.');
}
$directory = sys_get_temp_dir().'/'.$database;
if (! $worker) {
    File::makeDirectory($directory, 0700, true);
}
config(['cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync',
    'extensions.catalog' => $directory.'/plugins.json', 'themes.catalog' => $directory.'/themes.json', 'extensions.live_manifests' => false]);
$server = config('database.connections.mysql');
$server['database'] = null;
config(['database.connections.currency_server' => $server, 'database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
DB::purge('mysql');
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
$request = Request::create('/checkout/place-order', 'POST', server: ['REMOTE_ADDR' => '192.0.2.1']);
$request->setLaravelSession(app('session')->driver());
$app->instance('request', $request);

function currencyMutation(string $kind): void
{
    $prices = app(PriceBook::class);
    $revision = $prices->context()->revision;
    match ($kind) {
        'switch' => $prices->select('EUR', $revision, (string) Str::uuid()),
        'price' => $prices->save('USD', [['kind' => 'variant', 'entity_id' => 1, 'amount_minor' => 14500]], $revision, (string) Str::uuid()),
        'shipping' => $prices->save('USD', [['kind' => 'zone', 'entity_id' => 1, 'amount_minor' => 800]], $revision, (string) Str::uuid()),
        'coupon' => $prices->save('USD', [['kind' => 'coupon', 'entity_id' => 1, 'amount_minor' => 1500, 'minimum_minor' => 0]], $revision, (string) Str::uuid()),
        'tax' => Setting::set('store_tax_rate', '10.00'),
    };
}

if ($worker) {
    auth('admin')->setUser(Admin::firstOrFail());
    auth('web')->setUser(User::firstOrFail());
    session(['coupon_code' => 'SAVE']);
    $case = $argv[3];
    $role = $argv[4];
    $first = $argv[5] === 'first';
    try {
        $result = DB::transaction(function () use ($case, $role, $first, $directory) {
            app(PricingService::class)->lock($role === 'mutate');
            if ($first) {
                file_put_contents($directory.'/locked', 'ready');
                usleep(350000);
            }
            if ($role === 'mutate') {
                currencyMutation($case);

                return ['result' => 'updated'];
            }
            $input = json_decode(file_get_contents($directory.'/input.json'), true, 512, JSON_THROW_ON_ERROR);
            $placed = app(OrderPlacementService::class)->place($input);

            return ['result' => 'order', 'currency' => $placed->order->currency, 'id' => $placed->order->id];
        }, 3);
    } catch (ValidationException $e) {
        $result = ['result' => 'stale', 'errors' => array_keys($e->errors())];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
    exit;
}

$children = [];
try {
    $catalog = ['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => [[
        'package' => 'test/pricing', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Pricing',
        'manifest' => ['kind' => 'plugin', 'id' => 'test.pricing', 'name' => 'Pricing fixture', 'provider' => PricingPlugin::class, 'capabilities' => ['payment.provider', 'fulfillment.provider']],
    ]]];
    file_put_contents($directory.'/plugins.json', json_encode($catalog, JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/themes.json', json_encode(['format' => 1, 'lock_hash' => $catalog['lock_hash'], 'packages' => []], JSON_THROW_ON_ERROR));
    DB::connection('currency_server')->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    Artisan::call('migrate', ['--force' => true]);
    DB::table('plugin_states')->updateOrInsert(['plugin_id' => 'test.pricing'], ['active' => true, 'version' => '1.0.0', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('extension_runtime')->where('id', 1)->increment('revision');
    $actor = Admin::create(['name' => 'Currency fixture', 'email' => 'currency@example.test', 'password' => 'fixture-only-password', 'role' => 'super_admin', 'is_active' => true]);
    $user = User::factory()->create();
    auth('admin')->setUser($actor);
    auth('web')->setUser($user);
    $category = Category::create(['name' => 'Currency fixture']);
    $product = Product::create(['title' => 'Contended item', 'category_id' => $category->id, 'price' => '150.00', 'is_active' => true, 'in_stock' => true, 'inventory_mode' => 'shared', 'stock_quantity' => 30]);
    $product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
    DeliveryZone::create(['name' => 'Fixture zone', 'delivery_fee' => '5.00', 'is_active' => true]);
    Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => '10.00', 'minimum_order' => 0, 'usage_limit' => 1000, 'per_user_limit' => 1000, 'is_active' => true]);
    $prices = app(PriceBook::class);
    $prices->enable(['PKR', 'USD', 'EUR'], $prices->context()->revision, (string) Str::uuid());
    foreach (['USD', 'EUR'] as $currency) {
        $prices->save($currency, [['kind' => 'variant', 'entity_id' => 1, 'amount_minor' => 15000], ['kind' => 'zone', 'entity_id' => 1, 'amount_minor' => 500],
            ['kind' => 'coupon', 'entity_id' => 1, 'amount_minor' => 1000, 'minimum_minor' => 0]], $prices->context()->revision, (string) Str::uuid());
    }
    $cases = 0;
    foreach (['switch', 'price', 'shipping', 'coupon', 'tax'] as $case) {
        foreach (['checkout', 'mutate'] as $firstRole) {
            fwrite(STDERR, 'Checking '.$case.' with '.$firstRole." first\n");
            app(PricingService::class)->forget();
            $prices->select('USD', $prices->context()->revision, (string) Str::uuid());
            $prices->save('USD', [['kind' => 'variant', 'entity_id' => 1, 'amount_minor' => 15000], ['kind' => 'zone', 'entity_id' => 1, 'amount_minor' => 500],
                ['kind' => 'coupon', 'entity_id' => 1, 'amount_minor' => 1000, 'minimum_minor' => 0]], $prices->context()->revision, (string) Str::uuid());
            Setting::set('store_tax_rate', '0.00');
            session(['coupon_code' => 'SAVE']);
            $cart = app(CartService::class);
            $cart->clear();
            $cart->add($product->fresh(), 1);
            $input = ['idempotency_key' => app(CheckoutAttemptService::class)->issue(), 'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '03001234567',
                'country' => 'PK', 'city' => 'City', 'address_line' => 'Fixture street', 'shipping_option' => 'test.pricing.delivery:standard', 'payment_method' => 'fixture_manual'];
            $input['review_token'] = app(CheckoutQuoteService::class)->issue($input)['review_token'];
            file_put_contents($directory.'/input.json', json_encode($input, JSON_THROW_ON_ERROR));
            if (is_file($directory.'/locked')) {
                unlink($directory.'/locked');
            }
            $before = ['orders' => DB::table('orders')->count(), 'stock' => DB::table('products')->where('id', 1)->value('reserved_quantity'), 'usages' => DB::table('coupon_usages')->count(), 'notifications' => DB::table('notification_outbox')->count()];
            $secondRole = $firstRole === 'checkout' ? 'mutate' : 'checkout';
            $first = new Process([PHP_BINARY, __FILE__, 'worker', $database, $case, $firstRole, 'first'], base_path());
            $first->setTimeout(30);
            $first->start();
            $children[] = $first;
            $deadline = microtime(true) + 10;
            while (! is_file($directory.'/locked') && $first->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (! is_file($directory.'/locked')) {
                throw new RuntimeException('First worker never acquired the root lock: '.$first->getErrorOutput().$first->getOutput());
            }
            $second = new Process([PHP_BINARY, __FILE__, 'worker', $database, $case, $secondRole, 'second'], base_path());
            $second->setTimeout(30);
            $second->start();
            $children[] = $second;
            $first->wait();
            $second->wait();
            foreach ([$first, $second] as $process) {
                if (! $process->isSuccessful()) {
                    throw new RuntimeException('Worker failed: '.$process->getErrorOutput().$process->getOutput());
                }
            }
            $placed = json_decode(($firstRole === 'checkout' ? $first : $second)->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $success = $firstRole === 'checkout';
            if (($placed['result'] ?? '') !== ($success ? 'order' : 'stale')) {
                throw new RuntimeException('Unexpected contention result for '.$case.' / '.$firstRole.': '.json_encode($placed));
            }
            if (DB::table('orders')->count() !== $before['orders'] + ($success ? 1 : 0) ||
                (int) DB::table('products')->where('id', 1)->value('reserved_quantity') !== (int) $before['stock'] + ($success ? 1 : 0) ||
                DB::table('coupon_usages')->count() !== $before['usages'] + ($success ? 1 : 0) ||
                DB::table('notification_outbox')->count() !== $before['notifications'] + ($success ? 1 : 0)) {
                throw new RuntimeException('Order/stock/coupon/outbox effects did not match contention result.');
            }
            if ($success) {
                $order = DB::table('orders')->where('id', $placed['id'])->first();
                if ($order->currency !== 'USD' || $order->grand_total !== '145.00') {
                    throw new RuntimeException('Mixed monetary terms committed.');
                }
                $replay = app(OrderPlacementService::class)->place($input);
                if (! $replay->replayed || $replay->order->id !== $placed['id']) {
                    throw new RuntimeException('Committed checkout did not replay.');
                }
            }
            $cases++;
        }
    }
    echo json_encode(['result' => 'passed', 'contention_cases' => $cases, 'covered' => ['switch', 'price', 'shipping', 'coupon', 'tax', 'stock/coupon/outbox effects', 'committed replay']], JSON_THROW_ON_ERROR)."\n";
} finally {
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->stop(1);
        }
    }
    DB::purge('mysql');
    DB::connection('currency_server')->statement('DROP DATABASE IF EXISTS `'.$database.'`');
    File::deleteDirectory($directory);
}
