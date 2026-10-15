<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function updateMany(array $ids, array $data, ?Admin $actor, bool $notifyCustomer = false, bool $verifiedGateway = false): Collection
    {
        return DB::transaction(function () use ($ids, $data, $actor, $notifyCustomer, $verifiedGateway) {
            app(PricingService::class)->lock();
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
                $online = Schema::hasTable('gateway_payment_attempts') && DB::table('gateway_payment_attempts')->where('order_id', $order->id)->lockForUpdate()->get(['id'])->isNotEmpty();
                if ($online && ! $verifiedGateway && isset($data['payment_status']) && $data['payment_status'] !== $order->payment_status) {
                    throw ValidationException::withMessages(['payment_status' => 'Online payment status changes require a verified gateway result or refund operation.']);
                }
                $status = $data['status'] ?? $order->status;
                $payment = $data['payment_status'] ?? $order->payment_status;
                if ($online && $status !== $order->status && ! in_array($status, ['pending', 'cancelled'], true) && ($payment !== 'paid' || DB::table('gateway_payment_attempts')->where('order_id', $order->id)->where('needs_review', true)->lockForUpdate()->get(['id'])->isNotEmpty())) {
                    throw ValidationException::withMessages(['status' => 'Verified payment and resolution of payment review are required before fulfillment.']);
                }
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
