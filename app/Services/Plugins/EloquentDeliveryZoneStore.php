<?php

namespace App\Services\Plugins;

use App\Models\DeliveryZone;
use App\Services\Pricing\PricingService;
use Mavyn\Contracts\Plugins\DeliveryZoneData;
use Mavyn\Contracts\Plugins\DeliveryZoneStore;

final class EloquentDeliveryZoneStore implements DeliveryZoneStore
{
    private function data(DeliveryZone $zone): ?DeliveryZoneData
    {
        $pricing = app(PricingService::class);
        $row = $pricing->price('zone', $zone->id);

        return $row ? new DeliveryZoneData($zone->id, $zone->name, $row['amount_minor'], $pricing->context()->currency) : null;
    }

    public function active(): array
    {
        return DeliveryZone::active()->orderBy('display_order')->get()->map(fn ($zone) => $this->data($zone))->filter()->values()->all();
    }

    public function findAvailable(int $id): ?DeliveryZoneData
    {
        $zone = DeliveryZone::active()->lockForUpdate()->find($id);

        return $zone ? $this->data($zone) : null;
    }
}
