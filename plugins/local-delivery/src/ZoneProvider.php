<?php

namespace Mavyn\LocalDelivery;

use Mavyn\Contracts\Plugins\DeliveryZoneStore;
use Mavyn\Contracts\Plugins\ShippingProvider;
use Mavyn\Contracts\Plugins\ShippingQuote;
use Mavyn\Contracts\Plugins\ShippingRequest;

final class ZoneProvider implements ShippingProvider
{
    public function __construct(private DeliveryZoneStore $zones) {}

    public function quote(ShippingRequest $request): ?ShippingQuote
    {
        if ($request->country !== 'PK' || $request->currency !== 'PKR' || ! ctype_digit($request->zone ?? '')) {
            return null;
        }
        $zone = $this->zones->findAvailable((int) $request->zone);

        return $zone && $zone->currency === $request->currency ? new ShippingQuote($zone->feeMinor, $zone->currency, $zone->name) : null;
    }
}
