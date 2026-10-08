<?php

namespace App\Services;

use App\Services\Plugins\PluginRuntime;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\DeliveryZoneStore;
use Mavyn\Contracts\Plugins\ShippingQuote;
use Mavyn\Contracts\Plugins\ShippingRequest;

final class LocalDeliveryService
{
    public function __construct(private PluginRuntime $runtime, private DeliveryZoneStore $zones) {}

    public function zones(): array
    {
        return in_array('mavyn.local-delivery.zone', $this->runtime->providers()->keys('fulfillment'), true) ? $this->zones->active() : [];
    }

    public function quote(int $zoneId, int $subtotalMinor, string $country): ShippingQuote
    {
        $registry = $this->runtime->providers();
        if (! in_array('mavyn.local-delivery.zone', $registry->keys('fulfillment'), true)) {
            throw ValidationException::withMessages(['delivery_zone_id' => 'Local delivery is unavailable.']);
        }
        if (! in_array(strtolower(trim($country)), ['pakistan', 'pk'], true)) {
            throw ValidationException::withMessages(['country' => 'Local delivery is unavailable in this country.']);
        }
        $quote = $registry->get('fulfillment', 'mavyn.local-delivery.zone')->quote(new ShippingRequest($subtotalMinor, 'PKR', 'PK', zone: (string) $zoneId));
        if (! $quote || $quote->currency !== 'PKR') {
            throw ValidationException::withMessages(['delivery_zone_id' => 'Choose an available delivery zone.']);
        }

        return $quote;
    }
}
