<?php

namespace Tests\Unit;

use Mavyn\Contracts\SettingsStore;
use Mavyn\Core\CouponIdentity;
use Mavyn\Core\CouponPricing;
use Mavyn\Core\Money;
use Mavyn\Core\PaymentMethods;
use Mavyn\Core\ProductConfiguration;
use Mavyn\Core\SettingsSchema;
use PHPUnit\Framework\TestCase;

class CorePackagesTest extends TestCase
{
    public function test_commerce_services_run_without_laravel_application(): void
    {
        $store = new class implements SettingsStore
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return $key === 'payment_cod_enabled' ? 'false' : $default;
            }
        };
        $this->assertSame(['easypaisa', 'jazzcash', 'bank_transfer'], (new PaymentMethods($store))->enabled());
        $this->assertSame('1.01', Money::decimal(Money::minor('1.005')));
        $pricing = new CouponPricing;
        $this->assertSame('0.00', $pricing->discount('10', 'fixed', '5', '11'));
        $this->assertSame('2.50', $pricing->discount('10', 'percentage', '50', null, '2.50'));
        $this->assertSame('10.00', $pricing->discount('10', 'fixed', '20'));
        $this->assertSame(CouponIdentity::pairHash(' A@EXAMPLE.COM ', '127.0.0.1'), CouponIdentity::pairHash('a@example.com', '::ffff:127.0.0.1'));
        $this->assertNull(CouponIdentity::pairHash('a@example.com', 'bad'));
        $this->assertSame(ProductConfiguration::signature(1, 2, [3, '4', 3, 0], ' Hello '), ProductConfiguration::signature(1, 2, [4, 3], 'Hello'));
        $this->assertNotSame(ProductConfiguration::signature(1, 2), ProductConfiguration::signature(1, 3));
        $this->assertArrayHasKey('site_name', (new SettingsSchema)->definitions());
    }
}
