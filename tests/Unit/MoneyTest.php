<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_conversion_rounds_half_away_from_zero(): void
    {
        foreach (['0.10' => 10, '1.005' => 101, '-1.005' => -101, '9999999999.99' => 999999999999] as $value => $minor) {
            $this->assertSame($minor, Money::minor($value));
            $this->assertSame($minor, Money::minor(Money::decimal($minor)));
        }
    }

    public function test_percentage_rounding_threshold_cap_and_fixed_discount(): void
    {
        $coupon = new Coupon(['type' => 'percentage', 'amount' => '12.50', 'minimum_order' => '0.04']);
        $this->assertSame('0.00', $coupon->calculateDiscount('0.03'));
        $this->assertSame('0.01', $coupon->calculateDiscount('0.04'));
        $this->assertSame('1250000000.00', $coupon->calculateDiscount('9999999999.99'));
        $coupon->maximum_discount = '0.02';
        $this->assertSame('0.02', $coupon->calculateDiscount('1.00'));
        $coupon->maximum_discount = '0.00';
        $this->assertSame('0.00', $coupon->calculateDiscount('1.00'));
        $coupon->maximum_discount = null;
        $coupon->type = 'fixed';
        $coupon->amount = '5.00';
        $this->assertSame('1.00', $coupon->calculateDiscount('1.00'));
    }

    public function test_currency_display_preserves_minor_units_and_groups_without_float_rounding(): void
    {
        foreach ([['125.55', 'Rs. 125.55'], ['1000', 'Rs. 1,000'], ['0.01', 'Rs. 0.01'], ['-1234.05', 'Rs. -1,234.05'], ['9999999999.99', 'Rs. 9,999,999,999.99']] as [$amount, $expected]) {
            $this->assertSame($expected, Money::format(Money::minor($amount)));
        }
    }
}
