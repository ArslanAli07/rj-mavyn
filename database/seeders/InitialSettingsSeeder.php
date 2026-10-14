<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\StoreFinancials;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Mavyn\Core\SettingsSchema;

/** Only declared platform defaults; no catalog, branding images or merchant content. */
class InitialSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $financials = app(StoreFinancials::class)->snapshot();
        $configured = [
            'site_name' => trim((string) config('site.name', 'Store')) ?: 'Store',
            'store_currency' => $financials['currency'],
            'store_currency_symbol' => $financials['currency_symbol'],
            'store_tax_rate' => $financials['tax_rate'],
            'store_country' => config('site.country', 'Pakistan'),
            'store_country_code' => config('site.country_code', 'PK'),
            'store_timezone' => config('site.timezone', 'Asia/Karachi'),
        ];
        foreach ((new SettingsSchema)->definitions() as $key => $definition) {
            $value = $configured[$key] ?? $definition['default'];
            // Unconfigured optional content/contact fields retain their normal config fallbacks.
            if ($value === null) {
                continue;
            }
            Setting::firstOrCreate(['key' => $key], [
                'value' => is_bool($value) ? (string) (int) $value : $value,
                'type' => $definition['type'], 'group' => $definition['group'],
            ]);
        }
        Cache::forget('all_settings');
    }
}
