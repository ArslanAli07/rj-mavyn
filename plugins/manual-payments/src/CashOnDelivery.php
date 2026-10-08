<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\PaymentInstructions;

final class CashOnDelivery extends ManualMethod
{
    public function methodId(): string
    {
        return 'cod';
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Cash on Delivery', 'Pay when your order arrives.', false);
    }
}
