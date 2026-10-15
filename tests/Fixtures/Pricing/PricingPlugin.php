<?php

namespace Tests\Fixtures\Pricing;

use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginContext;

final class PricingPlugin implements Plugin
{
    public function register(PluginContext $context): void
    {
        $context->provider('payment', $context->id().'.manual', PricingPayment::class);
        $context->provider('fulfillment', $context->id().'.delivery', PricingShipping::class);
    }
}
