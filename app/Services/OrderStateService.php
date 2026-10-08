<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\InventoryReservation;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStateService
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'],
        'confirmed' => ['preparing', 'out_for_delivery', 'delivered', 'cancelled'],
        'preparing' => ['out_for_delivery', 'delivered', 'cancelled'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(private InventoryService $inventory) {}

    public function updateMany(array $ids, array $data, ?Admin $actor, bool $notifyCustomer = false): Collection
    {
        return DB::transaction(function () use ($ids, $data, $actor, $notifyCustomer) {
            $productIds = InventoryReservation::whereIn('order_id', $ids)->whereNotNull('product_id')->pluck('product_id')->unique()->all();
            $this->inventory->lockProducts($productIds);
            $orders = Order::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            foreach ($orders as $order) {
                if ($actor && ((array_key_exists('status', $data) && $data['status'] !== $order->status) || array_key_exists('admin_notes', $data))) {
                    abort_unless($actor->hasPermission('orders.manage'), 403);
                }
                if ($actor && array_key_exists('payment_status', $data) && $data['payment_status'] !== $order->payment_status) {
                    abort_unless($actor->hasPermission('orders.payment'), 403);
                }
                $status = $data['status'] ?? $order->status;
                $payment = $data['payment_status'] ?? $order->payment_status;
                if (! array_key_exists($status, Order::STATUSES) || ! in_array($payment, ['pending', 'paid', 'failed', 'refunded'], true)) {
                    throw ValidationException::withMessages(['status' => 'Unsupported order or payment status.']);
                }
                if ($order->inventory_managed) {
                    if ($payment === 'failed' && $status !== 'cancelled') {
                        if ($order->status === 'pending' && $status === 'pending') {
                            $status = 'cancelled';
                        } else {
                            throw ValidationException::withMessages(['payment_status' => 'Cancel the order before recording payment failure. Dispatched stock requires a return workflow.']);
                        }
                    }
                    if ($status !== $order->status && ! in_array($status, self::TRANSITIONS[$order->status] ?? [], true)) {
                        throw ValidationException::withMessages(['status' => 'This order cannot move to that status. Dispatched or delivered stock requires a return workflow; cancelled orders cannot be reopened.']);
                    }
                }
                $oldStatus = $order->status;
                $this->inventory->settle($order, $status, $payment, $actor);
                $payload = $data + ['status' => $status, 'payment_status' => $payment];
                $payload['status'] = $status;
                if ($status === 'delivered' && ! $order->delivered_at) {
                    $payload['delivered_at'] = now();
                }
                if ($status !== $oldStatus) {
                    $payload['status_revision'] = $order->status_revision + 1;
                }
                // Fulfillment never implies staff confirmation that money was received.
                $order->update($payload);
                if ($notifyCustomer && $status !== $oldStatus) {
                    app(NotificationOutboxService::class)->recordStatusUpdate($order, $oldStatus);
                }
            }
            DB::afterCommit(function () {
                Cache::forget('admin_dashboard_stats');
                Cache::forget('admin_orders_poll_snapshot');
            });

            return $orders;
        }, 3);
    }
}
