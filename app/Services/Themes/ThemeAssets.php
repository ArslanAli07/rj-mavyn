<?php

namespace App\Services\Themes;

use Illuminate\Support\Facades\File;
use Mavyn\Core\Themes\ThemeCatalog;
use RuntimeException;

final class ThemeAssets
{
    public function __construct(private InstalledThemeCatalog $catalogs) {}

    public function manifest(array $package): array
    {
        $path = $this->catalogs->resource($package, $package['manifest']['asset_manifest']);
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || array_diff(array_keys($manifest), ['format', 'styles', 'scripts', 'files'])) {
            throw new RuntimeException('Invalid theme asset manifest.');
        }
        foreach (['styles' => 'css', 'scripts' => 'js'] as $kind => $extension) {
            if (! is_array($manifest[$kind] ?? null)) {
                throw new RuntimeException('Invalid theme asset list.');
            }
            foreach ($manifest[$kind] as $entry) {
                ThemeCatalog::path($entry['path'] ?? null, $extension);
                if (! preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'] ?? '') || hash_file('sha256', $this->catalogs->resource($package, $entry['path'])) !== $entry['sha256']) {
                    throw new RuntimeException('Theme asset checksum mismatch.');
                }
            }
        }

        if (! is_array($manifest['files'] ?? [])) {
            throw new RuntimeException('Invalid theme file list.');
        }
        foreach ($manifest['files'] ?? [] as $name => $entry) {
            $extension = pathinfo($entry['path'] ?? '', PATHINFO_EXTENSION);
            if (! is_string($name) || ! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'woff2'], true)) {
                throw new RuntimeException('Unsupported packaged file.');
            }
            ThemeCatalog::path($entry['path'], $extension);
            if (! preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'] ?? '') || hash_file('sha256', $this->catalogs->resource($package, $entry['path'])) !== $entry['sha256']) {
                throw new RuntimeException('Theme file checksum mismatch.');
            }
        }

        return $manifest;
    }

    private function relative(array $package, array $entry): string
    {
        return 'themes/'.$package['manifest']['id'].'/'.$entry['sha256'].'/'.basename($entry['path']);
    }

    public function publish(): void
    {
        foreach ($this->catalogs->load()->all() as $package) {
            $manifest = $this->manifest($package);
            foreach ([...$manifest['styles'], ...$manifest['scripts'], ...array_values($manifest['files'] ?? [])] as $entry) {
                $destination = public_path($this->relative($package, $entry));
                File::ensureDirectoryExists(dirname($destination));
                if (! str_starts_with(realpath(dirname($destination)), realpath(public_path()).DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Unsafe public theme asset directory.');
                }
                $temporary = tempnam(dirname($destination), 'theme-');
                try {
                    if (! copy($this->catalogs->resource($package, $entry['path']), $temporary) || ! rename($temporary, $destination)) {
                        throw new RuntimeException('Could not publish theme asset.');
                    }
                } finally {
                    if (is_file($temporary)) {
                        unlink($temporary);
                    }
                }
            }
        }
    }

    public function urls(array $chain): array
    {
        $result = ['styles' => [], 'scripts' => [], 'files' => []];
        $catalog = $this->catalogs->load();
        foreach (array_reverse($chain) as $id) {
            $package = $catalog->get($id);
            foreach ($this->manifest($package) as $kind => $entries) {
                if (! isset($result[$kind])) {
                    continue;
                }
                foreach ($entries as $name => $entry) {
                    $path = $this->relative($package, $entry);
                    if (! is_file(public_path($path)) || hash_file('sha256', public_path($path)) !== $entry['sha256']) {
                        throw new RuntimeException('Publish theme assets with platform:themes:assets.');
                    }
                    if ($kind === 'files') {
                        $result[$kind][$name] = asset($path);
                    } else {
                        $result[$kind][] = asset($path);
                    }
                }
            }
        }

        return $result;
    }
}
