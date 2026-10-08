<?php

namespace Mavyn\Contracts\Plugins;

interface PluginContext
{
    public function id(): string;

    public function setting(string $key): mixed;

    public function action(string $hook, string $contribution, callable $callback, int $priority = 10): void;

    public function filter(string $hook, string $contribution, callable $callback, int $priority = 10): void;

    public function provider(string $kind, string $key, string $class): void;

    public function defineAction(string $hook, string $payloadClass): void;

    public function defineFilter(string $hook, string $valueClass): void;
}
