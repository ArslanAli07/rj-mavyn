<?php

namespace Mavyn\Contracts\Payments;

interface RefundProvider extends GatewayProvider
{
    public function refund(RefundContext $context): RefundResult;

    public function reconcileRefund(RefundContext $context): RefundResult;
}
