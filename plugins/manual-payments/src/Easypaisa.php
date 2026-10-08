<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\PaymentInstructions;

final class Easypaisa extends ManualMethod
{
    public function methodId(): string
    {
        return 'easypaisa';
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Easypaisa', 'Account: '.($this->settings->get('easypaisa_account', ''))."\nAccount name: ".($this->settings->get('easypaisa_name', '')), true);
    }
}
