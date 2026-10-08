<?php

namespace Mavyn\FoodProducts;

use Mavyn\Contracts\Plugins\Plugin as PluginContract;
use Mavyn\Contracts\Plugins\PluginContext;

final class Plugin implements PluginContract
{
    public function register(PluginContext $context): void
    {
        $context->provider('product.attributes', $context->id().'.food', FoodAttributes::class);
    }
}
