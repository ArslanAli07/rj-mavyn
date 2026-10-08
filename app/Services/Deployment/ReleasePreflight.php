<?php

namespace App\Services\Deployment;

use App\Services\Plugins\PluginRuntime;
use App\Services\Themes\ThemeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only evidence with fixed error identifiers; configuration/exception bodies never enter reports. */
final class ReleasePreflight
{
    public function __construct(private ReleaseManifest $manifests) {}

    public function scopeHash(): string
    {
        $connection = config('database.connections.'.config('database.default'));

        return hash('sha256', implode('|', [config('platform.client'), $connection['driver'] ?? '', $connection['host'] ?? '', $connection['port'] ?? '', $connection['database'] ?? '']));
    }

    public function check(string $manifestPath, string $backupPath): array
    {
        $checks = [];
        $check = function (string $name, callable $run) use (&$checks): void {
            try {
                $checks[$name] = (bool) $run();
            } catch (Throwable) {
                $checks[$name] = false;
            }
        };
        $manifest = null;
        $check('source_inventory', function () use ($manifestPath, &$manifest) {
            if (! is_file($manifestPath)) {
                throw new \RuntimeException('Release manifest missing.');
            }
            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $this->manifests->verify($manifest);

            return true;
        });
        $client = config('platform.client');
        $check('client_identity', fn () => is_string($client) && preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/D', $client));
        $check('runtime', fn () => version_compare(PHP_VERSION, '8.3', '>=') && extension_loaded('pdo') && extension_loaded('mbstring') && extension_loaded('gd') && function_exists('imagewebp'));
        $check('application_key', fn () => app('encrypter')->getKey() !== '');
        $check('database', fn () => DB::connection()->getPdo() !== null);
        $check('migrations', fn () => $manifest && Schema::hasTable('migrations') && ! array_diff($manifest['migrations'], DB::table('migrations')->pluck('migration')->all()));
        $check('client_storage', fn () => realpath(storage_path()) && ! str_starts_with(realpath(storage_path()), realpath(base_path()).DIRECTORY_SEPARATOR) && is_writable(storage_path('framework/cache')) && is_writable(storage_path('app/private')) && is_writable(storage_path('app/public')));
        $check('namespaces', fn () => $client && str_starts_with((string) config('session.cookie'), $client.'_') && str_starts_with((string) config('cache.prefix'), $client.':') && config('session.domain') === null && config('queue.connections.'.config('queue.default').'.queue') === $client && config('notifications.queue') === $client);
        $check('plugins', function () {
            app(PluginRuntime::class)->refresh();

            return true;
        });
        $check('theme', function () {
            app(ThemeManager::class)->validate(DB::table('theme_selection')->where('id', 1)->value('active_theme'));

            return true;
        });
        $check('frontend_assets', function () use ($manifest) {
            if (! $manifest) {
                return false;
            }
            foreach ($manifest['files'] as $file => $hash) {
                if (str_starts_with($file, 'public/build/') && (! is_file(public_path(substr($file, 7))) || hash_file('sha256', public_path(substr($file, 7))) !== $hash)) {
                    return false;
                }
            }

            return true;
        });
        $check('backup', fn () => $this->backupReady($backupPath));
        $passed = ! in_array(false, $checks, true);

        return ['format' => 1, 'client' => is_string($client) && ($checks['client_identity'] ?? false) ? $client : null, 'release' => $checks['source_inventory'] ? $manifest['release'] : null, 'source_hash' => $checks['source_inventory'] ? $manifest['source_hash'] : null, 'outcome' => $passed ? 'verified' : 'failed', 'checks' => $checks, 'versions' => $checks['source_inventory'] ? $manifest['versions'] : []];
    }

    private function backupReady(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }
        $backup = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($backup['format'] ?? null) !== 1 || ($backup['client'] ?? null) !== config('platform.client') || ($backup['scope_hash'] ?? null) !== $this->scopeHash() || ($backup['restore_verified'] ?? false) !== true || ! is_int($backup['created_at'] ?? null) || $backup['created_at'] > time() + 300 || $backup['created_at'] < time() - config('platform.backup_max_age_hours') * 3600 || empty($backup['files']) || ! is_array($backup['files'])) {
            return false;
        }
        $root = realpath(dirname($path));
        foreach ($backup['files'] as $name => $hash) {
            if (! is_string($name) || str_starts_with($name, '/') || in_array('..', explode('/', $name), true) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                return false;
            }
            $file = realpath($root.'/'.$name);
            if (! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file) || hash_file('sha256', $file) !== $hash) {
                return false;
            }
        }

        return true;
    }

    public function record(array $report): void
    {
        DB::table('deployment_runs')->insert(['client_id' => $report['client'] ?? 'unconfigured', 'release_id' => $report['release'], 'source_hash' => $report['source_hash'], 'outcome' => $report['outcome'], 'checks' => json_encode($report['checks'], JSON_THROW_ON_ERROR), 'versions' => json_encode($report['versions'], JSON_THROW_ON_ERROR), 'checked_at' => now()]);
    }
}
