<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Mavyn\Core\Currencies;
use Mavyn\Core\Pricing\QuoteFingerprint;
use PHPUnit\Framework\TestCase;

class PricingContractsTest extends TestCase
{
    public function test_sql_json_object_key_order_does_not_change_a_review_fingerprint(): void
    {
        $a = ['currency' => 'USD', 'items' => [['id' => 1, 'amount_minor' => 15000]], 'tax' => ['basis' => 'merchandise', 'rate' => '10.00']];
        $b = ['tax' => ['rate' => '10.00', 'basis' => 'merchandise'], 'items' => [['amount_minor' => 15000, 'id' => 1]], 'currency' => 'USD'];
        $this->assertSame(QuoteFingerprint::of($a), QuoteFingerprint::of($b));
        $b['currency'] = 'EUR';
        $this->assertNotSame(QuoteFingerprint::of($a), QuoteFingerprint::of($b));
        $this->assertNotSame(QuoteFingerprint::of(['items' => [1, 2]]), QuoteFingerprint::of(['items' => [2, 1]]));
    }

    public function test_money_bounds_and_signed_adjustments_remain_explicit(): void
    {
        $this->assertSame(0, Currencies::amount(0));
        $this->assertNull(Currencies::amount(null, nullable: true));
        $this->assertSame(-100, Currencies::amount(-100, signed: true));
        $this->assertSame(9, count(Currencies::SYMBOLS));
        foreach ([-1, Currencies::MAX_MINOR + 1, '100', 1.5, null] as $value) {
            try {
                Currencies::amount($value);
                $this->fail('Invalid or unbounded money was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
