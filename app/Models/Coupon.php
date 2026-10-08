<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mavyn\Core\CouponPricing;

class Coupon extends Model
{
    protected $fillable = [
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
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(string|int|float $subtotal): string
    {
        return (new CouponPricing)->discount($subtotal, $this->type, $this->amount, $this->minimum_order, $this->maximum_discount);
    }
}
