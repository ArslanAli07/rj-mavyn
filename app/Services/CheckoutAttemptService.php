<?php

namespace App\Services;

use App\Models\CheckoutAttempt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutAttemptService
{
    public function __construct(private CartService $cart) {}

    /** Reuse a draft for the same reviewed cart, including across page reloads. */
    public function issue(): string
    {
        $this->cart->forgetCachedState();
        $cartHash = $this->cartHash($this->cart->items());
        $key = session('checkout_key');
        $attempt = $this->owned($key);
        if ($attempt && ! $attempt->committed_at && hash_equals($attempt->cart_hash, $cartHash)) {
            return $key;
        }
        $key = (string) Str::uuid();
        CheckoutAttempt::create(['key_hash' => $this->keyHash($key), 'owner_hash' => $this->ownerHash(), 'cart_hash' => $cartHash]);
        session(['checkout_key' => $key]);

        return $key;
    }

    /** Caller holds the order transaction; this row is locked before cart/stock rows. */
    public function lock(string $key): CheckoutAttempt
    {
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Reload checkout before placing your order.']);
        }
        $attempt = CheckoutAttempt::whereKey($this->keyHash($key))->lockForUpdate()->first();
        if (! $attempt) {
            throw ValidationException::withMessages(['idempotency_key' => 'Reload checkout before placing your order.']);
        }
        abort_unless(hash_equals($attempt->owner_hash, $this->ownerHash()), 403, 'This checkout belongs to another customer.');

        return $attempt;
    }

    public function owned(mixed $key): ?CheckoutAttempt
    {
        if (! is_string($key) || ! Str::isUuid($key)) {
            return null;
        }

        return CheckoutAttempt::query()->useWritePdo()->whereKey($this->keyHash($key))->where('owner_hash', $this->ownerHash())->first();
    }

    public function completedForOwner(mixed $key): bool
    {
        return $this->owned($key)?->committed_at !== null;
    }

    /** Coupon edits update a draft checkout, but never turn a completed retry into a new purchase. */
    public function refreshDraft(mixed $key): ?string
    {
        if (! $this->owned($key)) {
            return null;
        }

        return DB::transaction(function () use ($key) {
            return $this->lock($key)->committed_at ? null : $this->issue();
        });
    }

    public function cartHash(Collection $items): string
    {
        $rows = $items->sortBy('id')->map(fn ($item) => [
            'id' => (int) $item->id, 'product_id' => (int) $item->product_id,
            'variant_id' => (int) $item->product_variant_id, 'quantity' => (int) $item->quantity,
            'currency' => $item->currency, 'pricing_revision' => (int) $item->pricing_revision,
            'price' => (string) $item->price, 'configuration' => $item->configuration_signature,
            'options' => $item->options_snapshot ?? [], 'special_request' => $item->special_request,
            'customization' => $item->customization ?? null,
        ])->values()->all();

        return hash('sha256', json_encode(['items' => $rows, 'coupon' => session('coupon_code')], JSON_THROW_ON_ERROR));
    }

    public function assertCart(CheckoutAttempt $attempt, Collection $items): void
    {
        if (! hash_equals($attempt->cart_hash, $this->cartHash($items))) {
            throw ValidationException::withMessages(['cart' => 'Your cart changed. Review checkout again before placing the order.']);
        }
    }

    public function receiptHash(?UploadedFile $receipt): ?string
    {
        return $receipt ? hash_file('sha256', $receipt->getRealPath()) : null;
    }

    public function requestHash(array $data, ?string $receiptHash): string
    {
        $fields = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'country', 'city', 'address_line', 'map_link', 'payment_method', 'order_notes'] as $field) {
            $fields[$field] = trim((string) ($data[$field] ?? ''));
        }
        $fields['delivery_zone_id'] = (int) ($data['delivery_zone_id'] ?? 0);
        // Preserve hashes for already committed legacy requests.
        foreach (['shipping_option', 'postal_code'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $fields[$field] = trim((string) $data[$field]);
            }
        }
        $fields['receipt'] = $receiptHash;

        return hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
    }

    private function keyHash(string $key): string
    {
        return hash('sha256', strtolower($key));
    }

    private function ownerHash(): string
    {
        // Cart identity survives session regeneration; an account can retry from another login.
        return hash('sha256', $this->cart->checkoutOwner());
    }
}
