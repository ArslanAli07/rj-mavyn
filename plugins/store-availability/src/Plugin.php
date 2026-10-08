<?php

namespace Mavyn\StoreAvailability;

use Mavyn\Contracts\Plugins\Plugin as PluginContract;
use Mavyn\Contracts\Plugins\PluginContext;

final class Plugin implements PluginContract
{
    public function register(PluginContext $context): void
    {
        $context->provider('ordering.policy', $context->id().'.hours', DailyHours::class);
    }
}
