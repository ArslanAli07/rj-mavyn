<?php

namespace App\Services;

use App\Services\Plugins\LegacyPaymentInstructions;
use App\Services\Plugins\PluginRuntime;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\CurrencyPaymentProvider;
use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\Plugins\SelectablePaymentProvider;
use Mavyn\Contracts\SettingsStore;
use RuntimeException;

class PaymentMethodService
{
    public function __construct(private PluginRuntime $runtime, private SettingsStore $settings) {}

    /** @return array<string, array{provider_key: string, instructions: PaymentInstructions}> */
    public function choices(?string $currency = null): array
    {
        $currency ??= app(PricingService::class)->assertReady()->currency;
        $registry = $this->runtime->providers();
        $methods = [];
        foreach ($registry->keys('payment') as $key) {
            $provider = $registry->get('payment', $key);
            if (! $provider instanceof SelectablePaymentProvider || ! $provider->enabled()) {
                continue;
            }
            $id = $provider->methodId();
            if (! preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $id) || isset($methods[$id])) {
                throw new RuntimeException('Invalid or duplicate checkout payment identifier.');
            }
            if (! $this->eligible($key, $id, $provider, $currency)) {
                continue;
            }
            $methods[$id] = ['provider_key' => $key, 'instructions' => $provider->instructions()];
        }

        return $methods;
    }

    public function enabled(): array
    {
        return array_keys($this->choices());
    }

    public function choose(string $id, ?string $currency = null): array
    {
        return $this->choices($currency)[$id] ?? throw ValidationException::withMessages(['payment_method' => 'This payment method is unavailable.']);
    }

    private function eligible(string $key, string $method, object $provider, string $currency): bool
    {
        // Known wallet identities remain PKR-only, even with an erroneous broad policy.
        if (in_array($key, ['mavyn.manual-payments.easypaisa', 'mavyn.manual-payments.jazzcash'], true)) {
            return $currency === 'PKR';
        }
        $policy = DB::table('payment_currency_policies')->where('provider_key', $key)->value('currencies');
        if ($policy !== null) {
            return in_array($currency, json_decode($policy, true, 512, JSON_THROW_ON_ERROR), true);
        }
        if ($provider instanceof CurrencyPaymentProvider) {
            return $provider->supportsCurrency($currency);
        }

        return false; // Legacy COD/bank/unknown methods require recorded provider-specific evidence.
    }

    public function historical(string $id): ?PaymentInstructions
    {
        return LegacyPaymentInstructions::for($id, $this->settings);
    }
}
