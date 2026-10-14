<?php

namespace App\Console\Commands;

use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginManifests;
use App\Services\Themes\InstalledThemeCatalog;
use App\Services\Themes\ThemeAssets;
use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Console\Command;
use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Core\Plugins\PluginCatalog;
use Mavyn\Core\Themes\ThemeCatalog;
use RuntimeException;
use Throwable;

class BuildPluginCatalogCommand extends Command
{
    protected $signature = 'platform:extensions:build';

    protected $description = 'Validate Composer-installed plugins and atomically build the installed catalog';

    public function handle(InstalledPluginCatalog $resources): int
    {
        try {
            $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true, 512, JSON_THROW_ON_ERROR);
            $packages = [];
            foreach ($installed['packages'] ?? $installed as $package) {
                $meta = $package['extra']['mavyn'] ?? null;
                if (! $meta || ($meta['kind'] ?? '') !== 'plugin') {
                    continue;
                }
                if (! empty($package['extra']['laravel']['providers']) || ! empty($package['autoload']['files'])) {
                    throw new RuntimeException('Plugin eager autoload/discovery is forbidden: '.$package['name']);
                }
                foreach (['php', 'mavyn/core', 'mavyn/contracts'] as $requirement) {
                    $constraint = $package['require'][$requirement] ?? null;
                    if (! is_string($constraint) || ($requirement === 'php' ? ! Semver::satisfies(PHP_VERSION, $constraint) : ! InstalledVersions::satisfies(new VersionParser, $requirement, $constraint))) {
                        throw new RuntimeException('Incompatible or missing requirement: '.$package['name'].' / '.$requirement);
                    }
                }
                $path = realpath(InstalledVersions::getInstallPath($package['name']));
                $base = realpath(base_path());
                if (! $path || ! str_starts_with($path, $base.DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Plugin package must be inside the host artifact.');
                }
                $relative = substr($path, strlen($base) + 1);
                $meta = app(PluginManifests::class)->read($package, $resources->resource(['path' => $relative], 'composer.json'));
                $packages[] = ['package' => $package['name'], 'version' => $package['version'], 'path' => $relative, 'manifest' => $meta];
            }
            $catalog = new PluginCatalog($packages);
            $migrationNames = array_map('basename', glob(database_path('migrations/*.php')));
            foreach ($catalog->all() as $id => $package) {
                if (! is_subclass_of($package['manifest']['provider'], Plugin::class)) {
                    throw new RuntimeException('Invalid plugin entry point: '.$id);
                }
                foreach ($package['manifest']['migrations'] ?? [] as $migration) {
                    $name = basename($migration);
                    if (in_array($name, $migrationNames, true)) {
                        throw new RuntimeException('Duplicate migration filename: '.$name);
                    }
                    $migrationNames[] = $name;
                }
                foreach (array_merge($package['manifest']['routes'] ?? [], $package['manifest']['migrations'] ?? []) as $path) {
                    $resources->resource($package, $path);
                }
            }
            $this->buildThemes($installed);
            $data = ['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => array_values($catalog->all())];
            $target = config('extensions.catalog');
            $temporary = tempnam(dirname($target), 'extensions-');
            try {
                if (file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false || ! rename($temporary, $target)) {
                    throw new RuntimeException('Could not write plugin catalog.');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $this->info('Installed plugin catalog built: '.count($packages).' plugins.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function buildThemes(array $installed): void
    {
        $packages = [];
        foreach ($installed['packages'] ?? $installed as $package) {
            if (($package['extra']['mavyn']['kind'] ?? '') !== 'theme') {
                continue;
            }
            if (! empty($package['extra']['laravel']['providers']) || ! empty($package['autoload']['files'])) {
                throw new RuntimeException('Theme eager discovery/autoload is forbidden.');
            }
            foreach (['php', 'mavyn/core', 'mavyn/contracts'] as $requirement) {
                $constraint = $package['require'][$requirement] ?? null;
                if (! is_string($constraint) || ($requirement === 'php' ? ! Semver::satisfies(PHP_VERSION, $constraint) : ! InstalledVersions::satisfies(new VersionParser, $requirement, $constraint))) {
                    throw new RuntimeException('Incompatible theme requirement: '.$package['name']);
                }
            }
            $root = realpath(InstalledVersions::getInstallPath($package['name']));
            $base = realpath(base_path());
            if (! $root || ! str_starts_with($root, $base.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Theme outside host artifact.');
            }
            $packages[] = ['package' => $package['name'], 'version' => $package['version'], 'path' => substr($root, strlen($base) + 1), 'manifest' => $package['extra']['mavyn'], 'require' => $package['require']];
        }
        $catalog = new ThemeCatalog($packages);
        $resources = app(InstalledThemeCatalog::class);
        foreach ($catalog->all() as $id => $package) {
            $parent = $package['manifest']['parent'] ?? null;
            if ($parent !== null) {
                $parentPackage = $catalog->get($parent);
                $constraint = $package['require'][$parentPackage['package']] ?? null;
                if (! is_string($constraint) || ! Semver::satisfies($parentPackage['version'], $constraint)) {
                    throw new RuntimeException('Child must require a compatible Composer parent.');
                }
            }
            foreach ($package['manifest']['templates'] as $path) {
                $resources->resource($package, $path);
            }
            app(ThemeAssets::class)->manifest($package);
        }
        $target = config('themes.catalog');
        $temporary = tempnam(dirname($target), 'themes-');
        try {
            $data = ['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => array_values($catalog->all())];
            if (file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false || ! rename($temporary, $target)) {
                throw new RuntimeException('Could not write theme catalog.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        $this->info('Installed theme catalog built: '.count($packages).' themes.');
    }
}
