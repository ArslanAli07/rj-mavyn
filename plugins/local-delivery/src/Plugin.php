<?php

namespace Mavyn\LocalDelivery;

use Mavyn\Contracts\Plugins\Plugin as PluginContract;
use Mavyn\Contracts\Plugins\PluginContext;

final class Plugin implements PluginContract
{
    public function register(PluginContext $context): void
    {
        $context->provider('fulfillment', $context->id().'.zone', ZoneProvider::class);
    }
}
