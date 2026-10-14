<?php

namespace App\Services\Themes;

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
                $resource = $this->catalogs->resource($package, $entry['path']);
                if (pathinfo($resource, PATHINFO_EXTENSION) !== $extension || ! preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'] ?? '') || hash_file('sha256', $resource) !== $entry['sha256']) {
                    throw new RuntimeException('Theme asset checksum mismatch.');
                }
            }
        }

        if (! is_array($manifest['files'] ?? [])) {
            throw new RuntimeException('Invalid theme file list.');
        }
        foreach ($manifest['files'] ?? [] as $name => $entry) {
            $extension = pathinfo($entry['path'] ?? '', PATHINFO_EXTENSION);
            if (! is_string($name) || ! in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'mp4', 'webm', 'webmanifest'], true)) {
                throw new RuntimeException('Unsupported packaged file.');
            }
            ThemeCatalog::path($entry['path'], $extension);
            $resource = $this->catalogs->resource($package, $entry['path']);
            if (pathinfo($resource, PATHINFO_EXTENSION) !== $extension || ! preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'] ?? '') || hash_file('sha256', $resource) !== $entry['sha256']) {
                throw new RuntimeException('Theme file checksum mismatch.');
            }
        }

        return $manifest;
    }

    private function revision(array $manifest): string
    {
        return hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** Retained for deployment-script compatibility; assets stay in their packages. */
    public function publish(): void
    {
        foreach ($this->catalogs->load()->all() as $package) {
            $this->manifest($package);
        }
    }

    /** Resolve only declared, checksum-verified files, never arbitrary package contents. */
    public function resource(string $id, string $revision, string $path): array
    {
        $package = $this->catalogs->load()->get($id);
        $manifest = $this->manifest($package);
        if (! hash_equals($this->revision($manifest), $revision)) {
            throw new RuntimeException('Theme asset revision is unavailable.');
        }
        foreach ([...$manifest['styles'], ...$manifest['scripts'], ...array_values($manifest['files'] ?? [])] as $entry) {
            if ($entry['path'] === $path) {
                return ['path' => $this->catalogs->resource($package, $path), 'sha256' => $entry['sha256']];
            }
        }
        throw new RuntimeException('Theme asset is not declared.');
    }

    public function urls(array $chain): array
    {
        $result = ['styles' => [], 'scripts' => [], 'files' => []];
        $catalog = $this->catalogs->load();
        foreach (array_reverse($chain) as $id) {
            $package = $catalog->get($id);
            $manifest = $this->manifest($package);
            $revision = $this->revision($manifest);
            foreach ($manifest as $kind => $entries) {
                if (! isset($result[$kind])) {
                    continue;
                }
                foreach ($entries as $name => $entry) {
                    $url = route('theme-assets.show', ['theme' => $id, 'revision' => $revision, 'path' => $entry['path']]);
                    if ($kind === 'files') {
                        $result[$kind][$name] = $url;
                    } else {
                        $result[$kind][] = $url;
                    }
                }
            }
        }

        return $result;
    }
}
