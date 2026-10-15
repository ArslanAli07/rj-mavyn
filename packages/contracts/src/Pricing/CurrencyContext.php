<?php

namespace Mavyn\Contracts\Pricing;

final readonly class CurrencyContext
{
    public function __construct(
        public string $currency,
        public string $symbol,
        public int $revision,
        public array $enabled,
        public bool $ready,
        public int $precision = 2,
    ) {}
}
