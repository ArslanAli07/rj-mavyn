<?php

namespace App\Services\Plugins;

use App\Models\DeliveryZone;
use App\Support\Money;
use Mavyn\Contracts\Plugins\DeliveryZoneData;
use Mavyn\Contracts\Plugins\DeliveryZoneStore;

final class EloquentDeliveryZoneStore implements DeliveryZoneStore
{
    private function data(DeliveryZone $zone): DeliveryZoneData
    {
        return new DeliveryZoneData($zone->id, $zone->name, Money::minor($zone->delivery_fee), 'PKR');
    }

    public function active(): array
    {
        return DeliveryZone::active()->orderBy('display_order')->get()->map(fn ($zone) => $this->data($zone))->all();
    }

    public function findAvailable(int $id): ?DeliveryZoneData
    {
        $zone = DeliveryZone::active()->lockForUpdate()->find($id);

        return $zone ? $this->data($zone) : null;
    }
}
