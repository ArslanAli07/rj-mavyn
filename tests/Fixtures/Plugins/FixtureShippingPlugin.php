<?php

namespace Tests\Fixtures\Plugins;

use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginContext;

final class FixtureShippingPlugin implements Plugin
{
    public function register(PluginContext $context): void
    {
        $context->provider('fulfillment', $context->id().'.shipping', FixtureShippingProvider::class);
        $context->provider('product.attributes', $context->id().'.attributes', FixtureAttributesProvider::class);
    }
}
