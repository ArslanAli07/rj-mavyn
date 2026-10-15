<?php

namespace App\Services\Pricing;

use App\Models\CheckoutAttempt;
use App\Models\Coupon;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\CouponIdentityService;
use App\Services\InventoryService;
use App\Services\PaymentMethodService;
use App\Services\Plugins\PluginRuntime;
use App\Services\ShippingService;
use App\Services\StoreAvailabilityService;
use App\Services\StoreFinancials;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Pricing\CheckoutReview;
use Mavyn\Core\Currencies;
use Mavyn\Core\Money;
use Mavyn\Core\Pricing\QuoteFingerprint;

final class CheckoutQuoteService implements CheckoutReview
{
    public function issue(array $input): array
    {
        return DB::transaction(function () use ($input) {
            $pricing = app(PricingService::class);
            $pricing->lock();
            $pricing->assertReady();
            app(PluginRuntime::class)->refresh();
            $attempts = app(CheckoutAttemptService::class);
            $attempt = $attempts->lock($input['idempotency_key'] ?? '');
            abort_if($attempt->committed_at !== null, 409, 'This checkout already placed an order. Retry the original placement request.');
            app(StoreAvailabilityService::class)->assertAvailable();
            $cart = app(CartService::class);
            $items = $cart->lockedItems();
            $products = app(InventoryService::class)->lockProducts($items->pluck('product_id')->unique()->all());
            $cart->reprice($items, $products, true);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }
            app(InventoryService::class)->assertAvailable($items);
            $terms = $this->calculate($input, $items);
            $token = Str::random(64);
            $expires = now()->addMinutes(15);
            $attempt->update(['cart_hash' => $attempts->cartHash($items), 'review_quote' => $terms['review'],
                'review_token_hash' => hash('sha256', $token), 'review_expires_at' => $expires]);

            return ['review_token' => $token, 'expires_at' => $expires->toIso8601String(), 'quote' => $terms['review']];
        }, 3);
    }

    /** Caller holds currency/runtime, attempt, cart and product locks in that order. */
    public function calculate(array $input, Collection $items): array
    {
        $context = app(PricingService::class)->assertReady();
        $subtotalMinor = 0;
        foreach ($items as $item) {
            if ($item->currency !== $context->currency || (int) $item->pricing_revision !== $context->revision || $item->quantity < 1 || $item->quantity > 20) {
                $this->stale();
            }
            $subtotalMinor += Money::minor($item->price) * $item->quantity;
        }
        Currencies::amount($subtotalMinor);
        $subtotal = Money::decimal($subtotalMinor);
        $code = session('coupon_code');
        $coupon = $code ? Coupon::where('code', $code)->lockForUpdate()->first() : null;
        if ($code && (! $coupon || ! $coupon->is_valid || ! $coupon->currencyRule() || $subtotalMinor < Money::minor($coupon->minimum_order))) {
            throw ValidationException::withMessages(['coupon' => 'Your coupon is unavailable. Remove it or choose another coupon.']);
        }
        if ($coupon && app(CouponIdentityService::class)->limitReached($coupon, $input['email'] ?? null, locked: true)) {
            throw ValidationException::withMessages(['coupon' => 'The coupon usage limit has been reached for this customer.']);
        }
        $discount = $coupon ? $coupon->calculateDiscount($subtotal) : '0.00';
        $financials = app(StoreFinancials::class)->snapshot();
        $taxMinor = app(StoreFinancials::class)->taxMinor($subtotalMinor, Money::minor($discount), $financials['tax_rate']);
        $shipping = app(ShippingService::class)->choose($input, $subtotalMinor);
        $payment = app(PaymentMethodService::class)->choose($input['payment_method'], $context->currency);
        $total = max(0, $subtotalMinor - Money::minor($discount)) + $taxMinor + $shipping['quote']->feeMinor;
        Currencies::amount($total);
        $identity = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'country', 'city', 'address_line', 'map_link', 'postal_code', 'order_notes'] as $key) {
            $identity[$key] = trim((string) ($input[$key] ?? ''));
        }
        $review = [
            'currency' => $context->currency, 'currency_symbol' => $context->symbol, 'pricing_revision' => $context->revision,
            'extension_revision' => (int) DB::table('extension_runtime')->where('id', 1)->value('revision'),
            'cart_hash' => app(CheckoutAttemptService::class)->cartHash($items),
            'identity' => $identity, 'eligibility_identity' => app(CouponIdentityService::class)->snapshot($input['email'] ?? null),
            'subtotal_minor' => $subtotalMinor, 'discount_minor' => Money::minor($discount),
            'coupon' => $coupon ? ['id' => $coupon->id, 'code' => $coupon->code, 'type' => $coupon->type, 'percentage' => $coupon->type === 'percentage' ? $coupon->getRawOriginal('amount') : null,
                'rules' => $coupon->currencyRule(), 'starts_at' => $coupon->getRawOriginal('starts_at'), 'expires_at' => $coupon->getRawOriginal('expires_at'),
                'usage_limit' => $coupon->usage_limit, 'per_user_limit' => $coupon->per_user_limit] : null,
            'shipping' => ['provider' => $shipping['provider_key'], 'option' => $shipping['option']->id, 'label' => $shipping['quote']->label,
                'fee_minor' => $shipping['quote']->feeMinor, 'currency' => $shipping['quote']->currency],
            'payment' => ['method' => $input['payment_method'], 'provider' => $payment['provider_key'], 'instructions' => (array) $payment['instructions']],
            'tax_rate' => $financials['tax_rate'], 'tax_basis' => 'discounted_merchandise_excluding_shipping', 'tax_minor' => $taxMinor, 'total_minor' => $total,
        ];

        return compact('review', 'coupon', 'discount', 'financials', 'taxMinor', 'shipping', 'payment', 'subtotal');
    }

    public function assertReviewed(CheckoutAttempt $attempt, array $input, array $review): void
    {
        $token = $input['review_token'] ?? '';
        if (! is_string($token) || ! $attempt->review_token_hash || ! hash_equals($attempt->review_token_hash, hash('sha256', $token)) ||
            ! $attempt->review_expires_at || $attempt->review_expires_at->isPast() ||
            $this->fingerprint($attempt->review_quote ?? []) !== $this->fingerprint($review)) {
            $this->stale();
        }
    }

    private function fingerprint(array $data): string
    {
        // JSON round-trip normalizes DTO scalar encodings saved in the database.
        return QuoteFingerprint::of($data);
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['review_token' => 'Checkout terms changed or the review expired. Request a fresh quote and review it before confirming.']);
    }
}
