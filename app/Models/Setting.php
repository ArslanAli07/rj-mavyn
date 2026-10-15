<?php

namespace App\Models;

use App\Models\Concerns\HasLibraryMedia;
use App\Services\Plugins\LegacyCommerceSettings;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Setting extends Model
{
    use HasLibraryMedia;

    protected $fillable = ['key', 'value', 'group', 'type'];

    public function save(array $options = [])
    {
        $pricing = app(PricingService::class);
        if (! in_array($this->key, ['store_currency', 'store_currency_symbol', 'store_tax_rate', 'store_country', 'store_country_code', 'store_timezone'], true) || ! $pricing->installed()) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($pricing, $options) {
            $context = $pricing->lock(true);
            if (($this->key === 'store_currency' && $this->value !== $context->currency) ||
                ($this->key === 'store_currency_symbol' && $this->value !== $context->symbol)) {
                throw ValidationException::withMessages([$this->key => 'Change the selling currency from currency management.']);
            }
            if ($this->key === 'store_tax_rate' && (Money::minor($this->value) < 0 || Money::minor($this->value) > 10000)) {
                throw ValidationException::withMessages([$this->key => 'Tax must be between zero and 100 percent.']);
            }
            $changed = $this->isDirty();
            $saved = parent::save($options);
            if ($saved && $changed) {
                $pricing->bump();
            }

            return $saved;
        });
    }

    /**
     * Load ALL settings into a single cache entry.
     * This means any page reading N settings only costs 1 cache read,
     * not N — regardless of how many setting() calls are made.
     */
    protected static function allCached(): array
    {
        return Cache::rememberForever('all_settings', function () {
            return static::all()->mapWithKeys(fn ($s) => [
                $s->key => ['value' => $s->value, 'type' => $s->type],
            ])->toArray();
        });
    }

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (in_array($key, ['store_currency', 'store_currency_symbol'], true) && app(PricingService::class)->installed()) {
            $context = app(PricingService::class)->context();

            return $key === 'store_currency' ? $context->currency : $context->symbol;
        }
        $bridge = app(LegacyCommerceSettings::class);
        if ($bridge->owns($key)) {
            return $bridge->read($key, $default);
        }
        $settings = static::allCached();
        $setting = $settings[$key] ?? null;

        if (! $setting) {
            return $default;
        }

        return match ($setting['type']) {
            'boolean' => filter_var($setting['value'], FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting['value'], true),
            default => $setting['value'],
        };
    }

    /**
     * Set a setting value and bust the bulk cache.
     */
    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'text'): void
    {
        $bridge = app(LegacyCommerceSettings::class);
        if ($bridge->owns($key)) {
            $bridge->write($key, $value);

            return;
        }
        $existing = static::where('key', $key)->first();
        $finalType = $existing ? $existing->type : $type;
        $finalGroup = $existing ? $existing->group : $group;

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'group' => $finalGroup,
                'type' => $finalType,
            ]
        );
        // Bust the single bulk key so the next read re-loads all settings fresh.
        Cache::forget('all_settings');
        Cache::forget('homepage_gallery');
    }
}
