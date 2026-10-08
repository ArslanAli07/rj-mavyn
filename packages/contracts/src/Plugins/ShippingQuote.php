<?php

namespace Mavyn\Contracts\Plugins;

use InvalidArgumentException;

final readonly class ShippingQuote
{
    public function __construct(public int $feeMinor, public string $currency, public string $label)
    {
        if ($feeMinor < 0 || ! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Invalid shipping quote.');
        }
    }
}
