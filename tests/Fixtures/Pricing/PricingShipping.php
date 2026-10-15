<?php

namespace Tests\Fixtures\Pricing;

use Mavyn\Contracts\Plugins\SelectableShippingProvider;
use Mavyn\Contracts\Plugins\ShippingOption;
use Mavyn\Contracts\Plugins\ShippingQuote;
use Mavyn\Contracts\Plugins\ShippingRequest;
use Mavyn\Contracts\Pricing\PriceBook;

final class PricingShipping implements SelectableShippingProvider
{
    public function __construct(private PriceBook $prices) {}

    public function options(ShippingRequest $request): array
    {
        $row = $this->prices->read('zone', [1], $request->currency)[1] ?? null;

        return $row ? [new ShippingOption('standard', 'Fixture delivery', $row['amount_minor'], $request->currency)] : [];
    }

    public function quote(ShippingRequest $request): ?ShippingQuote
    {
        $option = $this->options($request)[0] ?? null;

        return $option && $request->zone === 'standard' ? new ShippingQuote($option->feeMinor, $option->currency, $option->label) : null;
    }
}
