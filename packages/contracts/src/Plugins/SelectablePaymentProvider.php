<?php

namespace Mavyn\Contracts\Plugins;

interface SelectablePaymentProvider extends PaymentProvider
{
    public function methodId(): string;

    public function enabled(): bool;
}
