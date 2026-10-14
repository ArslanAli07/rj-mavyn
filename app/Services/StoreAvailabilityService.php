<?php

namespace App\Services;

use App\Services\Plugins\PluginRuntime;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\OrderingAvailability;

final class StoreAvailabilityService
{
    public function __construct(private PluginRuntime $runtime) {}

    public function evaluate(): OrderingAvailability
    {
        $registry = $this->runtime->providers();
        $enabled = true;
        $open = true;
        $labels = [];
        foreach ($registry->keys('ordering.policy') as $key) {
            $result = $registry->get('ordering.policy', $key)->evaluate(DateTimeImmutable::createFromInterface(now((string) setting('store_timezone', config('site.timezone')))));
            $enabled = $enabled && $result->manuallyEnabled;
            $open = $open && $result->open;
            $labels[] = $result->hoursLabel;
        }

        return new OrderingAvailability($enabled, $open, implode('; ', $labels));
    }

    public function assertAvailable(): void
    {
        if (! $this->evaluate()->allowed()) {
            throw ValidationException::withMessages(['cart' => 'Online ordering is currently unavailable.']);
        }
    }
}
