<?php

namespace Tests\Fixtures\Pricing;

use Mavyn\Contracts\Plugins\CurrencyPaymentProvider;
use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\Plugins\SelectablePaymentProvider;

final class PricingPayment implements CurrencyPaymentProvider, SelectablePaymentProvider
{
    public function methodId(): string
    {
        return 'fixture_manual';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array($currency, ['PKR', 'USD', 'EUR', 'GBP'], true);
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Fixture manual payment', 'Pay the order amount in the order currency.');
    }
}
