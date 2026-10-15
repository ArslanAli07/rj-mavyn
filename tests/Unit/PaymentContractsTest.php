<?php

namespace Tests\Unit;

use Mavyn\Contracts\Payments\PaymentAction;
use Mavyn\Contracts\Payments\PaymentContext;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\RefundResult;
use PHPUnit\Framework\TestCase;

final class PaymentContractsTest extends TestCase
{
    public function test_gateway_context_and_results_are_immutable_currency_values(): void
    {
        $context = new PaymentContext('attempt', 1, 'ORDER-1', 15000, 'USD', 'owner', 'owner@example.test', 'https://example.test/return', 'https://example.test/cancel', 'created');
        $this->assertSame(15000, $context->amountMinor);
        $this->assertTrue((new \ReflectionClass($context))->isReadOnly());
        $this->assertSame('USD', (new PaymentResult('attempt', 'evt', 'paid', 15000, 'USD', 'ref'))->currency);
    }

    public function test_payment_action_rejects_untrusted_redirect_schemes(): void
    {
        foreach (['javascript:alert(1)', 'http://gateway.example.test', 'https://user:password@gateway.example.test'] as $url) {
            try {
                new PaymentAction('redirect', $url);
                $this->fail('Unsafe redirect accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('redirect', (new PaymentAction('redirect', 'https://gateway.example.test/pay'))->type);
    }

    public function test_verified_results_require_supported_state_money_and_reference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentResult('attempt', 'evt', 'paid', 100, 'USD');
    }

    public function test_refund_confirmation_requires_a_gateway_reference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RefundResult('refund', 'evt', 'succeeded', 100, 'USD');
    }
}
