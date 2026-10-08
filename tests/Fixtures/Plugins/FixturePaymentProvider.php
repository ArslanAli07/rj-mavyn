<?php

namespace Tests\Fixtures\Plugins;

use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\Plugins\PaymentProvider;

final class FixturePaymentProvider implements PaymentProvider
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Test payment', 'Test instructions', true);
    }
}
