<?php

namespace Mavyn\Core\Plugins;

use InvalidArgumentException;
use UnexpectedValueException;

final class HookBus
{
    private bool $sealed = false;

    public function seal(): void
    {
        $this->sealed = true;
    }

    private array $definitions = [];

    private array $callbacks = [];

    public function define(string $kind, string $hook, string $class): void
    {
        if ($this->sealed || ! in_array($kind, ['action', 'filter'], true) || ! class_exists($class) || ! (new \ReflectionClass($class))->isReadOnly() || isset($this->definitions[$hook])) {
            throw new InvalidArgumentException('Invalid or duplicate hook: '.$hook);
        }
        $this->definitions[$hook] = [$kind, $class];
    }

    public function add(string $kind, string $hook, string $plugin, string $contribution, callable $callback, int $priority, int $rank): void
    {
        if ($this->sealed || ($this->definitions[$hook][0] ?? null) !== $kind || ! preg_match('/^[a-z][a-z0-9_.-]*$/D', $contribution) || isset($this->callbacks[$hook][$plugin."\0".$contribution])) {
            throw new InvalidArgumentException('Invalid or duplicate contribution: '.$hook);
        }
        $this->callbacks[$hook][$plugin."\0".$contribution] = [$priority, $rank, $plugin, $contribution, $callback];
    }

    private function callbacks(string $kind, string $hook, object $value): array
    {
        [$declaredKind, $class] = $this->definitions[$hook] ?? throw new InvalidArgumentException('Unknown hook: '.$hook);
        if ($kind !== $declaredKind || ! $value instanceof $class) {
            throw new InvalidArgumentException('Invalid hook payload.');
        }
        $callbacks = array_values($this->callbacks[$hook] ?? []);
        usort($callbacks, fn ($a, $b) => array_slice($a, 0, 4) <=> array_slice($b, 0, 4));

        return $callbacks;
    }

    public function dispatch(string $hook, object $payload): void
    {
        foreach ($this->callbacks('action', $hook, $payload) as $callback) {
            ($callback[4])($payload);
        }
    }

    public function apply(string $hook, object $value): object
    {
        $class = $this->definitions[$hook][1] ?? null;
        foreach ($this->callbacks('filter', $hook, $value) as $callback) {
            $value = ($callback[4])($value);
            if (! $value instanceof $class) {
                throw new UnexpectedValueException('Invalid filter result: '.$hook);
            }
        }

        return $value;
    }
}
