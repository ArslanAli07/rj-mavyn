<?php

namespace Mavyn\Core;

use Mavyn\Contracts\SettingsStore;

final class PaymentMethods
{
    public function __construct(private SettingsStore $settings) {}

    /** @return list<string> */
    public function enabled(): array
    {
        return array_values(array_filter(['cod', 'easypaisa', 'jazzcash', 'bank_transfer'],
            fn (string $method) => filter_var($this->settings->get('payment_'.$method.'_enabled', true), FILTER_VALIDATE_BOOLEAN)));
    }
}
