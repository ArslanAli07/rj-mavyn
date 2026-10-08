<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\PaymentInstructions;

final class Jazzcash extends ManualMethod
{
    public function methodId(): string
    {
        return 'jazzcash';
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('JazzCash', 'Account: '.($this->settings->get('jazzcash_account', ''))."\nAccount name: ".($this->settings->get('jazzcash_name', '')), true);
    }
}
