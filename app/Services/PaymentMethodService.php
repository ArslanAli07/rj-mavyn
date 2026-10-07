<?php

namespace App\Services;

class PaymentMethodService
{
    /** @return list<string> */
    public function enabled(): array
    {
        return array_keys(array_filter([
            'cod' => (bool) setting('payment_cod_enabled', true),
            'easypaisa' => (bool) setting('payment_easypaisa_enabled', true),
            'jazzcash' => (bool) setting('payment_jazzcash_enabled', true),
            'bank_transfer' => (bool) setting('payment_bank_transfer_enabled', true),
        ]));
    }
}
