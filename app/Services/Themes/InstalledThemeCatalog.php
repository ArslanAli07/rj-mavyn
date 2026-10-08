<?php

namespace App\Services\Themes;

use Mavyn\Core\Themes\ThemeCatalog;
use RuntimeException;

final class InstalledThemeCatalog
{
    public function load(): ThemeCatalog
    {
        $path = config('themes.catalog');
        if (! is_file($path)) {
            throw new RuntimeException('Build the installed theme catalog with platform:extensions:build.');
        }
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($data['format'] ?? null) !== 1 || ($data['lock_hash'] ?? null) !== hash_file('sha256', base_path('composer.lock'))) {
            throw new RuntimeException('Theme catalog is stale; run platform:extensions:build.');
        }

        return new ThemeCatalog($data['packages']);
    }

    public function resource(array $package, string $relative): string
    {
        $base = realpath(base_path());
        $root = realpath(base_path($package['path'] ?? ''));
        $path = $root ? realpath($root.'/'.$relative) : false;
        if (! $root || ! str_starts_with($root, $base.DIRECTORY_SEPARATOR) || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            throw new RuntimeException('Missing or unsafe theme resource.');
        }

        return $path;
    }

    public function verify(string $id): void
    {
        $catalog = $this->load();
        foreach ($catalog->chain($id) as $owner) {
            $package = $catalog->get($owner);
            foreach ($package['manifest']['templates'] as $path) {
                $this->resource($package, $path);
            }
            app(ThemeAssets::class)->manifest($package);
        }
    }
}
