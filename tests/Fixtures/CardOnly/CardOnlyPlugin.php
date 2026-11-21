<?php

namespace Tests\Fixtures\CardOnly;

use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginContext;
use Tests\Fixtures\Gateway\FakeGateway;

/** A Stripe-like store: a hosted card gateway and no fulfillment plugin at all. */
final class CardOnlyPlugin implements Plugin
{
    public function register(PluginContext $context): void
    {
        $context->provider('payment', 'test.cardonly.card', FakeGateway::class);
    }
}
