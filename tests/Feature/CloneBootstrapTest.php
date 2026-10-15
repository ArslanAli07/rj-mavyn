<?php

namespace Tests\Feature;

use App\Services\Themes\InstalledThemeCatalog;
use App\Services\Themes\ThemeAssets;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Core\Themes\ThemeCatalog;
use Tests\Fixtures\Plugins\FixturePlugin;
use Tests\TestCase;

class CloneBootstrapTest extends TestCase
{
    private string $catalogPath;

    private string $themeCatalogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogPath = tempnam(sys_get_temp_dir(), 'mavyn-clone-plugins-');
        $this->themeCatalogPath = tempnam(sys_get_temp_dir(), 'mavyn-clone-themes-');
        file_put_contents($this->themeCatalogPath, json_encode(['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => []]));
        config([
            'database.default' => 'clone_bootstrap',
            'database.connections.clone_bootstrap' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'extensions.catalog' => $this->catalogPath,
            'themes.catalog' => $this->themeCatalogPath,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('clone_bootstrap');
        unlink($this->catalogPath);
        unlink($this->themeCatalogPath);
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function catalog(array $packages): void
    {
        file_put_contents($this->catalogPath, json_encode([
            'format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => $packages,
        ], JSON_THROW_ON_ERROR));
    }

    public function test_new_clone_migrates_without_any_plugins(): void
    {
        $this->catalog([]);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertTrue(Schema::hasColumn('orders', 'payment_instructions_snapshot'));
        $this->assertTrue(Schema::hasColumn('order_items', 'attributes_snapshot'));
        $this->assertTrue(Schema::hasTable('cart_owner_locks'));
        $this->assertDatabaseCount('plugin_states', 0);
        $this->assertDatabaseCount('plugin_settings', 0);
        $this->assertDatabaseHas('theme_selection', ['active_theme' => 'mavyn.theme-default']);
        $this->assertDatabaseCount('home_slides', 0);
    }

    public function test_core_only_migrate_and_seed_adds_only_required_platform_data(): void
    {
        $this->catalog([]);
        config(['platform.bootstrap_admin' => ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'a-strong-bootstrap-password'], 'site.name' => 'Store']);
        $this->assertSame(0, Artisan::call('migrate', ['--seed' => true, '--force' => true, '--no-interaction' => true]));
        $this->assertDatabaseCount('admins', 1);
        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Store']);
        $this->assertDatabaseHas('settings', ['key' => 'store_currency', 'value' => 'PKR']);
        foreach (['products', 'product_variants', 'product_images', 'categories', 'occasions', 'home_slides', 'delivery_zones', 'coupons', 'users', 'orders', 'order_items', 'faqs', 'testimonials', 'media', 'media_assets', 'media_usages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_new_clone_serves_the_bundled_default_theme_and_its_packaged_assets(): void
    {
        $this->catalog([]);
        $package = json_decode(file_get_contents(base_path('themes/default/composer.json')), true, 512, JSON_THROW_ON_ERROR);
        file_put_contents($this->themeCatalogPath, json_encode([
            'format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')),
            'packages' => [['package' => $package['name'], 'version' => '0.1.0', 'path' => 'themes/default', 'manifest' => $package['extra']['mavyn']]],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        app(InstalledThemeCatalog::class)->verify(ThemeCatalog::DEFAULT);
        foreach (['/', '/shop', '/cart', '/contact', '/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-storefront-theme="'.ThemeCatalog::DEFAULT.'"', false)
                ->assertSee('<script type="module"', false);
        }
        $assets = app(ThemeAssets::class)->urls([ThemeCatalog::DEFAULT]);
        foreach ($assets['styles'] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        }
        foreach ([...$assets['scripts'], ...array_values($assets['files'])] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        }
        $this->assertSame(dirname($assets['scripts'][0]).'/vendor-CWk4R-Yb.js', $assets['files']['alpine']);
    }

    public function test_new_clone_imports_only_reference_plugins_that_are_installed(): void
    {
        $this->catalog([[
            'package' => 'test/reference-payment', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Plugins',
            'manifest' => [
                'kind' => 'plugin', 'id' => 'mavyn.manual-payments', 'name' => 'Reference payment fixture',
                'provider' => FixturePlugin::class,
                'settings' => ['payment_cod_enabled' => ['type' => 'boolean', 'default' => true]],
            ],
        ]]);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseCount('plugin_states', 1);
        $this->assertDatabaseHas('plugin_states', ['plugin_id' => 'mavyn.manual-payments', 'active' => true]);
        $this->assertDatabaseHas('plugin_settings', ['plugin_id' => 'mavyn.manual-payments', 'key' => 'payment_cod_enabled', 'value' => 'true']);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseCount('plugin_settings', 1);
    }
}
