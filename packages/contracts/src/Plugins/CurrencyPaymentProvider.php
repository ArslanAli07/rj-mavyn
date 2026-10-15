<?php

namespace Mavyn\Contracts\Plugins;

/** Additive to SelectablePaymentProvider; enablement never implies currency support. */
interface CurrencyPaymentProvider
{
    public function supportsCurrency(string $currency): bool;
}
