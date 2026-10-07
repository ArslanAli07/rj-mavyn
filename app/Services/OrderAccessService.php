<?php

namespace App\Services;

use App\Models\Order;

class OrderAccessService
{
    public function authorizeCustomer(Order $order): void
    {
        $customerId = auth('web')->id();
        $ownsOrder = $customerId !== null && $order->user_id === $customerId;
        $placedOrder = session('placed_order_id');
        $ownsSession = $placedOrder !== null && (string) $placedOrder === (string) $order->id;

        abort_unless($ownsOrder || $ownsSession, 403);
    }
}
