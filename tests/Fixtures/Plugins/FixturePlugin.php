<?php

namespace Tests\Fixtures\Plugins;

use App\Models\Setting;
use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginContext;
use Mavyn\Contracts\Plugins\PluginLifecycle;
use Mavyn\Contracts\Plugins\ProductDisplay;
use RuntimeException;

final class FixturePlugin implements Plugin, PluginLifecycle
{
    public static bool $failActivation = false;

    public static bool $failDeactivation = false;

    public static array $events = [];

    public function register(PluginContext $context): void
    {
        $context->provider('payment', $context->id().'.manual', FixturePaymentProvider::class);
        $context->filter('core.catalog.product.display', 'notice', fn (ProductDisplay $display) => new ProductDisplay($display->productId, [...$display->notices, $context->setting('notice')]));
        $context->action('core.catalog.product.displayed', 'record', function (ProductDisplay $display) use ($context) {
            self::$events[] = $context->id();
        });
    }

    public function activate(PluginContext $context): void
    {
        Setting::set('fixture_setup', $context->id());
        if (self::$failActivation) {
            throw new RuntimeException('Activation failed');
        }
    }

    public function deactivate(PluginContext $context): void
    {
        if (self::$failDeactivation) {
            throw new RuntimeException('Open operations must drain');
        }
    }
}
