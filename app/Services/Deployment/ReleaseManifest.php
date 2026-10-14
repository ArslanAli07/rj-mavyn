<?php

namespace App\Services\Deployment;

use Composer\InstalledVersions;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** A deterministic code inventory. Client environments, storage, uploads and runtime caches are excluded. */
final class ReleaseManifest
{
    private const TREES = ['app', 'config', 'database/migrations', 'packages', 'plugins', 'themes', 'resources', 'routes', 'public/build'];

    private const FILES = ['artisan', 'bootstrap/app.php', 'bootstrap/providers.php', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js', 'public/index.php', 'public/.htaccess', 'public/images/placeholder.svg'];

    public function inventory(): array
    {
        $files = [];
        foreach (self::TREES as $tree) {
            if (! is_dir(base_path($tree))) {
                if (in_array($tree, ['plugins', 'themes'], true)) {
                    continue;
                }
                throw new RuntimeException('Release source tree is missing.');
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($tree), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->isLink()) {
                    throw new RuntimeException('Release source symlinks are not supported.');
                }
                if ($file->isFile()) {
                    $files[$tree.'/'.substr($file->getPathname(), strlen(base_path($tree)) + 1)] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        foreach (self::FILES as $file) {
            if (! is_file(base_path($file))) {
                throw new RuntimeException('Release source file is missing.');
            }
            $files[$file] = hash_file('sha256', base_path($file));
        }
        ksort($files);

        return $files;
    }

    private function vendorHash(): string
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('vendor'), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $name = substr($file->getPathname(), strlen(base_path('vendor')) + 1);
            if ($file->isLink()) {
                $target = realpath($file->getPathname());
                if (! str_starts_with($name, 'mavyn/') || ! $target || ! str_starts_with($target, realpath(base_path()).DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Unsafe dependency symlink.');
                }
                $files[$name] = hash('sha256', 'link:'.substr($target, strlen(realpath(base_path())) + 1));
            } elseif ($file->isFile()) {
                $files[$name] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($files);

        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    public function build(string $id): array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/D', $id)) {
            throw new RuntimeException('Invalid release identifier.');
        }
        $files = $this->inventory();
        $versions = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $versions[$name] = ['version' => InstalledVersions::getPrettyVersion($name), 'reference' => InstalledVersions::getReference($name)];
        }
        ksort($versions);

        return ['format' => 1, 'release' => $id, 'source_hash' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR)), 'composer_lock_hash' => $files['composer.lock'], 'frontend_lock_hash' => $files['package-lock.json'], 'vendor_hash' => $this->vendorHash(), 'versions' => $versions, 'migrations' => array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), array_keys(array_filter($files, fn ($hash, $name) => str_starts_with($name, 'database/migrations/'), ARRAY_FILTER_USE_BOTH))), 'files' => $files];
    }

    public function verify(array $manifest): void
    {
        if (($manifest['format'] ?? null) !== 1 || ! is_string($manifest['release'] ?? null)) {
            throw new RuntimeException('Invalid release manifest.');
        }
        $actual = $this->build($manifest['release']);
        foreach (['source_hash', 'composer_lock_hash', 'frontend_lock_hash', 'vendor_hash', 'versions', 'migrations', 'files'] as $field) {
            if (($manifest[$field] ?? null) !== $actual[$field]) {
                throw new RuntimeException('Release inventory mismatch: '.$field);
            }
        }
    }
}
