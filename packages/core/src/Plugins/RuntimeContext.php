<?php

namespace Mavyn\Core\Plugins;

use Closure;
use InvalidArgumentException;
use Mavyn\Contracts\Plugins\PluginContext;

final class RuntimeContext implements PluginContext
{
    public function __construct(private string $pluginId, private array $manifest, private HookBus $hooks, private ProviderRegistry $providers, private Closure $readSetting, private int $rank) {}

    public function id(): string
    {
        return $this->pluginId;
    }

    public function setting(string $key): mixed
    {
        return ($this->readSetting)($this->pluginId, $key);
    }

    public function action(string $hook, string $contribution, callable $callback, int $priority = 10): void
    {
        $this->hooks->add('action', $hook, $this->pluginId, $contribution, $callback, $priority, $this->rank);
    }

    public function filter(string $hook, string $contribution, callable $callback, int $priority = 10): void
    {
        $this->hooks->add('filter', $hook, $this->pluginId, $contribution, $callback, $priority, $this->rank);
    }

    public function provider(string $kind, string $key, string $class): void
    {
        $capability = ['payment' => 'payment.provider', 'fulfillment' => 'fulfillment.provider', 'product.attributes' => 'product.attributes', 'ordering.policy' => 'ordering.policy'][$kind] ?? '';
        if (! str_starts_with($key, $this->pluginId.'.') || ! preg_match('/^[a-z][a-z0-9_.-]*$/D', $key) || ! in_array($capability, $this->manifest['capabilities'] ?? [], true)) {
            throw new InvalidArgumentException('Provider must be namespaced and declare its capability.');
        }
        $this->providers->add($kind, $key, $class);
    }

    private function define(string $kind, string $hook, string $class): void
    {
        if (! str_starts_with($hook, $this->pluginId.'.') || ! preg_match('/^[a-z][a-z0-9_.-]*$/D', $hook)) {
            throw new InvalidArgumentException('Custom hook must be namespaced.');
        }
        $this->hooks->define($kind, $hook, $class);
    }

    public function defineAction(string $hook, string $payloadClass): void
    {
        $this->define('action', $hook, $payloadClass);
    }

    public function defineFilter(string $hook, string $valueClass): void
    {
        $this->define('filter', $hook, $valueClass);
    }
}
