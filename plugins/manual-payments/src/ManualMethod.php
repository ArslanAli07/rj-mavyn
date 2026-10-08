<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\SelectablePaymentProvider;
use Mavyn\Contracts\SettingsStore;

abstract class ManualMethod implements SelectablePaymentProvider
{
    public function __construct(protected SettingsStore $settings) {}

    public function enabled(): bool
    {
        return filter_var($this->settings->get('payment_'.$this->methodId().'_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }
}
