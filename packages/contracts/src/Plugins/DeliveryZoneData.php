<?php

namespace Mavyn\Contracts\Plugins;

use InvalidArgumentException;

final readonly class DeliveryZoneData
{
    public function __construct(public int $id, public string $name, public int $feeMinor, public string $currency)
    {
        if ($id < 1 || $feeMinor < 0 || ! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Invalid delivery zone.');
        }
    }
}
