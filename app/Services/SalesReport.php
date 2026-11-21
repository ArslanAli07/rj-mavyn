<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard money figures for one report currency.
 *
 * - Booked: every order that is not cancelled, whether or not money has arrived (COD awaiting cash,
 *   or a gateway checkout the customer has not finished).
 * - Collected: orders staff or the gateway marked paid, minus succeeded gateway refunds. Fully refunded
 *   orders count as zero.
 * - Awaiting payment: booked orders whose payment is still pending.
 *
 * "Today" and daily buckets use the store timezone, not UTC.
 */
final class SalesReport
{
    public function __construct(private string $currency) {}

    public function summary(): array
    {
        $today = $this->storeDayRange(CarbonImmutable::now(store_timezone()));
        $booked = Order::sales()->where('currency', $this->currency);
        $paid = Order::where('currency', $this->currency)->where('payment_status', 'paid');
        $awaiting = (clone $booked)->where('payment_status', 'pending');

        $collectedMinor = $this->sumMinor($paid) - $this->refundedMinor((clone $paid)->pluck('id'));
        $todayCollectedMinor = $this->sumMinor((clone $paid)->whereBetween('created_at', $today))
            - $this->refundedMinor((clone $paid)->whereBetween('created_at', $today)->pluck('id'));
        $bookedCount = (clone $booked)->count();

        return [
            'booked_sales' => Money::decimal($this->sumMinor($booked)),
            'booked_orders' => $bookedCount,
            'today_booked' => Money::decimal($this->sumMinor((clone $booked)->whereBetween('created_at', $today))),
            'collected' => Money::decimal(max(0, $collectedMinor)),
            'today_collected' => Money::decimal(max(0, $todayCollectedMinor)),
            'awaiting_payment' => Money::decimal($this->sumMinor($awaiting)),
            'awaiting_orders' => (clone $awaiting)->count(),
            'refunded' => Money::decimal($this->totalRefundedMinor()),
            'total_discounts' => Money::decimal($this->sumMinor($booked, 'discount_amount')),
            'delivery_fees' => Money::decimal($this->sumMinor($booked, 'delivery_fee')),
            'average_order_value' => Money::decimal($bookedCount ? intdiv($this->sumMinor($booked), $bookedCount) : 0),
        ] + [
            // Backward-compatible keys (booked, i.e. non-cancelled, sales) for existing consumers.
            'total_sales' => Money::decimal($this->sumMinor($booked)),
            'today_sales' => Money::decimal($this->sumMinor((clone $booked)->whereBetween('created_at', $today))),
        ];
    }

    /** @return list<array{currency: string, date: string, revenue: string, collected: string, orders: int}> */
    public function daily(int $days = 7): array
    {
        $now = CarbonImmutable::now(store_timezone());
        $start = $now->subDays($days - 1)->startOfDay();
        $orders = Order::sales()->where('currency', $this->currency)
            ->where('created_at', '>=', $start->utc())
            ->get(['id', 'created_at', 'grand_total', 'payment_status']);
        $refunds = $this->refundsByOrder($orders->where('payment_status', 'paid')->pluck('id'));

        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $buckets[$now->subDays($i)->toDateString()] = ['label' => $now->subDays($i)->format('M d'), 'revenue' => 0, 'collected' => 0, 'orders' => 0];
        }
        foreach ($orders as $order) {
            $day = store_time($order->created_at)->toDateString();
            if (! isset($buckets[$day])) {
                continue;
            }
            $total = Money::minor($order->grand_total);
            $buckets[$day]['revenue'] += $total;
            $buckets[$day]['orders']++;
            if ($order->payment_status === 'paid') {
                $buckets[$day]['collected'] += max(0, $total - ($refunds[$order->id] ?? 0));
            }
        }

        return array_values(array_map(fn ($bucket) => [
            'currency' => $this->currency,
            'date' => $bucket['label'],
            // Plain numeric strings ("155", "12.5"), the format the chart endpoint has always returned.
            'revenue' => (string) ($bucket['revenue'] / 100),
            'collected' => (string) ($bucket['collected'] / 100),
            'orders' => $bucket['orders'],
        ], $buckets));
    }

    /** UTC bounds of the store-local calendar day containing $moment. */
    private function storeDayRange(CarbonImmutable $moment): array
    {
        return [$moment->startOfDay()->utc(), $moment->endOfDay()->utc()];
    }

    /** Sum money columns exactly in minor units (avoids float drift from SQL SUM on decimals). */
    private function sumMinor($query, string $column = 'grand_total'): int
    {
        return (int) (clone $query)->pluck($column)->sum(fn ($value) => Money::minor($value));
    }

    private function refundedMinor(Collection $orderIds): int
    {
        return array_sum($this->refundsByOrder($orderIds));
    }

    /** @return array<int, int> order id => succeeded gateway refunds in minor units */
    private function refundsByOrder(Collection $orderIds): array
    {
        if ($orderIds->isEmpty() || ! Schema::hasTable('gateway_refunds')) {
            return [];
        }

        return DB::table('gateway_refunds')
            ->join('gateway_payment_attempts', 'gateway_payment_attempts.id', '=', 'gateway_refunds.attempt_id')
            ->whereIn('gateway_payment_attempts.order_id', $orderIds)
            ->where('gateway_refunds.status', 'succeeded')
            ->where('gateway_refunds.currency', $this->currency)
            ->groupBy('gateway_payment_attempts.order_id')
            ->selectRaw('gateway_payment_attempts.order_id as order_id, SUM(gateway_refunds.amount_minor) as refunded')
            ->pluck('refunded', 'order_id')->map(fn ($value) => (int) $value)->all();
    }

    /** Money returned to customers: gateway refunds plus orders staff marked refunded without a gateway refund. */
    private function totalRefundedMinor(): int
    {
        $orders = Order::where('currency', $this->currency)->whereIn('payment_status', ['paid', 'refunded']);
        $gateway = $this->refundsByOrder((clone $orders)->pluck('id'));
        $manual = (clone $orders)->where('payment_status', 'refunded')->whereNotIn('id', array_keys($gateway));

        return array_sum($gateway) + $this->sumMinor($manual);
    }
}
