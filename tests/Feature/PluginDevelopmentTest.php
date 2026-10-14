<?php

namespace Tests\Feature;

use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginRoutes;
use App\Services\Plugins\PluginSettings;
use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Fixtures\Plugins\FixturePlugin;
use Tests\TestCase;

class PluginDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    private string $originalBase;

    private string $directory;

    private array $installed;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('plugin_states')->delete();
        DB::table('plugin_settings')->delete();
        FixturePlugin::$failActivation = false;
        FixturePlugin::$failDeactivation = false;
        $this->originalBase = base_path();
        $this->directory = sys_get_temp_dir().'/mavyn-plugin-dev-'.bin2hex(random_bytes(8));
        foreach (['vendor/composer', 'plugins/test.live/routes', 'plugins/test.live/database/migrations'] as $path) {
            File::ensureDirectoryExists($this->directory.'/'.$path, 0700);
        }
        copy(base_path('composer.lock'), $this->directory.'/composer.lock');
        $this->installed = ['name' => 'test/live', 'version' => '1.0.0', 'type' => 'library',
            'require' => ['php' => '^8.3', 'mavyn/core' => '^0.1', 'mavyn/contracts' => '^0.1'],
            'autoload' => ['psr-4' => ['Tests\\Fixtures\\Plugins\\' => 'src/']],
            'extra' => ['mavyn' => ['kind' => 'plugin', 'id' => 'test.live', 'name' => 'Original live fixture', 'provider' => FixturePlugin::class,
                'capabilities' => ['payment.provider'], 'settings' => ['notice' => ['type' => 'text', 'default' => 'Original notice']], 'routes' => [], 'migrations' => []]]];
        File::put($this->directory.'/vendor/composer/installed.json', json_encode(['packages' => [$this->installed]], JSON_THROW_ON_ERROR));
        $this->source();
        $catalog = ['format' => 1, 'lock_hash' => hash_file('sha256', $this->directory.'/composer.lock'), 'packages' => [
            ['package' => 'test/live', 'version' => '1.0.0', 'path' => 'plugins/test.live', 'manifest' => $this->installed['extra']['mavyn']]]];
        File::put($this->directory.'/catalog.json', json_encode($catalog, JSON_THROW_ON_ERROR));
        File::put($this->directory.'/themes.json', json_encode(['format' => 1, 'lock_hash' => $catalog['lock_hash'], 'packages' => []], JSON_THROW_ON_ERROR));
        config(['extensions.catalog' => $this->directory.'/catalog.json', 'extensions.live_manifests' => true, 'themes.catalog' => $this->directory.'/themes.json']);
        $this->app->setBasePath($this->directory);
        $this->app->instance('env', 'local');
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBase);
        $this->app->instance('env', 'testing');
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function source(?array $source = null): void
    {
        File::put($this->directory.'/plugins/test.live/composer.json', json_encode($source ?? $this->installed, JSON_THROW_ON_ERROR));
    }

    public function test_local_manifest_edits_are_visible_without_updating_composer_or_catalog_files(): void
    {
        app(PluginManager::class)->change('test.live', true);
        $this->assertSame('Original notice', app(PluginSettings::class)->get('test.live', 'notice'));
        $before = array_map(fn ($path) => hash_file('sha256', $this->directory.'/'.$path), ['composer.lock', 'vendor/composer/installed.json', 'catalog.json']);
        $source = $this->installed;
        $source['extra']['mavyn']['name'] = 'Edited live fixture';
        $source['extra']['mavyn']['settings']['notice']['default'] = 'Edited notice';
        $this->source($source);
        $this->assertSame('Edited live fixture', app(InstalledPluginCatalog::class)->load()->get('test.live')['manifest']['name']);
        $this->assertSame('Edited notice', app(PluginSettings::class)->get('test.live', 'notice'));
        $this->assertSame($before, array_map(fn ($path) => hash_file('sha256', $this->directory.'/'.$path), ['composer.lock', 'vendor/composer/installed.json', 'catalog.json']));
    }

    public function test_new_route_declarations_load_and_keep_activation_guards_without_composer_updates(): void
    {
        $source = $this->installed;
        $source['extra']['mavyn']['routes'] = ['storefront' => 'routes/storefront.php'];
        File::put($this->directory.'/plugins/test.live/routes/storefront.php', '<?php use Illuminate\\Support\\Facades\\Route; use Tests\\Fixtures\\Plugins\\FixtureController; Route::get("live-probe", FixtureController::class);');
        $this->source($source);
        app(PluginRoutes::class)->register();
        $this->get('/extensions/test.live/live-probe')->assertNotFound();
        app(PluginManager::class)->change('test.live', true);
        $this->get('/extensions/test.live/live-probe')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_new_migration_declarations_require_explicit_schema_application(): void
    {
        $source = $this->installed;
        $path = 'database/migrations/2026_10_14_900001_test_live_create_records.php';
        $source['extra']['mavyn']['migrations'] = [$path];
        File::put($this->directory.'/plugins/test.live/'.$path, '<?php use Illuminate\\Database\\Migrations\\Migration; use Illuminate\\Database\\Schema\\Blueprint; use Illuminate\\Support\\Facades\\Schema; return new class extends Migration { public function up(): void { Schema::create("test_live_records", function (Blueprint $table) { $table->id(); $table->string("value"); }); } public function down(): void { Schema::dropIfExists("test_live_records"); } };');
        $this->source($source);
        $package = app(InstalledPluginCatalog::class)->load()->get('test.live');
        $this->assertSame([$path], $package['manifest']['migrations']);
        $this->assertFalse(Schema::hasTable('test_live_records'));
        $this->artisan('platform:plugins:activate', ['plugin' => 'test.live'])->assertFailed();
        // The host registers these declared paths during the next application bootstrap.
        app('migrator')->path(app(InstalledPluginCatalog::class)->resource($package, $path));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('test_live_records'));
        app(PluginManager::class)->change('test.live', true);
        DB::table('test_live_records')->insert(['value' => 'Retained']);
        app(PluginManager::class)->change('test.live', false);
        $this->assertDatabaseHas('test_live_records', ['value' => 'Retained']);
    }

    public function test_production_and_explicit_local_opt_out_use_the_built_snapshot(): void
    {
        $source = $this->installed;
        $source['extra']['mavyn']['name'] = 'Unbuilt edits';
        $this->source($source);
        $this->app->instance('env', 'production');
        $this->assertSame('Original live fixture', app(InstalledPluginCatalog::class)->load()->get('test.live')['manifest']['name']);
        $this->app->instance('env', 'local');
        config(['extensions.live_manifests' => false]);
        $this->assertSame('Original live fixture', app(InstalledPluginCatalog::class)->load()->get('test.live')['manifest']['name']);
    }

    public function test_explicit_production_build_uses_source_metadata_without_composer_update(): void
    {
        $source = $this->installed;
        $source['extra']['mavyn']['name'] = 'New release metadata';
        $this->source($source);
        $this->app->instance('env', 'production');
        $versions = InstalledVersions::getRawData();
        $fixture = $versions;
        $fixture['versions']['test/live'] = ['pretty_version' => '1.0.0', 'version' => '1.0.0.0', 'aliases' => [], 'type' => 'library', 'install_path' => $this->directory.'/plugins/test.live', 'dev_requirement' => false];
        InstalledVersions::reload($fixture);
        try {
            $before = hash_file('sha256', $this->directory.'/composer.lock');
            $this->artisan('platform:extensions:build')->assertSuccessful();
            $this->assertSame('New release metadata', app(InstalledPluginCatalog::class)->load()->get('test.live')['manifest']['name']);
            $this->assertSame($before, hash_file('sha256', $this->directory.'/composer.lock'));
        } finally {
            InstalledVersions::reload($versions);
        }
    }

    public static function dependencyFields(): array
    {
        return [['require'], ['autoload']];
    }

    #[DataProvider('dependencyFields')]
    public function test_dependency_or_autoload_changes_are_not_silently_accepted(string $field): void
    {
        $source = $this->installed;
        $source[$field] = $field === 'require' ? [...$source[$field], 'missing/sdk' => '^1.0'] : ['psr-4' => ['Changed\\Namespace\\' => 'src/']];
        $this->source($source);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('run composer update test/live');
        app(InstalledPluginCatalog::class)->load();
    }
}
