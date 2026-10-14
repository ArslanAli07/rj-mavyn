<?php

namespace App\Services;

use App\Services\Plugins\LegacyPaymentInstructions;
use App\Services\Plugins\PluginRuntime;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\Plugins\SelectablePaymentProvider;
use Mavyn\Contracts\SettingsStore;
use RuntimeException;

class PaymentMethodService
{
    public function __construct(private PluginRuntime $runtime, private SettingsStore $settings) {}

    /** @return array<string, array{provider_key: string, instructions: PaymentInstructions}> */
    public function choices(): array
    {
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
            $methods[$id] = ['provider_key' => $key, 'instructions' => $provider->instructions()];
        }

        return $methods;
    }

    public function enabled(): array
    {
        return array_keys($this->choices());
    }

    public function choose(string $id): array
    {
        return $this->choices()[$id] ?? throw ValidationException::withMessages(['payment_method' => 'This payment method is unavailable.']);
    }

    public function historical(string $id): ?PaymentInstructions
    {
        return LegacyPaymentInstructions::for($id, $this->settings);
    }
}
