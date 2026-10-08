<?php

namespace Mavyn\Core\Plugins;

use InvalidArgumentException;
use Mavyn\Core\Money;

final readonly class PluginSetting
{
    public function __construct(public string $key, public array $definition)
    {
        if (strlen($key) > 100 || ! preg_match('/^[a-z][a-z0-9_]*$/D', $key) || array_diff(array_keys($definition), ['type', 'default', 'nullable', 'min', 'max', 'secret', 'permission']) || ! in_array($definition['type'] ?? null, ['text', 'boolean', 'integer', 'decimal', 'email', 'url', 'time'], true) || ! array_key_exists('default', $definition)) {
            throw new InvalidArgumentException('Invalid setting definition: '.$key);
        }
        foreach (['secret', 'nullable'] as $flag) {
            if (isset($definition[$flag]) && ! is_bool($definition[$flag])) {
                throw new InvalidArgumentException('Invalid setting flag.');
            }
        }
        foreach (['min', 'max'] as $bound) {
            if (isset($definition[$bound]) && ! is_int($definition[$bound])) {
                throw new InvalidArgumentException('Invalid setting bound.');
            }
        }
        if (($definition['min'] ?? 0) > ($definition['max'] ?? PHP_INT_MAX)) {
            throw new InvalidArgumentException('Invalid setting bounds.');
        }
        $this->normalize($definition['default']);
    }

    public function secret(): bool
    {
        return $this->definition['secret'] ?? false;
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null && ($this->definition['nullable'] ?? false)) {
            return null;
        }
        $type = $this->definition['type'];
        if ($type === 'boolean') {
            if (! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                throw new InvalidArgumentException('Must be a boolean.');
            }

            return (bool) $value;
        }
        if ($type === 'integer') {
            if (is_bool($value) || (! is_int($value) && (! is_string($value) || ! preg_match('/^-?\d+$/D', $value))) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException('Must be an integer.');
            }
            $value = (int) $value;
        } elseif ($type === 'decimal') {
            if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^-?\d+(?:\.\d{1,2})?$/D', (string) $value)) {
                throw new InvalidArgumentException('Must be a two-decimal amount.');
            }
            $value = Money::decimal(Money::minor($value));
        } else {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Must be text.');
            }
            if ($type === 'time' && ! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) {
                throw new InvalidArgumentException('Must be a valid HH:MM time.');
            }
            if ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Must be an email address.');
            }
            if ($type === 'url' && (! filter_var($value, FILTER_VALIDATE_URL) || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true))) {
                throw new InvalidArgumentException('Must be an HTTP(S) URL.');
            }
        }
        $size = in_array($type, ['integer', 'decimal'], true) ? ($type === 'decimal' ? Money::minor($value) : $value) : mb_strlen($value);
        if ($size < ($this->definition['min'] ?? (in_array($type, ['integer', 'decimal'], true) ? PHP_INT_MIN : 0)) || $size > ($this->definition['max'] ?? (in_array($type, ['integer', 'decimal'], true) ? PHP_INT_MAX : 2048))) {
            throw new InvalidArgumentException('Value is outside allowed bounds.');
        }

        return $value;
    }
}
