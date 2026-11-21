<?php

namespace App\Services;

use App\Services\Plugins\PluginRuntime;
use App\Services\Pricing\PricingService;
use App\Support\Countries;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\DeliveryZoneStore;
use Mavyn\Contracts\Plugins\SelectableShippingProvider;
use Mavyn\Contracts\Plugins\ShippingOption;
use Mavyn\Contracts\Plugins\ShippingQuote;
use Mavyn\Contracts\Plugins\ShippingRequest;
use RuntimeException;

final class ShippingService
{
    /**
     * Built-in fulfillment used only when no fulfillment plugin is active: made-to-order goods ship after
     * production at no extra charge (e.g. a Stripe-only store). Any active fulfillment plugin replaces it.
     */
    public const DISPATCH_PROVIDER = 'core.dispatch';

    public const DISPATCH_OPTION = 'standard';

    public function __construct(private PluginRuntime $runtime, private DeliveryZoneStore $zones) {}

    public static function dispatchLabel(): string
    {
        return trim((string) setting('dispatch_label', 'Ships after production')) ?: 'Ships after production';
    }

    /** True when checkout has no fulfillment plugins and uses the built-in dispatch option. */
    public function usesBuiltInDispatch(): bool
    {
        return $this->runtime->providers()->keys('fulfillment') === [];
    }

    public static function isBuiltInToken(?string $token): bool
    {
        return $token === self::DISPATCH_PROVIDER.':'.self::DISPATCH_OPTION;
    }

    public function request(int $subtotalMinor, string $country, ?string $option = null, ?string $postalCode = null): ShippingRequest
    {
        $country = strtoupper(trim($country));
        $configuredName = strtoupper((string) setting('store_country', config('site.country')));
        // Customers may type a full country name ("United States"), an alias ("USA") or an ISO code.
        $code = $country === $configuredName ? strtoupper((string) setting('store_country_code', config('site.country_code', 'PK'))) : (Countries::code($country) ?? $country);
        if (! preg_match('/^[A-Z]{2}$/D', $code) || Countries::name($code) === null) {
            throw ValidationException::withMessages(['country' => 'Use a supported country name or two-letter country code.']);
        }

        return new ShippingRequest($subtotalMinor, app(PricingService::class)->assertReady()->currency, $code, $postalCode, $option);
    }

    public function choices(ShippingRequest $request): array
    {
        $registry = $this->runtime->providers();
        if ($this->usesBuiltInDispatch()) {
            return [self::DISPATCH_PROVIDER.':'.self::DISPATCH_OPTION => [
                'provider_key' => self::DISPATCH_PROVIDER,
                'option' => new ShippingOption(self::DISPATCH_OPTION, self::dispatchLabel(), 0, $request->currency),
                'legacy_zone_id' => null,
            ]];
        }
        $choices = [];
        foreach ($registry->keys('fulfillment') as $key) {
            $provider = $registry->get('fulfillment', $key);
            if ($provider instanceof SelectableShippingProvider) {
                $options = $provider->options($request);
            } elseif ($key === 'mavyn.local-delivery.zone') {
                // Compatibility for packages that predate selectable fulfillment.
                $options = [];
                foreach ($this->zones->active() as $zone) {
                    $quote = $provider->quote(new ShippingRequest($request->subtotalMinor, $request->currency, $request->country, $request->postalCode, (string) $zone->id));
                    if ($quote) {
                        $options[] = new ShippingOption((string) $zone->id, $quote->label, $quote->feeMinor, $quote->currency);
                    }
                }
            } else {
                continue;
            }
            foreach ($options as $option) {
                if (! $option instanceof ShippingOption) {
                    throw new RuntimeException('Shipping providers must return ShippingOption values.');
                }
                $token = $key.':'.$option->id;
                if (isset($choices[$token])) {
                    throw new RuntimeException('Duplicate shipping option.');
                }
                if ($option->currency !== $request->currency) {
                    continue; // Unsupported denominations never become selected-currency options.
                }
                $choices[$token] = ['provider_key' => $key, 'option' => $option, 'legacy_zone_id' => $key === 'mavyn.local-delivery.zone' && ctype_digit($option->id) ? (int) $option->id : null];
            }
        }

        return $choices;
    }

    /** Resolve under the order transaction; submitted fees and labels are ignored. */
    public function choose(array $data, int $subtotalMinor): array
    {
        $request = $this->request($subtotalMinor, $data['country'], postalCode: $data['postal_code'] ?? null);
        $token = $data['shipping_option'] ?? null;
        $error = $token ? 'shipping_option' : 'delivery_zone_id';
        $token ??= isset($data['delivery_zone_id']) ? 'mavyn.local-delivery.zone:'.(int) $data['delivery_zone_id'] : '';
        $choices = $this->choices($request);
        // Built-in dispatch has a single option, so an omitted choice means it. An explicitly submitted option
        // that is no longer offered (e.g. from a since-disabled plugin) is still rejected, never silently swapped.
        if ($token === '' && $this->usesBuiltInDispatch()) {
            $token = self::DISPATCH_PROVIDER.':'.self::DISPATCH_OPTION;
        }
        $choice = $choices[$token] ?? throw ValidationException::withMessages([$error => 'Choose an available fulfillment option.']);
        if ($choice['provider_key'] === self::DISPATCH_PROVIDER) {
            return $choice + ['quote' => new ShippingQuote(0, $request->currency, $choice['option']->label)];
        }
        $quote = $this->runtime->providers()->get('fulfillment', $choice['provider_key'])->quote(
            new ShippingRequest($request->subtotalMinor, $request->currency, $request->country, $request->postalCode, $choice['option']->id),
        );
        if (! $quote instanceof ShippingQuote || $quote->currency !== $request->currency) {
            throw ValidationException::withMessages([$error => 'This fulfillment option is no longer available.']);
        }
        if ($quote->feeMinor !== $choice['option']->feeMinor) {
            throw ValidationException::withMessages([$error => 'Fulfillment pricing changed. Review checkout and try again.']);
        }

        return $choice + ['quote' => $quote];
    }
}
