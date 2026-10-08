<?php

namespace Mavyn\Contracts\Plugins;

use InvalidArgumentException;

final readonly class ShippingRequest
{
    public function __construct(public int $subtotalMinor, public string $currency, public string $country, public ?string $postalCode = null, public ?string $zone = null)
    {
        if ($subtotalMinor < 0 || ! preg_match('/^[A-Z]{3}$/D', $currency) || ! preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new InvalidArgumentException('Invalid shipping request.');
        }
    }
}
