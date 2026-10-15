<?php

namespace Mavyn\Core\Plugins;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use InvalidArgumentException;

final class PluginCatalog
{
    private array $plugins = [];

    public function __construct(array $packages)
    {
        foreach ($packages as $package) {
            $meta = $package['manifest'] ?? [];
            $id = $meta['id'] ?? '';
            if (($meta['kind'] ?? null) !== 'plugin' || ! self::validId($id) || isset($this->plugins[$id])
                || ! is_string($meta['name'] ?? null) || $meta['name'] === '' || strlen($meta['name']) > 100
                || ! is_string($meta['provider'] ?? null) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/D', $meta['provider'])
                || ! is_string($package['package'] ?? null) || ! is_string($package['version'] ?? null)
                || array_diff(array_keys($meta), ['kind', 'id', 'name', 'provider', 'requires_active', 'conflicts_with', 'capabilities', 'permissions', 'settings', 'routes', 'migrations'])) {
                throw new InvalidArgumentException('Invalid or duplicate plugin manifest: '.$id);
            }
            (new VersionParser)->normalize($package['version']);
            foreach (['requires_active', 'conflicts_with', 'capabilities', 'permissions', 'settings', 'routes', 'migrations'] as $field) {
                if (isset($meta[$field]) && ! is_array($meta[$field])) {
                    throw new InvalidArgumentException('Invalid manifest field: '.$field);
                }
            }
            foreach ($meta['requires_active'] ?? [] as $dependency => $constraint) {
                if (! self::validId($dependency) || $dependency === $id || ! is_string($constraint)) {
                    throw new InvalidArgumentException('Invalid active dependency.');
                }
                (new VersionParser)->parseConstraints($constraint);
            }
            foreach ($meta['conflicts_with'] ?? [] as $conflict) {
                if (! self::validId($conflict) || $conflict === $id) {
                    throw new InvalidArgumentException('Invalid conflict.');
                }
            }
            foreach ($meta['capabilities'] ?? [] as $capability) {
                if (! in_array($capability, ['payment.provider', 'fulfillment.provider', 'product.attributes', 'ordering.policy'], true)) {
                    throw new InvalidArgumentException('Unknown capability.');
                }
            }
            foreach ($meta['permissions'] ?? [] as $permission => $label) {
                if (! str_starts_with($permission, $id.'.') || ! is_string($label) || $label === '' || ! preg_match('/^'.preg_quote($id, '/').'\.[a-z][a-z0-9-]*$/D', $permission)) {
                    throw new InvalidArgumentException('Invalid plugin permission.');
                }
            }
            foreach ($meta['settings'] ?? [] as $key => $definition) {
                if (! is_array($definition)) {
                    throw new InvalidArgumentException('Invalid setting definition.');
                }
                $setting = new PluginSetting($key, $definition);
                if (isset($definition['permission']) && ! in_array($definition['permission'], ['extensions.manage', 'settings.manage'], true) && ! isset($meta['permissions'][$definition['permission']])) {
                    throw new InvalidArgumentException('Undeclared setting permission.');
                }
                if ($setting->secret() && ! in_array($definition['default'], ['', null], true)) {
                    throw new InvalidArgumentException('Secret defaults must be empty.');
                }
            }
            if (array_diff(array_keys($meta['routes'] ?? []), ['admin', 'storefront', 'callbacks'])) {
                throw new InvalidArgumentException('Unknown route scope.');
            }
            if (isset($meta['routes']['callbacks']) && ! in_array('payment.provider', $meta['capabilities'] ?? [], true)) {
                throw new InvalidArgumentException('Payment callbacks require the payment.provider capability.');
            }
            if (isset($meta['routes']['admin']) && ! isset($meta['permissions'][$id.'.manage'])) {
                throw new InvalidArgumentException('Admin routes require a declared manage permission.');
            }
            foreach ($meta['routes'] ?? [] as $route) {
                if (! is_string($route) || ! str_ends_with($route, '.php')) {
                    throw new InvalidArgumentException('Route resources must be PHP files.');
                }
            }
            foreach ($meta['migrations'] ?? [] as $migration) {
                $prefix = str_replace(['.', '-'], '_', $id);
                if (! is_string($migration) || ! preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_'.preg_quote($prefix, '/').'_[a-z0-9_]+\.php$/D', basename($migration))) {
                    throw new InvalidArgumentException('Migration filenames must be namespaced.');
                }
            }
            foreach (array_merge($meta['routes'] ?? [], $meta['migrations'] ?? []) as $path) {
                if (! is_string($path) || ! preg_match('~^[a-zA-Z0-9_./-]+$~D', $path) || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
                    throw new InvalidArgumentException('Invalid resource path.');
                }
            }
            $this->plugins[$id] = $package;
        }
        ksort($this->plugins);
    }

    public static function validId(mixed $id): bool
    {
        return is_string($id) && strlen($id) <= 100 && (bool) preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)+$/D', $id);
    }

    public function all(): array
    {
        return $this->plugins;
    }

    public function get(string $id): array
    {
        return $this->plugins[$id] ?? throw new InvalidArgumentException('Plugin is not installed: '.$id);
    }

    public function permissions(): array
    {
        $permissions = [];
        foreach ($this->plugins as $plugin) {
            $permissions += $plugin['manifest']['permissions'] ?? [];
        }

        return $permissions;
    }

    /** Validate complete enabled set, then sort dependencies before dependents. */
    public function order(array $active): array
    {
        $active = array_fill_keys($active, true);
        $visited = [];
        $visiting = [];
        $ordered = [];
        $visit = function (string $id) use (&$visit, &$visited, &$visiting, &$ordered, $active) {
            if (isset($visiting[$id])) {
                throw new InvalidArgumentException('Active plugin dependency cycle: '.$id);
            }
            if (isset($visited[$id])) {
                return;
            }
            $plugin = $this->get($id);
            $meta = $plugin['manifest'];
            $visiting[$id] = true;
            foreach ($meta['requires_active'] ?? [] as $dependency => $constraint) {
                if (! isset($active[$dependency]) || ! Semver::satisfies($this->get($dependency)['version'], $constraint)) {
                    throw new InvalidArgumentException('Missing or incompatible active dependency: '.$dependency);
                }
                $visit($dependency);
            }
            foreach ($meta['conflicts_with'] ?? [] as $conflict) {
                if (isset($active[$conflict])) {
                    throw new InvalidArgumentException('Conflicting active plugins: '.$id.' / '.$conflict);
                }
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            $ordered[] = $id;
        };
        $ids = array_keys($active);
        sort($ids);
        foreach ($ids as $id) {
            $visit($id);
        }

        return $ordered;
    }
}
