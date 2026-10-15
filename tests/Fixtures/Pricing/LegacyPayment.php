<?php

namespace Tests\Fixtures\Pricing;

use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\Plugins\SelectablePaymentProvider;

final class LegacyPayment implements SelectablePaymentProvider
{
    public function methodId(): string
    {
        return 'legacy_manual';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Legacy manual payment', 'Original instructions.');
    }
}
