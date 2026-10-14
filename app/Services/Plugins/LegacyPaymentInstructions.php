<?php

namespace App\Services\Plugins;

use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\SettingsStore;

/** Historical identifiers remain readable without the optional payment package. */
final class LegacyPaymentInstructions
{
    public static function for(string $id, SettingsStore $settings): ?PaymentInstructions
    {
        return match ($id) {
            'cod' => new PaymentInstructions('Cash on Delivery', 'Pay when your order arrives.', false),
            'easypaisa', 'jazzcash' => new PaymentInstructions($id === 'easypaisa' ? 'Easypaisa' : 'JazzCash',
                'Account: '.$settings->get($id.'_account', '')."\nAccount name: ".$settings->get($id.'_name', ''), true),
            'bank_transfer' => new PaymentInstructions('Bank Transfer',
                'Bank: '.$settings->get('bank_name', '')."\nAccount title: ".$settings->get('bank_title', '')."\nAccount: ".$settings->get('bank_account', '')."\nIBAN: ".$settings->get('bank_iban', ''), true),
            default => null,
        };
    }
}
