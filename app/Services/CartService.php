<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected string $sessionKey = 'cart_session_id';

    protected ?Collection $cachedItems = null;
    protected ?int $cachedCount = null;
    protected ?float $cachedSubtotal = null;
    protected ?array $cachedTotals = null;
    protected ?Coupon $cachedCoupon = null;
    protected bool $couponResolved = false;

    public function __construct(private ProductConfigurationService $configurations) {}

    protected function resetCache(): void
    {
        $this->cachedItems = null;
        $this->cachedCount = null;
        $this->cachedSubtotal = null;
        $this->cachedTotals = null;
        $this->cachedCoupon = null;
        $this->couponResolved = false;
    }

    protected function getSessionId(): string
    {
        if (!Session::has($this->sessionKey)) {
            Session::put($this->sessionKey, 'guest_' . Session::getId());
        }

        return Session::get($this->sessionKey);
    }

    protected function query()
    {
        if (Auth::check()) {
            return CartItem::where('user_id', Auth::id());
        }

        return CartItem::where('session_id', $this->getSessionId());
    }

    /**
     * @param  array<int, int|string>  $optionValueIds
     */
    public function add(
        Product $product,
        int $quantity = 1,
        ?ProductVariant $variant = null,
        array $optionValueIds = [],
        ?string $specialRequest = null
    ): void {
        $config = $this->configurations->resolve(
            $product,
            $variant?->id,
            $optionValueIds,
            $specialRequest
        );

        $lookup = [
            'product_id' => $product->id,
            'configuration_signature' => $config['configuration_signature'],
        ];

        if (Auth::check()) {
            $lookup['user_id'] = Auth::id();
        } else {
            $lookup['session_id'] = $this->getSessionId();
        }

        $item = CartItem::firstOrNew($lookup);
        $item->configuration_signature = $config['configuration_signature'];
        $item->product_variant_id = $config['variant']->id;
        $item->weight = $config['weight'];
        $item->options_snapshot = $config['options_snapshot'];
        $item->special_request = $config['special_request'];
        $item->price = $config['unit_price'];
        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $item->save();
        $this->resetCache();
    }

    /**
     * @param  array<int, int|string>|null  $optionValueIds
     */
    public function update(
        int $itemId,
        ?int $quantity = null,
        ?int $variantId = null,
        ?array $optionValueIds = null,
        ?string $specialRequest = null,
        bool $updateSpecialRequest = false
    ): void {
        $item = $this->query()->with(['product.variants', 'product.optionGroups.values'])->find($itemId);
        if (!$item || !$item->product) {
            return;
        }

        if ($quantity !== null) {
            if ($quantity <= 0) {
                $item->delete();
                $this->resetCache();

                return;
            }
            $item->quantity = $quantity;
        }

        $shouldReconfigure = $variantId !== null || $optionValueIds !== null || $updateSpecialRequest;

        if ($shouldReconfigure) {
            $existingOptionIds = collect($item->options_snapshot ?? [])->pluck('value_id')->all();
            $config = $this->configurations->resolve(
                $item->product,
                $variantId ?? $item->product_variant_id,
                $optionValueIds ?? $existingOptionIds,
                $updateSpecialRequest ? $specialRequest : $item->special_request
            );

            $existing = $this->query()
                ->where('product_id', $item->product_id)
                ->where('configuration_signature', $config['configuration_signature'])
                ->where('id', '!=', $item->id)
                ->first();

            if ($existing) {
                $existing->quantity += $item->quantity;
                $existing->save();
                $item->delete();
                $this->resetCache();

                return;
            }

            $item->product_variant_id = $config['variant']->id;
            $item->weight = $config['weight'];
            $item->options_snapshot = $config['options_snapshot'];
            $item->special_request = $config['special_request'];
            $item->configuration_signature = $config['configuration_signature'];
            $item->price = $config['unit_price'];
        }

        $item->save();
        $this->resetCache();
    }

    public function remove(int $itemId): void
    {
        $this->query()->find($itemId)?->delete();
        $this->resetCache();
    }

    public function clear(): void
    {
        $this->query()->delete();
        $this->resetCache();
    }

    public function items(): Collection
    {
        if ($this->cachedItems === null) {
            $this->cachedItems = $this->query()
                ->with(['product.category', 'product.variants', 'product.optionGroups.values', 'variant'])
                ->get();
        }

        return $this->cachedItems;
    }

    /** Fresh rows locked by the caller's checkout transaction. */
    public function lockedItems(): Collection
    {
        $this->resetCache();

        return $this->query()->orderBy('id')->lockForUpdate()->get();
    }

    public function forgetCachedState(): void
    {
        $this->resetCache();
    }

    public function count(): int
    {
        if ($this->cachedCount === null) {
            if ($this->cachedItems !== null) {
                $this->cachedCount = $this->cachedItems->sum('quantity');
            } else {
                $this->cachedCount = (int) $this->query()->sum('quantity');
            }
        }

        return $this->cachedCount;
    }

    public function subtotal(): float
    {
        if ($this->cachedSubtotal === null) {
            if ($this->cachedItems !== null) {
                $this->cachedSubtotal = $this->cachedItems->sum(fn ($item) => $item->price * $item->quantity);
            } else {
                $this->cachedSubtotal = $this->query()->get()->sum(fn ($item) => $item->price * $item->quantity);
            }
        }

        return (float) $this->cachedSubtotal;
    }

    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (!$coupon || !$coupon->is_valid) {
            return ['success' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        $subtotal = $this->subtotal();
        if ($subtotal < $coupon->minimum_order) {
            return [
                'success' => false,
                'message' => 'Minimum order of ' . format_price($coupon->minimum_order) . ' required.',
            ];
        }

        if (Auth::check()) {
            $used = $coupon->usages()->where('user_id', Auth::id())->count();
            if ($used >= $coupon->per_user_limit) {
                return ['success' => false, 'message' => 'You have already used this coupon.'];
            }
        }

        Session::put('coupon_code', $coupon->code);
        $discount = $coupon->calculateDiscount($subtotal);
        $this->resetCache();

        return [
            'success' => true,
            'discount' => $discount,
            'coupon' => $coupon,
            'message' => 'Coupon applied! You saved ' . format_price($discount),
        ];
    }

    public function getAppliedCoupon(): ?Coupon
    {
        if (!$this->couponResolved) {
            $code = Session::get('coupon_code');
            $this->cachedCoupon = $code ? Coupon::where('code', $code)->first() : null;
            $this->couponResolved = true;
        }

        return $this->cachedCoupon;
    }

    public function removeCoupon(): void
    {
        Session::forget('coupon_code');
        $this->resetCache();
    }

    public function totals(): array
    {
        if ($this->cachedTotals === null) {
            $subtotal = $this->subtotal();
            $coupon = $this->getAppliedCoupon();
            $discount = $coupon && $coupon->is_valid ? $coupon->calculateDiscount($subtotal) : 0;
            $grandTotal = max(0, $subtotal - $discount);

            $this->cachedTotals = [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => null,
                'tax' => 0,
                'grand_total' => $grandTotal,
                'coupon' => $coupon,
            ];
        }

        return $this->cachedTotals;
    }

    public function mergeGuestCart(): void
    {
        $sessionId = $this->getSessionId();
        $guestItems = CartItem::where('session_id', $sessionId)->get();

        foreach ($guestItems as $guestItem) {
            $signature = $guestItem->configuration_signature
                ?: $this->configurations->signature(
                    $guestItem->product_id,
                    $guestItem->product_variant_id,
                    collect($guestItem->options_snapshot ?? [])->pluck('value_id')->all(),
                    $guestItem->special_request
                );

            $existing = CartItem::where('user_id', Auth::id())
                ->where('product_id', $guestItem->product_id)
                ->where('configuration_signature', $signature)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $guestItem->quantity);
                $guestItem->delete();
            } else {
                $guestItem->update([
                    'user_id' => Auth::id(),
                    'session_id' => null,
                    'configuration_signature' => $signature,
                ]);
            }
        }

        $this->resetCache();
    }
}
