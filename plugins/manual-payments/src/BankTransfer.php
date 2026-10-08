<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\PaymentInstructions;

final class BankTransfer extends ManualMethod
{
    public function methodId(): string
    {
        return 'bank_transfer';
    }

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Bank Transfer', 'Bank: '.($this->settings->get('bank_name', ''))."\nAccount title: ".($this->settings->get('bank_title', ''))."\nAccount: ".($this->settings->get('bank_account', ''))."\nIBAN: ".($this->settings->get('bank_iban', '')), true);
    }
}
