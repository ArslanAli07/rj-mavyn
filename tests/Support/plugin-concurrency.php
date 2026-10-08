<?php

// Creates/drops only a randomly named disposable local MySQL database. Sends no mail.
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginSettings;
use App\Services\Themes\ThemeManager;
use App\Services\Themes\ThemeSettings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Plugins\FixturePlugin;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);
$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'mavyn_plugin_check_'.bin2hex(random_bytes(8));
if (! preg_match('/^mavyn_plugin_check_[a-f0-9]{16}$/D', $database)) {
    throw new RuntimeException('Unsafe test database name.');
}
$directory = $worker ? $argv[3] : sys_get_temp_dir().'/'.$database;
if (! $worker) {
    File::makeDirectory($directory, 0700, true);
}
if ($worker) {
    config(['extensions.catalog' => $directory.'/catalog.json', 'themes.catalog' => $directory.'/themes.json']);
}
$serverConfig = config('database.connections.mysql');
$serverConfig['database'] = null;
config(['database.connections.plugin_server' => $serverConfig, 'database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
DB::purge('mysql');
if ($worker) {
    $operation = $argv[4];
    $slot = $argv[5];
    DB::beginTransaction();
    DB::table('theme_selection')->get();
    DB::table('theme_settings')->get();
    DB::table('plugin_states')->get(); // Establish a repeatable-read snapshot before competing mutations.
    file_put_contents($directory.'/ready-'.$slot, 'ready');
    $deadline = microtime(true) + 15;
    while (! is_file($directory.'/go') && microtime(true) < $deadline) {
        usleep(10000);
    }
    try {
        DB::transaction(function () use ($operation) {
            DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
            usleep(300000);
            if ($operation === 'theme-activate') {
                app(ThemeManager::class)->activate('mavyn.theme-happy-tea-child');
            } elseif ($operation === 'theme-save') {
                app(ThemeSettings::class)->save('mavyn.theme-happy-tea-child', ['welcome_text' => 'Concurrent theme settings', 'accent_color' => '#123456']);
            } elseif ($operation === 'activate') {
                app(PluginManager::class)->change('test.fixture', true);
            } elseif ($operation === 'child') {
                app(PluginManager::class)->change('test.child', true);
            } elseif ($operation === 'disable-base') {
                app(PluginManager::class)->change('test.base', false);
            } elseif ($operation === 'save') {
                app(PluginSettings::class)->save('test.fixture', ['token' => 'concurrent-secret', 'limit' => 5], Admin::firstOrFail());
            } elseif ($operation === 'disable') {
                app(PluginManager::class)->change('test.fixture', false);
            } else {
                throw new RuntimeException('Unknown operation');
            }
        });
        DB::commit();
        echo json_encode(['success' => true]);
    } catch (ValidationException|HttpException $exception) {
        DB::rollBack();
        echo json_encode(['rejected' => true]);
    }
    exit(0);
}
$server = DB::connection('plugin_server');
$created = false;
$processes = [];
$exitCode = 0;
$check = function (bool $condition, string $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
try {
    $server->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    Artisan::call('migrate', ['--force' => true]);
    DB::table('plugin_states')->delete();
    DB::table('plugin_settings')->delete();
    DB::table('admin_audit_logs')->delete();
    DB::table('extension_runtime')->where('id', 1)->update(['revision' => 0]);
    $packages = [];
    foreach (['test.fixture', 'test.base', 'test.child'] as $id) {
        $packages[] = ['package' => 'test/fixture', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Plugins', 'manifest' => ['kind' => 'plugin', 'id' => $id, 'name' => $id, 'provider' => FixturePlugin::class, 'capabilities' => ['payment.provider'], 'requires_active' => $id === 'test.child' ? ['test.base' => '^1.0'] : [], 'settings' => ['notice' => ['type' => 'text', 'default' => 'Fixture notice'], 'token' => ['type' => 'text', 'default' => null, 'nullable' => true, 'secret' => true], 'limit' => ['type' => 'integer', 'default' => 0, 'min' => 0, 'max' => 10]]]];
    }
    file_put_contents($directory.'/catalog.json', json_encode(['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => $packages]));
    $themeData = json_decode(file_get_contents(config('themes.catalog')), true);
    foreach ($themeData['packages'] as &$themePackage) {
        if ($themePackage['manifest']['id'] === 'mavyn.theme-happy-tea-child') {
            $themePackage['manifest']['requires_active'] = ['test.base' => '^1.0'];
        }
    }
    unset($themePackage);
    file_put_contents($directory.'/themes.json', json_encode($themeData));
    config(['extensions.catalog' => $directory.'/catalog.json', 'themes.catalog' => $directory.'/themes.json']);
    $race = function (array $operations) use ($database, $directory, &$processes, $check) {
        foreach (glob($directory.'/ready-*') as $file) {
            unlink($file);
        }
        if (is_file($directory.'/go')) {
            unlink($directory.'/go');
        }
        $batch = [];
        foreach ($operations as $slot => $operation) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', $database, $directory, $operation, (string) $slot], base_path());
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
            $batch[] = $process;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($directory.'/ready-*')) < count($operations) && microtime(true) < $deadline) {
            usleep(10000);
        }
        $check(count(glob($directory.'/ready-*')) === count($operations), 'Workers did not reach barrier.');
        file_put_contents($directory.'/go', 'go');
        $results = [];
        foreach ($batch as $process) {
            $process->wait();
            $check($process->isSuccessful(), 'Worker failed: '.$process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    };
    $results = $race(['activate', 'activate']);
    $check(count(array_filter($results, fn ($r) => $r['success'] ?? false)) === 2, 'Duplicate activation failed.');
    $check((int) DB::table('extension_runtime')->value('revision') === 1, 'Duplicate activation changed revision twice.');
    $check(AdminAuditLog::where('entity_type', 'plugins')->where('action', 'activated')->count() === 1, 'Duplicate activation audit.');
    app(PluginManager::class)->change('test.base', true);
    $results = $race(['child', 'disable-base']);
    $check(count(array_filter($results, fn ($r) => $r['rejected'] ?? false)) === 1, 'Dependency race accepted an invalid combination.');
    $active = DB::table('plugin_states')->where('active', true)->pluck('plugin_id')->all();
    app(InstalledPluginCatalog::class)->load()->order($active);
    Admin::create(['name' => 'Integration owner', 'email' => 'plugin-owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
    $race(['save', 'disable']);
    $check(! DB::table('plugin_states')->where('plugin_id', 'test.fixture')->where('active', true)->exists(), 'Disable did not persist.');
    $count = DB::table('plugin_settings')->where('plugin_id', 'test.fixture')->count();
    $check(in_array($count, [0, 2], true), 'Settings partially persisted during disable.');
    if ($count) {
        $check(! str_contains(DB::table('plugin_settings')->where('key', 'token')->value('value'), 'concurrent-secret'), 'Secret stored without encryption.');
    }
    // Remove the earlier dependent plugin before testing theme dependency races.
    app(PluginManager::class)->change('test.child', false);
    app(PluginManager::class)->change('test.base', true);
    $beforeThemeRevision = (int) DB::table('extension_runtime')->value('revision');
    $results = $race(['theme-activate', 'theme-activate']);
    $check(count(array_filter($results, fn ($r) => $r['success'] ?? false)) === 2, 'Duplicate theme activation failed.');
    $check((int) DB::table('extension_runtime')->value('revision') === $beforeThemeRevision + 1, 'Duplicate theme activation changed revision twice.');
    $check(AdminAuditLog::where('entity_type', 'themes')->where('action', 'activated')->count() === 1, 'Duplicate theme activation audit.');
    app(ThemeManager::class)->activate('mavyn.theme-default');
    $results = $race(['theme-activate', 'disable-base']);
    $check(count(array_filter($results, fn ($r) => $r['rejected'] ?? false)) === 1, 'Theme dependency race accepted an invalid combination.');
    $selected = DB::table('theme_selection')->value('active_theme');
    app(ThemeManager::class)->validate($selected);
    app(PluginManager::class)->change('test.base', true);
    $race(['theme-save', 'theme-activate']);
    $check(DB::table('theme_settings')->where('theme_id', 'mavyn.theme-happy-tea-child')->count() === 2, 'Theme settings partially persisted.');
    $check(app(ThemeSettings::class)->values('mavyn.theme-happy-tea-child')['welcome_text'] === 'Concurrent theme settings', 'Theme settings lost during activation.');
    echo json_encode(['result' => 'passed', 'database' => 'disposable MySQL', 'scenarios' => ['duplicate activation creates one revision and audit', 'dependent activation versus dependency disable retains a valid graph', 'settings save versus disable is atomic and secrets encrypted', 'duplicate theme activation creates one revision and audit', 'theme activation versus required plugin disable retains a valid combination', 'theme settings save versus activation retains all values']], JSON_THROW_ON_ERROR).PHP_EOL;
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
    } File::deleteDirectory($directory);
}
exit($exitCode);
