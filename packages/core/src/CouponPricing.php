<?php

namespace Mavyn\Core;

final class CouponPricing
{
    public function discount(string|int|float $subtotal, string $type, string|int|float $amount, string|int|float|null $minimumOrder = null, string|int|float|null $maximumDiscount = null): string
    {
        $minor = Money::minor($subtotal);
        if ($minor < Money::minor($minimumOrder)) {
            return '0.00';
        }
        $discount = $type === 'percentage'
            ? Money::percentage($minor, $amount)
            : Money::minor($amount);
        if ($maximumDiscount !== null) {
            $discount = min($discount, Money::minor($maximumDiscount));
        }

        return Money::decimal(max(0, min($discount, $minor)));
    }
}
