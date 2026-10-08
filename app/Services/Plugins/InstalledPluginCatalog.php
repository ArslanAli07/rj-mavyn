<?php

namespace App\Services\Plugins;

use Mavyn\Core\Plugins\PluginCatalog;
use RuntimeException;

final class InstalledPluginCatalog
{
    public function load(): PluginCatalog
    {
        $path = config('extensions.catalog');
        if (! is_file($path)) {
            $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($installed['packages'] ?? $installed as $package) {
                if (($package['extra']['mavyn']['kind'] ?? null) === 'plugin') {
                    throw new RuntimeException('Installed plugins require platform:extensions:build.');
                }
            }

            return new PluginCatalog([]);
        }
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($data['format'] ?? null) !== 1 || ($data['lock_hash'] ?? null) !== hash_file('sha256', base_path('composer.lock'))) {
            throw new RuntimeException('Plugin catalog is stale; run platform:extensions:build.');
        }
        $catalog = new PluginCatalog($data['packages']);
        foreach ($catalog->all() as $plugin) {
            $this->resource($plugin, 'composer.json');
        }

        return $catalog;
    }

    public function resource(array $plugin, string $relative): string
    {
        $root = realpath(base_path($plugin['path'] ?? ''));
        $base = realpath(base_path());
        $path = $root ? realpath($root.'/'.$relative) : false;
        if (! $root || ! str_starts_with($root, $base.DIRECTORY_SEPARATOR) || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            throw new RuntimeException('Missing or invalid installed plugin resource.');
        }

        return $path;
    }
}
