<?php

// Creates/drops only a randomly named disposable database. No merchant table is used.
use App\Models\Admin;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\OrderPlacementService;
use App\Services\Pricing\CheckoutQuoteService;
use App\Services\SitemapService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\Payments;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n");
    exit(1);
});
$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'mavyn_payment_check_'.bin2hex(random_bytes(8));
if (! preg_match('/^mavyn_payment_check_[a-f0-9]{16}$/D', $database)) {
    throw new RuntimeException('Unsafe test database name.');
}
$directory = sys_get_temp_dir().'/'.$database;
if (! $worker) {
    File::makeDirectory($directory, 0700, true);
}
$server = config('database.connections.mysql');
$server['database'] = null;
config(['database.connections.payment_server' => $server, 'database.default' => 'mysql', 'database.connections.mysql.database' => $database,
    'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync',
    'extensions.catalog' => $directory.'/plugins.json', 'extensions.live_manifests' => false, 'themes.catalog' => $directory.'/themes.json']);
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
if ($worker) {
    auth('admin')->setUser(Admin::firstOrFail());
    $id = DB::table('gateway_payment_attempts')->value('id');
    try {
        if ($argv[3] === 'paid') {
            $context = app(Payments::class)->context($id);
            app(Payments::class)->apply('test.gateway.card', new PaymentResult($id, 'same-verified-event', 'paid', $context->amountMinor, $context->currency, 'payment-'.$id));
        } else {
            app(Payments::class)->requestRefund($id, (int) $argv[5], $argv[4]);
        }
        echo "accepted\n";
    } catch (HttpExceptionInterface $e) {
        echo 'rejected-'.$e->getStatusCode()."\n";
    }
    exit;
}
$children = [];
$created = false;
try {
    $manifest = json_decode(file_get_contents(base_path('tests/Fixtures/Gateway/composer.json')), true)['extra']['mavyn'];
    $base = ['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock'))];
    file_put_contents($directory.'/plugins.json', json_encode([...$base, 'packages' => [['package' => 'test/gateway', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Gateway', 'manifest' => $manifest]]], JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/themes.json', json_encode([...$base, 'packages' => []], JSON_THROW_ON_ERROR));
    DB::connection('payment_server')->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    Artisan::call('migrate', ['--force' => true]);
    DB::table('plugin_states')->insert(['plugin_id' => 'test.gateway', 'version' => '1.0.0', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('extension_runtime')->where('id', 1)->increment('revision');
    auth('admin')->setUser(Admin::create(['name' => 'Concurrency fixture', 'email' => 'concurrency@example.test', 'password' => 'fixture-only-password', 'role' => 'super_admin', 'is_active' => true]));
    auth('web')->setUser(User::factory()->create());
    $category = Category::create(['name' => 'Jackets']);
    $product = Product::create(['title' => 'Contended jacket', 'category_id' => $category->id, 'price' => '150.00', 'is_active' => true, 'in_stock' => true, 'inventory_mode' => 'shared', 'stock_quantity' => 5]);
    $product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
    DeliveryZone::create(['name' => 'Fixture delivery', 'delivery_fee' => '5.00', 'is_active' => true]);
    app(CartService::class)->add($product, 1);
    $input = ['idempotency_key' => app(CheckoutAttemptService::class)->issue(), 'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '03001234567',
        'country' => 'PK', 'city' => 'City', 'address_line' => 'Fixture street', 'shipping_option' => 'test.gateway.delivery:standard', 'payment_method' => 'fixture_card'];
    $input['review_token'] = app(CheckoutQuoteService::class)->issue($input)['review_token'];
    $order = app(OrderPlacementService::class)->place($input)->order;
    $run = function (array $one, array $two) use (&$children, $database): array {
        $pair = [];
        foreach ([$one, $two] as $arguments) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', $database, ...$arguments], base_path());
            $process->setTimeout(30);
            $process->start();
            $children[] = $process;
            $pair[] = $process;
        }
        $results = [];
        foreach ($pair as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Worker failed: '.$process->getErrorOutput().$process->getOutput());
            }
            $results[] = trim($process->getOutput());
        }
        sort($results);

        return $results;
    };
    if ($run(['paid'], ['paid']) !== ['accepted', 'accepted'] || DB::table('gateway_events')->count() !== 1 ||
        (int) $product->fresh()->stock_quantity !== 4 || DB::table('inventory_movements')->where('kind', 'consume')->count() !== 1 || $order->fresh()->payment_status !== 'paid') {
        throw new RuntimeException('Duplicate verified callbacks were not applied exactly once.');
    }
    $context = app(Payments::class)->context(DB::table('gateway_payment_attempts')->value('id'));
    foreach (['CaseSensitiveEvent', 'casesensitiveevent'] as $event) {
        app(Payments::class)->apply('test.gateway.card', new PaymentResult($context->attemptId, $event, 'paid', $context->amountMinor, $context->currency, 'payment-'.$context->attemptId));
    }
    if (DB::table('gateway_events')->count() !== 3) {
        throw new RuntimeException('Distinct case-sensitive provider events were conflated.');
    }
    $operation = (string) Str::uuid();
    $duplicateResults = $run(['refund', $operation, '15500'], ['refund', $operation, '15500']);
    if ($duplicateResults !== ['accepted', 'accepted'] || DB::table('gateway_refunds')->count() !== 1) {
        throw new RuntimeException('Duplicate refund request was not idempotent: '.json_encode($duplicateResults).'; rows='.DB::table('gateway_refunds')->count());
    }
    // Test-fixture cleanup only; no gateway refund has been executed.
    DB::table('gateway_refunds')->delete();
    if ($run(['refund', (string) Str::uuid(), '10000'], ['refund', (string) Str::uuid(), '10000']) !== ['accepted', 'rejected-422'] || DB::table('gateway_refunds')->count() !== 1) {
        throw new RuntimeException('Concurrent refund requests exceeded captured funds.');
    }
    echo json_encode(['result' => 'passed', 'contention_cases' => 3, 'identifier_cases' => 1, 'covered' => ['duplicate verified events', 'idempotent refunds', 'refund capacity', 'case-sensitive gateway identifiers']], JSON_THROW_ON_ERROR)."\n";
} finally {
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->stop();
        }
    }
    DB::disconnect('mysql');
    if ($created) {
        DB::connection('payment_server')->statement('DROP DATABASE `'.$database.'`');
    }
    File::deleteDirectory($directory);
}
