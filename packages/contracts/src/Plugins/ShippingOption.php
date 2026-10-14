<?php

namespace Mavyn\Contracts\Plugins;

use InvalidArgumentException;

final readonly class ShippingOption
{
    public function __construct(public string $id, public string $label, public int $feeMinor, public string $currency)
    {
        if (! preg_match('/^[a-zA-Z0-9_.-]{1,100}$/D', $id) || trim($label) === '' || strlen($label) > 255 || $feeMinor < 0 || ! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Invalid shipping option.');
        }
    }
}
