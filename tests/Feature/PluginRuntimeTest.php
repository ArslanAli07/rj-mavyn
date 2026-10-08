<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginRuntime;
use App\Services\Plugins\PluginSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\ProductDisplay;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Plugins\BadPlugin;
use Tests\Fixtures\Plugins\FixtureController;
use Tests\Fixtures\Plugins\FixturePaymentProvider;
use Tests\Fixtures\Plugins\FixturePlugin;
use Tests\TestCase;

class PluginRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogPath;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('plugin_states')->delete();
        DB::table('plugin_settings')->delete();
        DB::table('admin_audit_logs')->delete();
        DB::table('extension_runtime')->where('id', 1)->update(['revision' => 0]);
        $this->catalogPath = tempnam(sys_get_temp_dir(), 'mavyn-plugins-');
        config(['extensions.catalog' => $this->catalogPath]);
        FixturePlugin::$failActivation = false;
        FixturePlugin::$failDeactivation = false;
        FixturePlugin::$events = [];
        FixturePaymentProvider::$constructed = 0;
        FixtureController::$executed = 0;
        $this->catalog([$this->package('test.fixture')]);
    }

    protected function tearDown(): void
    {
        try {
            if (is_file($this->catalogPath)) {
                unlink($this->catalogPath);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function package(string $id, array $extra = []): array
    {
        return ['package' => 'test/fixture', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Plugins', 'manifest' => [
            'kind' => 'plugin', 'id' => $id, 'name' => 'Fixture plugin', 'provider' => FixturePlugin::class, 'capabilities' => ['payment.provider'],
            'permissions' => [$id.'.manage' => 'Manage fixture'], 'routes' => ['admin' => 'routes/admin.php', 'storefront' => 'routes/storefront.php'],
            'settings' => ['notice' => ['type' => 'text', 'default' => 'Fixture notice'], 'token' => ['type' => 'text', 'default' => null, 'nullable' => true, 'secret' => true], 'limit' => ['type' => 'integer', 'default' => 0, 'min' => 0, 'max' => 10], 'enabled' => ['type' => 'boolean', 'default' => true]], ...$extra]];
    }

    private function catalog(array $packages): void
    {
        file_put_contents($this->catalogPath, json_encode(['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => $packages]));
        app(PluginRuntime::class)->forget();
    }

    private function actor(array $permissions = [], string $role = 'custom'): Admin
    {
        return Admin::create(['name' => 'Plugin operator', 'email' => uniqid().'@example.com', 'password' => 'password', 'is_active' => true, 'role' => $role, 'permissions' => $permissions]);
    }

    public function test_activation_is_idempotent_and_deactivation_retains_data(): void
    {
        $manager = app(PluginManager::class);
        $actor = $this->actor([], 'super_admin');
        $manager->change('test.fixture', true, $actor);
        $manager->change('test.fixture', true, $actor);
        $this->assertSame(1, (int) DB::table('extension_runtime')->value('revision'));
        $this->assertDatabaseCount('plugin_states', 1);
        $this->assertSame(1, AdminAuditLog::where('entity_type', 'plugins')->count());
        app(PluginSettings::class)->save('test.fixture', ['notice' => 'Saved notice'], $actor);
        $manager->change('test.fixture', false, $actor);
        $manager->change('test.fixture', false, $actor);
        $this->assertDatabaseHas('plugin_states', ['plugin_id' => 'test.fixture', 'active' => false]);
        $this->assertDatabaseCount('plugin_settings', 1);
        $this->assertSame('test.fixture', Setting::get('fixture_setup'));
        $this->assertSame([], app(PluginRuntime::class)->providers()->keys('payment'));
        $manager->change('test.fixture', true, $actor);
        $this->assertSame('Saved notice', app(PluginSettings::class)->get('test.fixture', 'notice'));
    }

    public function test_dependencies_block_activation_and_prevent_disabling_required_plugin(): void
    {
        $this->catalog([$this->package('test.base'), $this->package('test.child', ['requires_active' => ['test.base' => '^1.0']])]);
        $this->actingAs($this->actor([], 'super_admin'), 'admin');
        $this->putJson(route('admin.extensions.update', 'test.child'), ['active' => true])->assertUnprocessable();
        $this->put(route('admin.extensions.update', 'test.base'), ['active' => true])->assertRedirect();
        $this->put(route('admin.extensions.update', 'test.child'), ['active' => true])->assertRedirect();
        $this->putJson(route('admin.extensions.update', 'test.base'), ['active' => false])->assertUnprocessable();
        $this->assertDatabaseHas('plugin_states', ['plugin_id' => 'test.base', 'active' => true]);
        $hooks = app(PluginRuntime::class)->hooks();
        $hooks->dispatch('core.catalog.product.displayed', new ProductDisplay(1));
        $this->assertSame(['test.base', 'test.child'], FixturePlugin::$events);
    }

    public function test_failed_activation_and_invalid_registration_roll_back_state_setup_and_audit(): void
    {
        $this->actingAs($this->actor([], 'super_admin'), 'admin');
        FixturePlugin::$failActivation = true;
        $this->putJson(route('admin.extensions.update', 'test.fixture'), ['active' => true])->assertStatus(500);
        $this->assertDatabaseMissing('settings', ['key' => 'fixture_setup']);
        $this->assertDatabaseCount('plugin_states', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        FixturePlugin::$failActivation = false;
        $this->catalog([$this->package('test.fixture', ['provider' => BadPlugin::class])]);
        $this->putJson(route('admin.extensions.update', 'test.fixture'), ['active' => true])->assertUnprocessable();
        $this->assertSame(0, (int) DB::table('extension_runtime')->value('revision'));
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_pending_operation_failure_preserves_active_plugin(): void
    {
        app(PluginManager::class)->change('test.fixture', true);
        FixturePlugin::$failDeactivation = true;
        $this->actingAs($this->actor([], 'super_admin'), 'admin')->putJson(route('admin.extensions.update', 'test.fixture'), ['active' => false])->assertStatus(500);
        $this->assertDatabaseHas('plugin_states', ['plugin_id' => 'test.fixture', 'active' => true]);
        $this->assertSame(1, (int) DB::table('extension_runtime')->value('revision'));
    }

    public function test_settings_are_namespaced_validated_encrypted_and_redacted(): void
    {
        $this->catalog([$this->package('test.fixture'), $this->package('test.other')]);
        app(PluginManager::class)->change('test.fixture', true);
        app(PluginManager::class)->change('test.other', true);
        $this->actingAs($this->actor(['extensions.manage', 'extensions.view']), 'admin');
        $this->putJson(route('admin.extensions.settings', 'test.fixture'), ['settings' => ['notice' => '<script>secret marker</script>', 'token' => 'private-token', 'limit' => '5', 'enabled' => '0']])->assertRedirect();
        $this->assertSame(5, app(PluginSettings::class)->get('test.fixture', 'limit'));
        $this->assertFalse(app(PluginSettings::class)->get('test.fixture', 'enabled'));
        $this->assertSame('private-token', app(PluginSettings::class)->get('test.fixture', 'token'));
        $this->assertSame('Fixture notice', app(PluginSettings::class)->get('test.other', 'notice'));
        $this->assertStringNotContainsString('private-token', DB::table('plugin_settings')->where('key', 'token')->value('value'));
        $this->assertStringNotContainsString('private-token', AdminAuditLog::where('entity_type', 'plugins')->get()->toJson());
        $this->get(route('admin.extensions.index'))->assertOk()->assertDontSee('private-token');
        $this->putJson(route('admin.extensions.settings', 'test.fixture'), ['settings' => ['notice' => 'Changed', 'limit' => 11, 'unknown' => 'value']])->assertUnprocessable()->assertJsonValidationErrors(['settings.limit', 'settings.unknown']);
        $this->assertSame('<script>secret marker</script>', app(PluginSettings::class)->get('test.fixture', 'notice'));
        $this->put(route('admin.extensions.settings', 'test.fixture'), ['settings' => ['token' => '']])->assertRedirect();
        $this->assertSame('private-token', app(PluginSettings::class)->get('test.fixture', 'token'));
        app(PluginManager::class)->change('test.fixture', false);
        $this->putJson(route('admin.extensions.settings', 'test.fixture'), ['settings' => ['notice' => 'Changed']])->assertStatus(409);
    }

    public function test_failed_web_settings_save_never_flashes_secret_values(): void
    {
        app(PluginManager::class)->change('test.fixture', true);
        $this->actingAs($this->actor(['extensions.manage', 'extensions.view']), 'admin');
        $this->from(route('admin.extensions.index'))->put(route('admin.extensions.settings', 'test.fixture'), ['settings' => ['token' => 'never-flash-token', 'limit' => 11, 'notice' => 'Entered notice']])->assertSessionHasErrors('settings.limit');
        $this->assertNull(session()->getOldInput('settings.token'));
        $this->assertSame('Entered notice', session()->getOldInput('settings.notice'));
        $this->assertDatabaseCount('plugin_settings', 0);
        $this->get(route('admin.extensions.index'))->assertOk()->assertDontSee('never-flash-token');
    }

    public function test_admin_permissions_guard_management_and_setting_specific_permissions(): void
    {
        $this->actingAs($this->actor(['extensions.view']), 'admin');
        $this->get(route('admin.extensions.index'))->assertOk()->assertDontSee('>Activate<', false);
        $this->putJson(route('admin.extensions.update', 'test.fixture'), ['active' => true])->assertForbidden();
        $package = $this->package('test.fixture');
        $package['manifest']['settings']['notice']['permission'] = 'test.fixture.manage';
        $this->catalog([$package]);
        app(PluginManager::class)->change('test.fixture', true);
        $this->actingAs($this->actor(['extensions.manage']), 'admin')->putJson(route('admin.extensions.settings', 'test.fixture'), ['settings' => ['notice' => 'Changed']])->assertForbidden();
    }

    public function test_runtime_refresh_removes_disabled_contributions_and_settings_changes(): void
    {
        $runtime = app(PluginRuntime::class);
        $this->assertSame([], $runtime->providers()->keys('payment'));
        app(PluginManager::class)->change('test.fixture', true);
        $this->assertSame(['test.fixture.manual'], $runtime->providers()->keys('payment'));
        $this->assertSame(0, FixturePaymentProvider::$constructed);
        $this->assertSame(['Fixture notice'], $runtime->hooks()->apply('core.catalog.product.display', new ProductDisplay(1))->notices);
        app(PluginSettings::class)->save('test.fixture', ['notice' => 'Updated'], $this->actor([], 'super_admin'));
        $this->assertSame(['Updated'], $runtime->hooks()->apply('core.catalog.product.display', new ProductDisplay(1))->notices);
        app(PluginManager::class)->change('test.fixture', false);
        $this->assertSame([], $runtime->hooks()->apply('core.catalog.product.display', new ProductDisplay(1))->notices);
    }

    public function test_product_page_uses_escaped_display_hook_without_changing_price(): void
    {
        app(PluginManager::class)->change('test.fixture', true);
        app(PluginSettings::class)->save('test.fixture', ['notice' => '<script>Notice</script>'], $this->actor([], 'super_admin'));
        $product = Product::create(['title' => 'Fixture product', 'slug' => 'fixture-product', 'price' => '12.50', 'category_id' => Category::create(['name' => 'Fixture category', 'slug' => 'fixture-category'])->id, 'is_active' => true]);
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('&lt;script&gt;Notice&lt;/script&gt;', false)->assertDontSee('<script>Notice</script>', false);
        $this->assertSame('12.50', $product->fresh()->price);
        $this->assertSame(['test.fixture'], FixturePlugin::$events);
    }

    public function test_routes_are_guarded_after_route_cache_without_rebuilding_on_toggle(): void
    {
        $cachePath = $this->catalogPath.'.routes.php';
        $process = new Process([PHP_BINARY, 'artisan', 'route:cache'], base_path(), ['APP_ROUTES_CACHE' => $cachePath, 'MAVYN_EXTENSION_CATALOG' => $this->catalogPath]);
        try {
            $process->mustRun();
            require $cachePath;
            $this->get('/extensions/test.fixture/probe')->assertNotFound();
            $this->assertSame(0, FixtureController::$executed);
            app(PluginManager::class)->change('test.fixture', true);
            $this->get('/extensions/test.fixture/probe')->assertOk();
            $this->actingAs($this->actor([]), 'admin')->get('/admin/extensions/test.fixture/probe')->assertForbidden();
            $this->actingAs($this->actor(['test.fixture.manage']), 'admin')->get('/admin/extensions/test.fixture/probe')->assertOk();
            $this->post('/admin/extensions/test.fixture/probe')->assertOk();
            $this->assertDatabaseHas('admin_audit_logs', ['entity_type' => 'plugin_operations', 'route' => 'plugin.test.fixture.admin.mutate']);
            app(PluginManager::class)->change('test.fixture', false);
            $this->get('/extensions/test.fixture/probe')->assertNotFound();
            $this->get('/admin/extensions/test.fixture/probe')->assertNotFound();
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }

    public function test_activation_waits_for_explicit_deployment_migrations_and_preserves_their_data(): void
    {
        $path = 'database/migrations/2026_10_09_900001_test_fixture_create_records.php';
        $this->catalog([$this->package('test.fixture', ['migrations' => [$path]])]);
        $this->actingAs($this->actor([], 'super_admin'), 'admin');
        $this->putJson(route('admin.extensions.update', 'test.fixture'), ['active' => true])->assertUnprocessable();
        $this->assertFalse(Schema::hasTable('test_fixture_records'));
        $this->artisan('migrate', ['--path' => 'tests/Fixtures/Plugins/'.$path, '--force' => true])->assertSuccessful();
        $this->put(route('admin.extensions.update', 'test.fixture'), ['active' => true])->assertRedirect();
        DB::table('test_fixture_records')->insert(['value' => 'Historical data']);
        $this->put(route('admin.extensions.update', 'test.fixture'), ['active' => false])->assertRedirect();
        $this->assertDatabaseHas('test_fixture_records', ['value' => 'Historical data']);
    }

    public function test_scaffold_is_inert_and_does_not_overwrite_existing_package(): void
    {
        $original = $this->app->basePath();
        $directory = $this->catalogPath.'.host';
        File::makeDirectory($directory, 0700);
        try {
            $this->app->setBasePath($directory);
            $this->artisan('platform:plugin:make', ['plugin' => 'acme.scaffold'])->assertSuccessful();
            $path = $directory.'/plugins/acme.scaffold/composer.json';
            $data = json_decode(file_get_contents($path), true);
            $this->assertSame('acme.scaffold', $data['extra']['mavyn']['id']);
            $this->assertSame('acme/scaffold', $data['name']);
            $this->assertArrayNotHasKey('laravel', $data['extra']);
            require $directory.'/plugins/acme.scaffold/src/Plugin.php';
            $this->assertTrue(is_subclass_of($data['extra']['mavyn']['provider'], Plugin::class));
            $this->artisan('platform:plugin:make', ['plugin' => 'acme.scaffold'])->assertFailed();
            $this->artisan('platform:plugin:make', ['plugin' => '../unsafe'])->assertFailed();
            $this->assertDatabaseCount('plugin_states', 0);
        } finally {
            $this->app->setBasePath($original);
            File::deleteDirectory($directory);
        }
    }

    public function test_stale_catalog_fails_closed_and_lifecycle_audit_failure_rolls_back(): void
    {
        $data = json_decode(file_get_contents($this->catalogPath), true);
        $data['lock_hash'] = 'stale';
        file_put_contents($this->catalogPath, json_encode($data));
        try {
            app(InstalledPluginCatalog::class)->load();
            $this->fail('Stale catalog accepted');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('stale', $exception->getMessage());
        }
        $this->catalog([$this->package('test.fixture')]);
        AdminAuditLog::creating(fn () => throw new RuntimeException('Audit failure'));
        try {
            app(PluginManager::class)->change('test.fixture', true);
            $this->fail('Audit failure ignored');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('plugin_states', 0);
        $this->assertDatabaseMissing('settings', ['key' => 'fixture_setup']);
    }
}
