<?php

namespace App\Services;

use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreFinancials
{
    public const CURRENCIES = ['PKR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'AED', 'SAR', 'INR'];

    public function snapshot(): array
    {
        $context = app(PricingService::class)->context();
        $currency = $context->currency;
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw ValidationException::withMessages(['store_currency' => 'Choose a supported two-decimal currency.']);
        }
        $rate = (string) (DB::table('settings')->useWritePdo()->where('key', 'store_tax_rate')->value('value') ?? config('site.tax_rate', 0));
        if (Money::minor($rate) < 0 || Money::minor($rate) > 10000) {
            throw ValidationException::withMessages(['store_tax_rate' => 'Tax rate must be between 0 and 100 percent.']);
        }

        return ['currency' => $currency, 'currency_symbol' => $context->symbol, 'tax_rate' => Money::decimal(Money::minor($rate))];
    }

    /** Exclusive tax on discounted merchandise, excluding fulfillment charges. */
    public function taxMinor(int $subtotalMinor, int $discountMinor, ?string $rate = null): int
    {
        return Money::percentage(max(0, $subtotalMinor - $discountMinor), $rate ?? $this->snapshot()['tax_rate']);
    }

    public function validateChange(array $values): void
    {
        $pricing = app(PricingService::class);
        $pricing->lock(true);
        DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
        if (isset($values['store_currency']) && $values['store_currency'] !== $pricing->context()->currency) {
            throw ValidationException::withMessages(['store_currency' => 'Change the selling currency from currency management.']);
        }
        if (isset($values['store_currency_symbol']) && $values['store_currency_symbol'] !== $pricing->context()->symbol) {
            throw ValidationException::withMessages(['store_currency_symbol' => 'The currency symbol follows the selected ISO currency.']);
        }
    }
}
