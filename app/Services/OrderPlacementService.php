<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Media\MediaLibraryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Transitional application service; provider extraction follows in the plugin milestone. */
class OrderPlacementService
{
    public function __construct(
        private CartService $cart,
        private ProductConfigurationService $configurations,
        private PaymentMethodService $payments,
    ) {}

    public function place(array $data, ?UploadedFile $receipt = null): Order
    {
        $receiptPath = null;

        try {
            $order = DB::transaction(function () use ($data, $receipt, &$receiptPath) {
                if (! in_array($data['payment_method'], $this->payments->enabled(), true)) {
                    throw ValidationException::withMessages(['payment_method' => 'This payment method is unavailable.']);
                }

                $items = $this->cart->lockedItems();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }

                $changedPrices = false;
                // Stable lock order avoids opposing product lock order between carts.
                $products = Product::whereIn('id', $items->pluck('product_id')->unique())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    if (! $product || ! $product->is_active || ! $product->in_stock) {
                        throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
                    }
                    if ($item->quantity < 1 || $item->quantity > 20) {
                        throw ValidationException::withMessages(['cart' => 'Each configured item must contain between 1 and 20 units.']);
                    }

                    $configuration = $this->configurations->resolve(
                        $product,
                        $item->product_variant_id,
                        collect($item->options_snapshot ?? [])->pluck('value_id')->all(),
                        $item->special_request,
                    );

                    if (round((float) $item->price, 2) !== $configuration['unit_price']) {
                        $changedPrices = true;
                    }
                    $item->price = $configuration['unit_price'];
                    $item->weight = $configuration['variant_name'];
                    $item->options_snapshot = $configuration['options_snapshot'];
                    $item->setRelation('product', $product);
                }

                if ($changedPrices) {
                    // Commit only refreshed cart prices; the customer must resubmit to accept them.
                    foreach ($items as $item) {
                        $item->save();
                    }

                    return null;
                }

                $subtotal = round($items->sum(fn ($item) => (float) $item->price * $item->quantity), 2);
                $coupon = $this->validatedCoupon($subtotal);
                $discount = $coupon ? round($coupon->calculateDiscount($subtotal), 2) : 0;
                $zone = DeliveryZone::active()->lockForUpdate()->findOrFail($data['delivery_zone_id']);
                $address = $data['address_line'];
                if (! empty($data['map_link'])) {
                    $address .= "\nMap Link: ".$data['map_link'];
                }

                if ($receipt) {
                    $receiptPath = $receipt->store('receipts', 'local');
                    if (! $receiptPath) {
                        throw ValidationException::withMessages(['payment_receipt' => 'The receipt could not be stored. Please try again.']);
                    }
                }

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'user_id' => auth('web')->id(),
                    'coupon_id' => $coupon?->id,
                    'delivery_zone_id' => $zone->id,
                    'customer_name' => $data['first_name'].' '.$data['last_name'],
                    'customer_email' => $data['email'],
                    'customer_phone' => $data['phone'],
                    'delivery_country' => $data['country'],
                    'delivery_city' => $data['city'],
                    'delivery_area' => $zone->name,
                    'delivery_address' => $address,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $zone->delivery_fee,
                    'discount_amount' => $discount,
                    'tax_amount' => 0,
                    'grand_total' => max(0, round($subtotal - $discount + (float) $zone->delivery_fee, 2)),
                    'payment_method' => $data['payment_method'],
                    'payment_receipt' => $receiptPath,
                    'payment_receipt_disk' => 'local',
                    'payment_status' => 'pending',
                    'status' => 'pending',
                    'order_notes' => $data['order_notes'] ?? null,
                ]);

                foreach ($items as $item) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'weight' => $item->weight,
                        'variant_name' => $item->weight,
                        'options_snapshot' => $item->options_snapshot ?? [],
                        'special_request' => $item->special_request,
                        'product_title' => $item->product->title,
                        'product_sku' => $item->product->sku,
                        'product_image' => $item->product->main_image,
                        'unit_price' => $item->price,
                        'quantity' => $item->quantity,
                        'total_price' => $item->line_total,
                    ]);
                    app(MediaLibraryService::class)->retainOrderImage($orderItem, $item->product);
                    $item->product->increment('sales_count', $item->quantity);
                }

                if ($coupon) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id' => auth('web')->id(),
                        'order_id' => $order->id,
                    ]);
                    $coupon->increment('usage_count');
                }

                // Delete precisely the locked rows; leave concurrently added items intact.
                CartItem::whereIn('id', $items->pluck('id'))->delete();

                return $order;
            });
        } catch (Throwable $exception) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }
            throw $exception;
        } finally {
            $this->cart->forgetCachedState();
        }

        if (! $order) {
            throw ValidationException::withMessages(['cart' => 'Prices have changed. Review your updated cart and submit again to accept the new prices.']);
        }

        $this->cart->removeCoupon();
        Cache::forget('admin_dashboard_stats');
        Cache::forget('admin_orders_poll_snapshot');

        return $order;
    }

    private function validatedCoupon(float $subtotal): ?Coupon
    {
        $code = session('coupon_code');
        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->lockForUpdate()->first();
        if (! $coupon || ! $coupon->is_valid || $subtotal < (float) $coupon->minimum_order) {
            throw ValidationException::withMessages(['coupon' => 'Your coupon is no longer valid. Remove it or choose another coupon.']);
        }

        if (auth('web')->check() && $coupon->usages()->where('user_id', auth('web')->id())->count() >= $coupon->per_user_limit) {
            throw ValidationException::withMessages(['coupon' => 'You have already used this coupon.']);
        }

        return $coupon;
    }
}
