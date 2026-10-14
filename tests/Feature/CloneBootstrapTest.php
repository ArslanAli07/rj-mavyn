<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
