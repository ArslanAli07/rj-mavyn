<?php

namespace Mavyn\Core\Plugins;

use Closure;
use InvalidArgumentException;
use Mavyn\Contracts\Plugins\OrderingPolicy;
use Mavyn\Contracts\Plugins\PaymentProvider;
use Mavyn\Contracts\Plugins\ProductAttributesProvider;
use Mavyn\Contracts\Plugins\ShippingProvider;

final class ProviderRegistry
{
    private const TYPES = ['payment' => PaymentProvider::class, 'fulfillment' => ShippingProvider::class, 'product.attributes' => ProductAttributesProvider::class, 'ordering.policy' => OrderingPolicy::class];

    private bool $sealed = false;

    public function seal(): void
    {
        $this->sealed = true;
    }

    private array $providers = [];

    public function __construct(private Closure $resolve) {}

    public function add(string $kind, string $key, string $class): void
    {
        $contract = self::TYPES[$kind] ?? throw new InvalidArgumentException('Unknown provider kind.');
        if ($this->sealed || ! is_subclass_of($class, $contract) || isset($this->providers[$kind][$key])) {
            throw new InvalidArgumentException('Invalid or duplicate provider: '.$key);
        }
        $this->providers[$kind][$key] = $class;
    }

    public function keys(string $kind): array
    {
        return array_keys($this->providers[$kind] ?? []);
    }

    public function get(string $kind, string $key): object
    {
        $class = $this->providers[$kind][$key] ?? throw new InvalidArgumentException('Unknown provider.');
        $provider = ($this->resolve)($class);
        $contract = self::TYPES[$kind];
        if (! $provider instanceof $contract) {
            throw new InvalidArgumentException('Invalid provider implementation.');
        }

        return $provider;
    }
}
