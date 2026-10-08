<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\Plugin as PluginContract;
use Mavyn\Contracts\Plugins\PluginContext;

final class Plugin implements PluginContract
{
    public function register(PluginContext $context): void
    {
        foreach (Methods::classes() as $id => $class) {
            $context->provider('payment', $context->id().'.'.$id, $class);
        }
    }
}
