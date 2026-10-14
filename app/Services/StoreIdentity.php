<?php

namespace App\Services;

final class StoreIdentity
{
    public static function snapshot(): array
    {
        return [
            'name' => trim((string) setting('site_name', config('site.name', 'Store'))) ?: 'Store',
            'tagline' => (string) setting('site_tagline', config('site.tagline', '')),
            'address' => (string) setting('site_address', config('site.address', '')),
            'phone' => (string) setting('site_phone', config('site.phone', '')),
            'email' => (string) setting('site_email', config('site.email', '')),
        ];
    }
}
