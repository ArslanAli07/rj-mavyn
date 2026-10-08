<?php

namespace Mavyn\Core\Themes;

use InvalidArgumentException;
use Mavyn\Core\Plugins\PluginSetting;

final readonly class ThemeSetting
{
    public function __construct(public string $key, public array $definition)
    {
        if (array_diff(array_keys($definition), ['type', 'default', 'nullable', 'min', 'max']) || ! in_array($definition['type'] ?? '', ['text', 'boolean', 'integer', 'color'], true)) {
            throw new InvalidArgumentException('Unsupported theme setting: '.$key);
        }
        $this->normalize($definition['default'] ?? null);
    }

    public function normalize(mixed $value): mixed
    {
        $definition = $this->definition;
        if ($definition['type'] === 'color') {
            if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) {
                throw new InvalidArgumentException('Must be a six-digit hex color.');
            }
            $definition['type'] = 'text';
        }

        return (new PluginSetting($this->key, $definition))->normalize($value);
    }
}
