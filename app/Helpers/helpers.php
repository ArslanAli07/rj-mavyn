<?php

use App\Models\Setting;
use App\Services\CartService;
use App\Services\Media\ContentMediaService;
use Carbon\Carbon;

if (! function_exists('format_price')) {
    /**
     * Format a number as PKR price.
     */
    function format_price(float|int|string $amount): string
    {
        return 'Rs. '.number_format((float) $amount, 0);
    }
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

if (! function_exists('ordering_manually_enabled')) {
    /**
     * Whether the admin "Accept online orders" toggle is on.
     */
    function ordering_manually_enabled(): bool
    {
        return (bool) setting('ordering_enabled', true);
    }
}

if (! function_exists('shop_is_open')) {
    /**
     * Whether the current time in the site timezone falls within ordering hours.
     * An overnight window (start > end) is supported. Empty hours count as always open.
     */
    function shop_is_open(): bool
    {
        $start = substr((string) setting('ordering_start_time', ''), 0, 5);
        $end = substr((string) setting('ordering_end_time', ''), 0, 5);

        if ($start === '' || $end === '') {
            return true;
        }

        $now = now(config('site.timezone'))->format('H:i');

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }
}

if (! function_exists('ordering_hours_label')) {
    /**
     * Human-readable daily ordering window, e.g. "9:00 AM to 9:00 PM".
     */
    function ordering_hours_label(): string
    {
        $start = substr((string) setting('ordering_start_time', '09:00'), 0, 5) ?: '09:00';
        $end = substr((string) setting('ordering_end_time', '21:00'), 0, 5) ?: '21:00';

        try {
            $startLabel = Carbon::createFromFormat('H:i', $start)->format('g:i A');
            $endLabel = Carbon::createFromFormat('H:i', $end)->format('g:i A');

            return $startLabel.' to '.$endLabel;
        } catch (Throwable) {
            return $start.' to '.$end;
        }
    }
}

if (! function_exists('ordering_enabled')) {
    /**
     * Whether online ordering (cart / checkout) is currently available.
     */
    function ordering_enabled(): bool
    {
        return ordering_manually_enabled() && shop_is_open();
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
