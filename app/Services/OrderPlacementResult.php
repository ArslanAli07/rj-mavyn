<?php

namespace App\Services;

use App\Models\Order;

final readonly class OrderPlacementResult
{
    public function __construct(public Order $order, public bool $replayed) {}
}
