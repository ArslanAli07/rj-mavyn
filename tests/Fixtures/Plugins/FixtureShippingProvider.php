<?php

namespace Tests\Fixtures\Plugins;

use Mavyn\Contracts\Plugins\SelectableShippingProvider;
use Mavyn\Contracts\Plugins\ShippingOption;
use Mavyn\Contracts\Plugins\ShippingQuote;
use Mavyn\Contracts\Plugins\ShippingRequest;

final class FixtureShippingProvider implements SelectableShippingProvider
{
    public function options(ShippingRequest $request): array
    {
        return [new ShippingOption('standard', 'Fixture shipping', 250, $request->currency)];
    }

    public function quote(ShippingRequest $request): ?ShippingQuote
    {
        return $request->zone === 'standard' ? new ShippingQuote(250, $request->currency, 'Fixture shipping') : null;
    }
}
