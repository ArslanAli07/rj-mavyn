<?php

namespace Mavyn\Contracts\Plugins;

use InvalidArgumentException;

final readonly class ProductAttributeField
{
    public function __construct(public string $key, public string $label, public string $target = 'product', public string $input = 'text', public int $maxLength = 500)
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/D', $key) || ! in_array($target, ['product', 'variant'], true) || ! in_array($input, ['text', 'textarea'], true) || $maxLength < 1 || $maxLength > 10000) {
            throw new InvalidArgumentException('Invalid attribute field.');
        }
    }
}
