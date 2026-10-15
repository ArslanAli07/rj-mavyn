<?php

// Isolated manual browser fixture. Does not read or migrate the merchant database.
use App\Models\Admin;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Setting;
use App\Services\SitemapService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mavyn\Contracts\Pricing\PriceBook;
use Tests\Fixtures\Pricing\PricingPlugin;

$mode = PHP_SAPI === 'cli' ? ($argv[1] ?? 'init') : 'serve';
$directory = $mode === 'init' ? sys_get_temp_dir().'/mavyn-currency-browser-'.bin2hex(random_bytes(8)) : ($argv[2] ?? getenv('MAVYN_CURRENCY_BROWSER_FIXTURE'));
if (! is_string($directory) || ! preg_match('~^/tmp/mavyn-currency-browser-[a-f0-9]{16}$~D', $directory)) {
    throw new RuntimeException('Use an isolated currency browser fixture directory.');
}
$root = dirname(__DIR__, 2);
if ($mode === 'serve') {
    $path = realpath($root.'/public/'.ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    if ($path && is_file($path) && str_starts_with($path, $root.'/public/')) {
        return false;
    }
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($mode === 'init') {
    File::makeDirectory($directory.'/sessions', 0700, true);
    File::makeDirectory($directory.'/views', 0700, true);
    touch($directory.'/database.sqlite');
    $base = ['format' => 1, 'lock_hash' => hash_file('sha256', $root.'/composer.lock'), 'packages' => []];
    $plugin = ['package' => 'test/pricing', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Pricing',
        'manifest' => ['kind' => 'plugin', 'id' => 'test.pricing', 'name' => 'Pricing fixture', 'provider' => PricingPlugin::class, 'capabilities' => ['payment.provider', 'fulfillment.provider']]];
    file_put_contents($directory.'/plugins.json', json_encode([...$base, 'packages' => [$plugin]], JSON_THROW_ON_ERROR));
    $theme = json_decode(file_get_contents($root.'/resources/themes/default/composer.json'), true);
    file_put_contents($directory.'/themes.json', json_encode([...$base, 'packages' => [['package' => $theme['name'], 'version' => '0.1.0', 'path' => 'resources/themes/default', 'manifest' => $theme['extra']['mavyn']]]], JSON_THROW_ON_ERROR));
}
config(['database.default' => 'currency_browser', 'database.connections.currency_browser' => ['driver' => 'sqlite', 'database' => $directory.'/database.sqlite', 'foreign_key_constraints' => true],
    'cache.default' => 'array', 'session.driver' => 'file', 'session.files' => $directory.'/sessions', 'session.domain' => null, 'session.secure' => false,
    'session.cookie' => 'mavyn_currency_review_fixture', 'view.compiled' => $directory.'/views',
    'extensions.catalog' => $directory.'/plugins.json', 'extensions.live_manifests' => false, 'themes.catalog' => $directory.'/themes.json',
    'app.key' => 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', 'app.url' => 'http://127.0.0.1:8766', 'queue.default' => 'sync', 'mail.default' => 'array']);
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
if ($mode !== 'serve') {
    if ($mode === 'init') {
        Artisan::call('migrate', ['--force' => true]);
        $actor = Admin::create(['name' => 'Browser fixture', 'email' => 'browser@example.test', 'password' => 'fixture-password-only', 'role' => 'super_admin', 'is_active' => true]);
        DB::table('plugin_states')->updateOrInsert(['plugin_id' => 'test.pricing'], ['active' => true, 'version' => '1.0.0', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('extension_runtime')->where('id', 1)->increment('revision');
        $category = Category::create(['name' => 'Jackets', 'show_on_homepage' => true, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Fixture jacket', 'price' => '150.00', 'is_active' => true, 'in_stock' => true]);
        $product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
        DeliveryZone::create(['name' => 'Fixture delivery', 'delivery_fee' => '5.00', 'is_active' => true]);
        auth('admin')->setUser($actor);
        $prices = app(PriceBook::class);
        $prices->enable(['PKR', 'USD'], $prices->context()->revision, (string) Str::uuid());
        $prices->save('USD', [['kind' => 'variant', 'entity_id' => 1, 'amount_minor' => 15000], ['kind' => 'zone', 'entity_id' => 1, 'amount_minor' => 500]], $prices->context()->revision, (string) Str::uuid());
        $prices->select('USD', $prices->context()->revision, (string) Str::uuid());
        Setting::set('store_tax_rate', '10.00');
        echo $directory."\n";
    } elseif ($mode === 'inspect') {
        echo json_encode(['orders' => DB::table('orders')->count(), 'notifications' => DB::table('notification_outbox')->count()], JSON_THROW_ON_ERROR)."\n";
    } elseif ($mode === 'raise-fee') {
        auth('admin')->setUser(Admin::firstOrFail());
        $prices = app(PriceBook::class);
        $prices->save('USD', [['kind' => 'zone', 'entity_id' => 1, 'amount_minor' => 800]], $prices->context()->revision, (string) Str::uuid());
        echo "Fixture shipping fee updated.\n";
    }
    exit;
}
$app->handleRequest(Request::capture());
