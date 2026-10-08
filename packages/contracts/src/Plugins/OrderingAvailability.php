<?php

namespace Mavyn\Contracts\Plugins;

final readonly class OrderingAvailability
{
    public function __construct(public bool $manuallyEnabled, public bool $open, public string $hoursLabel) {}

    public function allowed(): bool
    {
        return $this->manuallyEnabled && $this->open;
    }
}
