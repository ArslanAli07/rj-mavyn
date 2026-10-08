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
use Illuminate\Support\Facades\Log;
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
        private InventoryService $inventory,
        private CheckoutAttemptService $attempts,
    ) {}

    public function place(array $data, ?UploadedFile $receipt = null): OrderPlacementResult
    {
        $receiptPath = null;

        try {
            $placement = DB::transaction(function () use ($data, $receipt, &$receiptPath) {
                $attempt = $this->attempts->lock($data['idempotency_key'] ?? '');
                $receiptHash = $this->attempts->receiptHash($receipt);
                $requestHash = $this->attempts->requestHash($data, $receiptHash ?? ($attempt->committed_at ? $attempt->receipt_hash : null));
                if ($attempt->committed_at) {
                    abort_unless(hash_equals($attempt->request_hash, $requestHash), 409,
                        'This checkout has already placed an order with different details. Review your orders before starting another checkout.');
                    $existing = $attempt->order()->useWritePdo()->first();
                    abort_unless($existing, 410, 'The order for this checkout is no longer available. This checkout cannot be reused.');

                    return new OrderPlacementResult($existing, true);
                }
                if (! in_array($data['payment_method'], $this->payments->enabled(), true)) {
                    throw ValidationException::withMessages(['payment_method' => 'This payment method is unavailable.']);
                }
                if ($data['payment_method'] !== 'cod' && ! $receipt) {
                    throw ValidationException::withMessages(['payment_receipt' => 'Please attach your payment receipt.']);
                }

                $items = $this->cart->lockedItems();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }
                $this->attempts->assertCart($attempt, $items);

                $changedPrices = false;
                // Stable lock order avoids opposing product lock order between carts.
                $products = $this->inventory->lockProducts($items->pluck('product_id')->unique()->all());

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
                    $item->setRelation('variant', $configuration['variant']);
                    $item->product_variant_id = $configuration['variant']->id;
                }

                if ($changedPrices) {
                    // Commit only refreshed cart prices; the customer must resubmit to accept them.
                    foreach ($items as $item) {
                        $item->save();
                    }
                    $attempt->update(['cart_hash' => $this->attempts->cartHash($items)]);

                    return null;
                }

                $this->inventory->assertAvailable($items);
                $subtotal = round($items->sum(fn ($item) => (float) $item->price * $item->quantity), 2);
                $coupon = $this->validatedCoupon($subtotal);
                $discount = $coupon ? round($coupon->calculateDiscount($subtotal), 2) : 0;
                $zone = DeliveryZone::active()->lockForUpdate()->find($data['delivery_zone_id']);
                if (! $zone) {
                    throw ValidationException::withMessages(['delivery_zone_id' => 'Choose an available delivery zone.']);
                }
                $address = $data['address_line'];
                if (! empty($data['map_link'])) {
                    $address .= "\nMap Link: ".$data['map_link'];
                }

                if ($receipt) {
                    // A retry also replaces an interrupted pre-commit write at the same path.
                    // The protected receipt endpoint detects MIME from the bytes, not an extension.
                    $receiptPath = 'receipts/checkout-'.$attempt->key_hash;
                    if (! $receipt->storeAs('receipts', basename($receiptPath), 'local')) {
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
                    'inventory_managed' => true,
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
                    $this->inventory->reserve($orderItem, $item->product, $item->variant);
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
                $attempt->update(['request_hash' => $requestHash, 'receipt_hash' => $receiptHash,
                    'order_id' => $order->id, 'committed_at' => now()]);

                return new OrderPlacementResult($order, false);
            });
        } catch (Throwable $exception) {
            if ($receiptPath) {
                // A lost connection or after-commit exception may mask a successful commit.
                // Delete only when the durable record confirms this checkout did not commit.
                try {
                    if (! $this->attempts->completedForOwner($data['idempotency_key'] ?? null)) {
                        Storage::disk('local')->delete($receiptPath);
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('Checkout receipt retained because commit state could not be verified.', ['path' => $receiptPath]);
                }
            }
            throw $exception;
        } finally {
            $this->cart->forgetCachedState();
        }

        if (! $placement) {
            throw ValidationException::withMessages(['cart' => 'Prices have changed. Review your updated cart and submit again to accept the new prices.']);
        }

        if (! $placement->replayed) {
            $this->cart->removeCoupon();
            Cache::forget('admin_dashboard_stats');
            Cache::forget('admin_orders_poll_snapshot');
        }

        return $placement;
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
