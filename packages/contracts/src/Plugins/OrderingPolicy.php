<?php

namespace Mavyn\Contracts\Plugins;

use DateTimeImmutable;

interface OrderingPolicy
{
    public function evaluate(DateTimeImmutable $localTime): OrderingAvailability;
}
