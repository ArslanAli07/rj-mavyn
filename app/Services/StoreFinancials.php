<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreFinancials
{
    public const CURRENCIES = ['PKR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'AED', 'SAR', 'INR'];

    public function snapshot(): array
    {
        $currency = (string) setting('store_currency', config('site.currency', 'PKR'));
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw ValidationException::withMessages(['store_currency' => 'Choose a supported two-decimal currency.']);
        }
        $rate = (string) setting('store_tax_rate', config('site.tax_rate', 0));
        if (Money::minor($rate) < 0 || Money::minor($rate) > 10000) {
            throw ValidationException::withMessages(['store_tax_rate' => 'Tax rate must be between 0 and 100 percent.']);
        }

        return ['currency' => $currency, 'currency_symbol' => (string) setting('store_currency_symbol', config('site.currency_symbol', 'Rs.')), 'tax_rate' => Money::decimal(Money::minor($rate))];
    }

    /** Exclusive tax on discounted merchandise, excluding fulfillment charges. */
    public function taxMinor(int $subtotalMinor, int $discountMinor, ?string $rate = null): int
    {
        return Money::percentage(max(0, $subtotalMinor - $discountMinor), $rate ?? $this->snapshot()['tax_rate']);
    }

    public function validateChange(array $values): void
    {
        // Catalog creation and checkout share this lock; settings take it exclusively.
        DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
        $current = $this->snapshot()['currency'];
        if (isset($values['store_currency']) && $values['store_currency'] !== $current && (Product::exists() || Order::exists())) {
            throw ValidationException::withMessages(['store_currency' => 'Currency cannot change after products or orders exist. Use a reviewed catalog migration or a new store.']);
        }
    }
}
