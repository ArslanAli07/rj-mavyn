<?php

namespace Tests\Fixtures\Plugins;

use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginContext;

final class BadPlugin implements Plugin
{
    public function register(PluginContext $context): void
    {
        $context->provider('payment', $context->id().'.broken', \stdClass::class);
    }
}
