<?php

namespace Mavyn\Contracts\Payments;

use Mavyn\Contracts\Plugins\CurrencyPaymentProvider;
use Mavyn\Contracts\Plugins\SelectablePaymentProvider;

/** Additive to manual payments. External operations must honor the persistent operation IDs. */
interface GatewayProvider extends CurrencyPaymentProvider, SelectablePaymentProvider
{
    public function initiate(PaymentContext $context): PaymentAction;

    /** Retrieve authoritative gateway state; do not infer failure from a transport error. */
    public function reconcile(PaymentContext $context): PaymentResult;
}
