<?php

namespace App\Services\Plugins;

use RuntimeException;

/** Plugin metadata may change without changing Composer's resolved dependency graph. */
final class PluginManifests
{
    public function read(array $installed, string $path): array
    {
        $source = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $manifest = $source['extra']['mavyn'] ?? null;
        if (($source['name'] ?? null) !== $installed['name'] || ! is_array($manifest)
            || ($manifest['kind'] ?? null) !== 'plugin'
            || ($manifest['id'] ?? null) !== ($installed['extra']['mavyn']['id'] ?? null)) {
            throw new RuntimeException('Plugin identity changed; resolve the package with Composer before loading it.');
        }
        if (! empty($source['autoload']['files']) || ! empty($source['extra']['laravel']['providers'])) {
            throw new RuntimeException('Plugin eager autoload/discovery is forbidden: '.$installed['name']);
        }
        foreach (['require', 'autoload', 'replace', 'provide', 'conflict'] as $field) {
            if (($source[$field] ?? []) != ($installed[$field] ?? [])) {
                throw new RuntimeException('Plugin '.$field.' changed for '.$installed['name'].'; run composer update '.$installed['name'].' before loading it.');
            }
        }
        if (($source['type'] ?? 'library') !== ($installed['type'] ?? 'library')) {
            throw new RuntimeException('Plugin package type changed; resolve the package with Composer.');
        }

        return $manifest;
    }

    public function localCatalog(array $packages, callable $resource): array
    {
        if (! app()->environment('local') || ! config('extensions.live_manifests', true) || $packages === []) {
            return $packages;
        }
        $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true, 512, JSON_THROW_ON_ERROR);
        $byName = array_column($installed['packages'] ?? $installed, null, 'name');
        foreach ($packages as &$package) {
            $metadata = $byName[$package['package']] ?? null;
            if (($metadata['extra']['mavyn']['kind'] ?? null) !== 'plugin') {
                throw new RuntimeException('Development plugins must first be installed through Composer.');
            }
            $manifest = $this->read($metadata, $resource($package, 'composer.json'));
            if ($manifest['id'] !== $package['manifest']['id']) {
                throw new RuntimeException('Plugin catalog identity is stale; run platform:extensions:build.');
            }
            $package['manifest'] = $manifest;
        }

        return $packages;
    }
}
