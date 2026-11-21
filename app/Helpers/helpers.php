<?php

use App\Models\Order;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\Media\ContentMediaService;
use App\Services\Plugins\PluginRuntime;
use App\Services\Pricing\PricingService;
use App\Services\Seo\StructuredDataService;
use App\Services\StoreAvailabilityService;
use App\Services\Themes\ThemeRuntime;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Mavyn\Core\Currencies;
use Mavyn\Core\Money;

if (! function_exists('plugin_active')) {
    function plugin_active(string $id): bool
    {
        return app(PluginRuntime::class)->active($id);
    }
}

if (! function_exists('format_price')) {
    /**
     * Format current catalog prices with the configured store symbol.
     */
    function format_price(float|int|string|null $amount, ?string $currency = null): string
    {
        if ($amount === null) {
            return 'Unavailable';
        }
        $context = app(PricingService::class)->context();

        return Money::format(Money::minor($amount), $currency ? Currencies::symbol($currency) : $context->symbol);
    }
}

function order_price(Order $order, float|int|string|null $amount): string
{
    return Money::format(Money::minor($amount), $order->currency_symbol ?? 'Rs.');
}

if (! function_exists('setting')) {
    /**
     * Get a site setting value.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('store_timezone')) {
    /** The store's configured local timezone; timestamps are stored in the app timezone (UTC). */
    function store_timezone(): string
    {
        $timezone = (string) setting('store_timezone', config('site.timezone', config('app.timezone')));

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone');
    }
}

if (! function_exists('store_time')) {
    /** Convert a stored timestamp to store-local time for display, e.g. store_time($order->created_at)->format('d M Y h:i A T'). */
    function store_time(?DateTimeInterface $time): ?Carbon
    {
        return $time ? Carbon::instance($time)->copy()->setTimezone(store_timezone()) : null;
    }
}

if (! function_exists('seo_schema')) {
    /**
     * Render a JSON-LD <script> tag for a structured-data graph built by
     * the StructuredDataService (Product, BreadcrumbList, ItemList…).
     */
    function seo_schema(array $graph): HtmlString
    {
        return app(StructuredDataService::class)->render($graph);
    }
}

if (! function_exists('ordering_manually_enabled')) {
    /**
     * Whether the admin "Accept online orders" toggle is on.
     */
    function ordering_manually_enabled(): bool
    {
        return app(StoreAvailabilityService::class)->evaluate()->manuallyEnabled;
    }
}

if (! function_exists('shop_is_open')) {
    /**
     * Whether the current time in the site timezone falls within ordering hours.
     * An overnight window (start > end) is supported. Empty hours count as always open.
     */
    function shop_is_open(): bool
    {
        return app(StoreAvailabilityService::class)->evaluate()->open;
    }
}

if (! function_exists('ordering_hours_label')) {
    /**
     * Human-readable daily ordering window, e.g. "9:00 AM to 9:00 PM".
     */
    function ordering_hours_label(): string
    {
        return app(StoreAvailabilityService::class)->evaluate()->hoursLabel;
    }
}

if (! function_exists('ordering_enabled')) {
    /**
     * Whether online ordering (cart / checkout) is currently available.
     */
    function ordering_enabled(): bool
    {
        return app(StoreAvailabilityService::class)->evaluate()->allowed();
    }
}

if (! function_exists('cart_count')) {
    /**
     * Get current cart item count.
     */
    function cart_count(): int
    {
        return app(CartService::class)->count();
    }
}

if (! function_exists('wishlist_count')) {
    /**
     * Get current wishlist item count.
     * Cached statically for the lifetime of the request to avoid
     * repeated DB queries when called from multiple views/components.
     */
    function wishlist_count(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        static $count = null;
        if ($count === null) {
            $count = auth()->user()->wishlists()->count();
        }

        return $count;
    }
}

if (! function_exists('store_media_url')) {
    function store_media_url(string $key, string $fallback): string
    {
        return app(ContentMediaService::class)->branding($key)['url'] ?? $fallback;
    }
}

/** Validated, request-scoped presentation setting; preview values never become store configuration. */
function theme_setting(string $key, mixed $default = null): mixed
{
    return app(ThemeRuntime::class)->state()['settings'][$key] ?? $default;
}

/** Immutable packaged asset, resolved parent before child. */
function theme_asset(string $name): string
{
    return app(ThemeRuntime::class)->state()['assets']['files'][$name] ?? throw new InvalidArgumentException('Theme asset not declared.');
}
