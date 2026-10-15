<?php

namespace App\Models;

use App\Models\Concerns\HasCurrencyPrices;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mavyn\Core\CouponPricing;

class Coupon extends Model
{
    use HasCurrencyPrices;

    protected $fillable = [
        'price_currency',
        'code', 'type', 'amount', 'minimum_order', 'maximum_discount',
        'usage_limit', 'usage_count', 'per_user_limit',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function getIsValidAttribute(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->endOfDay()->isPast()) {
            return false;
        }
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function currencyRule(): ?array
    {
        $row = app(PricingService::class)->price('coupon', $this->id);
        if ($row && (($this->type === 'fixed' && $row['amount_minor'] === null) || ($this->type === 'percentage' && $row['amount_minor'] !== null))) {
            return null;
        }

        return $row;
    }

    public function calculateDiscount(string|int|float $subtotal): string
    {
        if (! $this->exists) {
            return (new CouponPricing)->discount($subtotal, $this->type, $this->amount, $this->minimum_order, $this->maximum_discount);
        }
        $rule = $this->currencyRule();
        if (! $rule) {
            return '0.00';
        }
        $amount = $this->type === 'percentage' ? $this->getRawOriginal('amount') : Money::decimal($rule['amount_minor']);

        return (new CouponPricing)->discount($subtotal, $this->type, $amount, Money::decimal($rule['minimum_minor'] ?? 0),
            $rule['maximum_minor'] === null ? null : Money::decimal($rule['maximum_minor']));
    }

    public function getAmountAttribute($value): ?string
    {
        return $this->currencyDecimal('amount', $value);
    }

    public function getMinimumOrderAttribute($value): ?string
    {
        return $this->currencyDecimal('minimum_order', $value);
    }

    public function getMaximumDiscountAttribute($value): ?string
    {
        return $this->currencyDecimal('maximum_discount', $value);
    }
}
