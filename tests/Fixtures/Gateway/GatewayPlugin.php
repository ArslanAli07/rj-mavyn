<?php

namespace Tests\Fixtures\Gateway;

use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginContext;
use Tests\Fixtures\Pricing\PricingShipping;

final class GatewayPlugin implements Plugin
{
    public function register(PluginContext $context): void
    {
        $context->provider('payment', 'test.gateway.card', FakeGateway::class);
        $context->provider('fulfillment', 'test.gateway.delivery', PricingShipping::class);
    }
}
