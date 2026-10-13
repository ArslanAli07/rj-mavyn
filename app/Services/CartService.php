<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class CartService
{
    protected string $sessionKey = 'cart_session_id';

    protected ?Collection $cachedItems = null;

    protected ?int $cachedCount = null;

    protected ?string $cachedSubtotal = null;

    protected ?array $cachedTotals = null;

    protected ?Coupon $cachedCoupon = null;

    protected bool $couponResolved = false;

    public function __construct(private ProductConfigurationService $configurations, private CartOwnerLockService $ownerLocks) {}

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
        if (! Session::has($this->sessionKey)) {
            Session::put($this->sessionKey, 'guest_'.Session::getId());
        }

        return Session::get($this->sessionKey);
    }

    protected function query()
    {
        if (Auth::guard('web')->check()) {
            return CartItem::where('user_id', Auth::guard('web')->id());
        }

        return CartItem::whereNull('user_id')->where('session_id', $this->getSessionId());
    }

    public function checkoutOwner(): string
    {
        return Auth::guard('web')->check()
            ? 'user:'.Auth::guard('web')->id()
            : 'guest:'.$this->getSessionId();
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
        $this->ownerLocks->synchronized([$this->checkoutOwner()], function () use ($product, $quantity, $variant, $optionValueIds, $specialRequest) {
            $this->addItem($product, $quantity, $variant, $optionValueIds, $specialRequest);
        });
    }

    private function addItem(Product $product, int $quantity, ?ProductVariant $variant, array $optionValueIds, ?string $specialRequest): void
    {
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

        if (Auth::guard('web')->check()) {
            $lookup['user_id'] = Auth::guard('web')->id();
        } else {
            $lookup['user_id'] = null;
            $lookup['session_id'] = $this->getSessionId();
        }

        $item = CartItem::where($lookup)->lockForUpdate()->first() ?? new CartItem($lookup);
        $item->configuration_signature = $config['configuration_signature'];
        $item->product_variant_id = $config['variant']->id;
        $item->weight = $config['weight'];
        $item->options_snapshot = $config['options_snapshot'];
        $item->special_request = $config['special_request'];
        $item->price = $config['unit_price'];
        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $this->assertCartStock($product, $config['variant'], $item->quantity, $item->exists ? [$item->id] : []);
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
        $this->ownerLocks->synchronized([$this->checkoutOwner()], function () use ($itemId, $quantity, $variantId, $optionValueIds, $specialRequest, $updateSpecialRequest) {
            $this->updateItem($itemId, $quantity, $variantId, $optionValueIds, $specialRequest, $updateSpecialRequest);
        });
    }

    private function updateItem(int $itemId, ?int $quantity, ?int $variantId, ?array $optionValueIds, ?string $specialRequest, bool $updateSpecialRequest): void
    {
        $item = $this->query()->with(['product.variants', 'product.optionGroups.values'])->lockForUpdate()->find($itemId);
        if (! $item || ! $item->product) {
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
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertCartStock($item->product, $config['variant'], $existing->quantity + $item->quantity, [$existing->id, $item->id]);
                $existing->quantity += $item->quantity;
                $existing->price = $config['unit_price'];
                $existing->weight = $config['weight'];
                $existing->options_snapshot = $config['options_snapshot'];
                $existing->special_request = $config['special_request'];
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

        $variant = $item->product->variants->firstWhere('id', $item->product_variant_id);
        if (! $variant) {
            throw ValidationException::withMessages(['product_variant_id' => 'Choose an available variant.']);
        }
        $this->assertCartStock($item->product, $variant, $item->quantity, [$item->id]);
        $item->save();
        $this->resetCache();
    }

    private function assertCartStock(Product $product, ProductVariant $variant, int $quantity, array $excluded): void
    {
        if ($quantity < 1 || $quantity > 20) {
            throw ValidationException::withMessages(['quantity' => 'Each configured item must contain between 1 and 20 units.']);
        }
        $available = app(InventoryService::class)->availableQuantity($product, $variant);
        $current = $product->fresh();
        $others = $this->query()->where('product_id', $product->id)->whereNotIn('id', $excluded);
        if ($current->inventory_mode !== 'shared') {
            $others->where('product_variant_id', $variant->id);
        }
        if ($available !== null && $quantity + $others->sum('quantity') > $available) {
            throw ValidationException::withMessages(['quantity' => 'Not enough stock is available for the quantities in your cart.']);
        }
    }

    public function remove(int $itemId): void
    {
        $this->ownerLocks->synchronized([$this->checkoutOwner()], function () use ($itemId) {
            $this->query()->lockForUpdate()->find($itemId)?->delete();
        });
        $this->resetCache();
    }

    public function clear(): void
    {
        $this->ownerLocks->synchronized([$this->checkoutOwner()], fn () => $this->query()->delete());
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
        $this->ownerLocks->lock([$this->checkoutOwner()]);
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

    public function subtotal(): string
    {
        if ($this->cachedSubtotal === null) {
            if ($this->cachedItems !== null) {
                $this->cachedSubtotal = Money::decimal($this->cachedItems->sum(fn ($item) => Money::minor($item->price) * $item->quantity));
            } else {
                $this->cachedSubtotal = Money::decimal($this->query()->get()->sum(fn ($item) => Money::minor($item->price) * $item->quantity));
            }
        }

        return $this->cachedSubtotal;
    }

    public function applyCoupon(string $code, ?string $email = null): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon || ! $coupon->is_valid) {
            return ['success' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        $subtotal = $this->subtotal();
        if (Money::minor($subtotal) < Money::minor($coupon->minimum_order)) {
            return [
                'success' => false,
                'message' => 'Minimum order of '.format_price($coupon->minimum_order).' required.',
            ];
        }

        if (app(CouponIdentityService::class)->limitReached($coupon, $email)) {
            return ['success' => false, 'message' => 'The coupon usage limit has been reached for this customer.'];
        }

        Session::put('coupon_code', $coupon->code);
        $discount = $coupon->calculateDiscount($subtotal);
        $this->resetCache();

        return [
            'success' => true,
            'discount' => $discount,
            'coupon' => $coupon,
            'message' => 'Coupon applied! You saved '.format_price($discount).(! Auth::guard('web')->check() && ! $email ? ' Eligibility will be checked against your checkout email and IP address.' : ''),
        ];
    }

    public function getAppliedCoupon(): ?Coupon
    {
        if (! $this->couponResolved) {
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
            $discount = $coupon && $coupon->is_valid ? $coupon->calculateDiscount($subtotal) : '0.00';
            $grandTotal = Money::decimal(max(0, Money::minor($subtotal) - Money::minor($discount)));

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
        if (! Auth::guard('web')->check()) {
            return;
        }

        $sessionId = $this->getSessionId();
        $this->ownerLocks->synchronized([$this->checkoutOwner(), 'guest:'.$sessionId], function () use ($sessionId) {
            $guestItems = CartItem::whereNull('user_id')->where('session_id', $sessionId)->orderBy('id')->lockForUpdate()->get();

            foreach ($guestItems as $guestItem) {
                $signature = $guestItem->configuration_signature
                    ?: $this->configurations->signature(
                        $guestItem->product_id,
                        $guestItem->product_variant_id,
                        collect($guestItem->options_snapshot ?? [])->pluck('value_id')->all(),
                        $guestItem->special_request
                    );

                $existing = CartItem::where('user_id', Auth::guard('web')->id())
                    ->where('product_id', $guestItem->product_id)
                    ->where('configuration_signature', $signature)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $quantity = $existing->quantity + $guestItem->quantity;
                    if ($quantity > 20) {
                        Session::flash('error', 'Matching cart items were limited to 20 units. Review your cart before checkout.');
                    }
                    $existing->update(['quantity' => min(20, $quantity)]);
                    $guestItem->delete();
                } else {
                    $guestItem->update([
                        'user_id' => Auth::guard('web')->id(),
                        'session_id' => null,
                        'configuration_signature' => $signature,
                    ]);
                }
            }
        });

        $this->resetCache();
    }
}
