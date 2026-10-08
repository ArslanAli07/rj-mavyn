<?php

namespace Mavyn\Core\Themes;

use Composer\Semver\Semver;
use InvalidArgumentException;
use Mavyn\Contracts\Themes\TemplateInventory;

/** Pure manifest, inheritance, settings, and enabled-plugin compatibility rules. */
final class ThemeCatalog
{
    public const DEFAULT = 'mavyn.theme-default';

    private array $packages = [];

    public function __construct(array $packages)
    {
        foreach ($packages as $package) {
            $manifest = $package['manifest'] ?? [];
            $id = $manifest['id'] ?? '';
            if (! is_string($id) || strlen($id) > 100 || ! preg_match('/^[a-z][a-z0-9-]*\.[a-z][a-z0-9.-]*$/D', $id) || isset($this->packages[$id]) || ($manifest['kind'] ?? '') !== 'theme' || ! is_string($manifest['name'] ?? null) || array_diff(array_keys($manifest), ['kind', 'id', 'name', 'contract', 'parent', 'requires_active', 'settings', 'templates', 'asset_manifest'])) {
                throw new InvalidArgumentException('Invalid or duplicate theme manifest.');
            }
            if (! Semver::satisfies(TemplateInventory::VERSION, $manifest['contract'] ?? '')) {
                throw new InvalidArgumentException('Incompatible theme template contract: '.$id);
            }
            foreach (['templates', 'requires_active', 'settings'] as $field) {
                if (! is_array($manifest[$field] ?? [])) {
                    throw new InvalidArgumentException('Invalid theme '.$field);
                }
            }
            foreach ($manifest['templates'] ?? [] as $name => $path) {
                if (! in_array($name, TemplateInventory::NAMES, true)) {
                    throw new InvalidArgumentException('Unsupported theme template: '.$name);
                }
                self::path($path, 'blade.php');
            }
            self::path($manifest['asset_manifest'] ?? '', 'json');
            foreach ($manifest['settings'] ?? [] as $key => $definition) {
                new ThemeSetting($key, $definition);
            }
            foreach ($manifest['requires_active'] ?? [] as $plugin => $constraint) {
                if (! is_string($plugin) || ! is_string($constraint)) {
                    throw new InvalidArgumentException('Invalid theme dependency.');
                }
                Semver::satisfies('0.1.0', $constraint); // Parse even when currently inactive.
            }
            $this->packages[$id] = $package;
        }
        $default = $this->get(self::DEFAULT);
        if (($default['manifest']['parent'] ?? null) !== null || ($default['manifest']['requires_active'] ?? []) !== [] || array_diff(TemplateInventory::NAMES, array_keys($default['manifest']['templates'] ?? []))) {
            throw new InvalidArgumentException('Default theme must provide every template without plugin dependencies.');
        }
        foreach (array_keys($this->packages) as $id) {
            $this->chain($id);
            $this->settings($id);
        }
    }

    public static function path(mixed $path, string $extension): void
    {
        if (! is_string($path) || ! preg_match('/^[A-Za-z0-9_\/-]+\.'.preg_quote($extension, '/').'$/D', $path) || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
            throw new InvalidArgumentException('Unsafe theme resource path.');
        }
    }

    public function all(): array
    {
        return $this->packages;
    }

    public function get(string $id): array
    {
        return $this->packages[$id] ?? throw new InvalidArgumentException('Theme is not installed: '.$id);
    }

    /** Resolution order, with the mandatory default fallback last. */
    public function chain(string $id): array
    {
        $theme = $this->get($id);
        $chain = [$id];
        $parent = $theme['manifest']['parent'] ?? null;
        if ($parent !== null) {
            if (! is_string($parent) || $parent === $id || ($this->get($parent)['manifest']['parent'] ?? null) !== null) {
                throw new InvalidArgumentException('Only one parent level is supported.');
            }
            $chain[] = $parent;
        }
        if (! in_array(self::DEFAULT, $chain, true)) {
            $chain[] = self::DEFAULT;
        }

        return $chain;
    }

    public function settings(string $id): array
    {
        $fields = [];
        foreach (array_reverse($this->chain($id)) as $owner) {
            foreach ($this->get($owner)['manifest']['settings'] ?? [] as $key => $definition) {
                if (isset($fields[$key]) && $fields[$key]->definition['type'] !== $definition['type']) {
                    throw new InvalidArgumentException('Inherited setting type conflict: '.$key);
                }
                $fields[$key] = new ThemeSetting($key, $definition);
            }
        }

        return $fields;
    }

    public function validatePlugins(string $id, array $enabledVersions): void
    {
        foreach ($this->chain($id) as $owner) {
            foreach ($this->get($owner)['manifest']['requires_active'] ?? [] as $plugin => $constraint) {
                if (! isset($enabledVersions[$plugin]) || ! Semver::satisfies($enabledVersions[$plugin], $constraint)) {
                    throw new InvalidArgumentException('Theme requires active plugin: '.$plugin.' '.$constraint);
                }
            }
        }
    }
}
