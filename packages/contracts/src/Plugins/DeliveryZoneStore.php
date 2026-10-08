<?php

namespace Mavyn\Contracts\Plugins;

interface DeliveryZoneStore
{
    /** @return list<DeliveryZoneData> */
    public function active(): array;

    /** A current, locked read when called in an order transaction. */
    public function findAvailable(int $id): ?DeliveryZoneData;
}
