<?php

namespace App\Models\Concerns;

use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mavyn\Core\Currencies;
use Mavyn\Core\Money;

/** Legacy writes remain in their stamped currency; normalized records are authoritative. */
trait HasCurrencyPrices
{
    public function delete()
    {
        $pricing = app(PricingService::class);
        if (! $pricing->installed()) {
            return parent::delete();
        }

        return DB::transaction(function () use ($pricing) {
            $pricing->lock(true);
            $deleted = parent::delete();
            if ($deleted) {
                DB::table('currency_prices')->where('kind', $this->priceKind())->where('entity_id', $this->id)->delete();
                $pricing->bump();
            }

            return $deleted;
        }, 3);
    }

    public function save(array $options = [])
    {
        $pricing = app(PricingService::class);
        $fields = $this->currencyFields();
        $moneyChanged = ! $this->exists || $this->isDirty([...$fields, 'price_currency']) || ($this->priceKind() === 'variant' && $this->isDirty('is_default'));
        $termsChanged = $moneyChanged || $this->isDirty(['is_active', 'is_default', 'sort_order', 'type', 'usage_limit', 'per_user_limit', 'starts_at', 'expires_at']);
        if (! $pricing->installed() || ! $termsChanged) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($pricing, $options, $moneyChanged) {
            $context = $pricing->lock(true);
            if ($this->exists && $this->getRawOriginal('price_currency') === null) {
                throw ValidationException::withMessages(['price_currency' => 'Resolve legacy price provenance before editing this record.']);
            }
            $kind = $this->priceKind();
            $currency = $this->attributes['price_currency'] ?? ($this->exists ? $this->getRawOriginal('price_currency') : (DB::table('price_provenance')->where('source', $kind)->value('currency') ?? $context->currency));
            Currencies::symbol($currency);
            if ($this->exists && $currency !== $this->getRawOriginal('price_currency')) {
                throw ValidationException::withMessages(['price_currency' => 'This record’s original price currency cannot change. Enter another currency’s prices from currency management.']);
            }
            $this->attributes['price_currency'] = $currency;
            $values = null;
            if ($moneyChanged) {
                $minor = function ($key, $nullable = false, $signed = false) {
                    $value = $this->attributes[$key] ?? null;
                    if ($nullable && ($value === null || $value === '')) {
                        return null;
                    }
                    $result = Money::minor($value);
                    Currencies::amount($result, $signed);

                    return $result;
                };
                $values = match ($kind) {
                    'product', 'variant' => ['amount_minor' => $minor('price'), 'sale_minor' => $minor('discount_price', true)],
                    'option' => ['amount_minor' => $minor('price_adjustment', false, true)],
                    'zone' => ['amount_minor' => $minor('delivery_fee')],
                    'coupon' => ['amount_minor' => ($this->attributes['type'] ?? '') === 'fixed' ? $minor('amount') : null,
                        'minimum_minor' => $minor('minimum_order'), 'maximum_minor' => $minor('maximum_discount', true)],
                };
                if (isset($values['sale_minor']) && $values['sale_minor'] >= $values['amount_minor']) {
                    throw ValidationException::withMessages(['discount_price' => 'Sale must be less than the regular price.']);
                }
            }
            $saved = parent::save($options);
            if ($saved && $values !== null) {
                DB::table('currency_prices')->updateOrInsert(['kind' => $kind, 'entity_id' => $this->id, 'currency' => $currency], $values);
                if ($kind === 'product') {
                    $variant = DB::table('product_variants')->where('product_id', $this->id)->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->first();
                    if ($variant) {
                        DB::table('currency_prices')->updateOrInsert(['kind' => 'variant', 'entity_id' => $variant->id, 'currency' => $currency], $values);
                        if ($variant->price_currency === $currency) {
                            DB::table('product_variants')->where('id', $variant->id)->update(['price' => Money::decimal($values['amount_minor']), 'discount_price' => $values['sale_minor'] === null ? null : Money::decimal($values['sale_minor'])]);
                        }
                    }
                } elseif ($kind === 'variant' && $this->is_default) {
                    DB::table('currency_prices')->updateOrInsert(['kind' => 'product', 'entity_id' => $this->product_id, 'currency' => $currency], $values);
                    DB::table('products')->where('id', $this->product_id)->where('price_currency', $currency)->update(['price' => Money::decimal($values['amount_minor']), 'discount_price' => $values['sale_minor'] === null ? null : Money::decimal($values['sale_minor'])]);
                }
            }
            if ($saved) {
                $pricing->bump();
            }

            return $saved;
        }, 3);
    }

    public function priceKind(): string
    {
        return array_search($this->getTable(), PricingService::TABLES, true);
    }

    private function currencyFields(): array
    {
        return match ($this->priceKind()) {
            'product', 'variant' => ['price', 'discount_price'], 'option' => ['price_adjustment'],
            'zone' => ['delivery_fee'], 'coupon' => ['amount', 'minimum_order', 'maximum_discount'],
        };
    }

    /** Admin legacy forms edit the visibly stamped original currency. Plugins use PriceBook. */
    public function currencyDecimal(string $column, mixed $legacy): ?string
    {
        $pricing = app(PricingService::class);
        if (! $this->exists || ! $pricing->installed() || request()->is('admin/*')) {
            return $legacy === null ? null : (string) $legacy;
        }
        $key = match ($column) {
            'price', 'price_adjustment', 'delivery_fee' => 'amount_minor', 'discount_price' => 'sale_minor',
            'minimum_order' => 'minimum_minor', 'maximum_discount' => 'maximum_minor', 'amount' => 'amount_minor',
        };
        if ($this->priceKind() === 'coupon' && $column === 'amount' && $this->type === 'percentage') {
            return (string) $legacy;
        }
        $value = $this->priceKind() === 'product'
            ? DB::table('currency_prices')->where('kind', 'product')->where('entity_id', $this->id)->where('currency', $pricing->context()->currency)->value($key)
            : ($pricing->price($this->priceKind(), $this->id)[$key] ?? null);

        return $value === null ? null : Money::decimal($value);
    }
}
