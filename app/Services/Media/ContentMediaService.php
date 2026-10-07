<?php

namespace App\Services\Media;

use App\Models\Setting;

class ContentMediaService
{
    public function branding(string $key): ?array
    {
        $slot = match ($key) {
            'site_logo' => 'store.logo', 'site_favicon' => 'store.favicon',
            default => throw new \InvalidArgumentException('Unknown branding image.'),
        };

        return Setting::where('key', $key)->first()?->libraryImages($slot)[0] ?? null;
    }

    public function gallery(): array
    {
        $library = Setting::where('key', 'homepage_gallery')->first()?->libraryImages('content.gallery', 'product-detail') ?? [];
        if ($library) {
            return array_map(fn ($image) => ['src' => $image['url'], 'alt' => $image['alt_text'], 'srcset' => $image['srcset']], $library);
        }
        $legacy = Setting::get('homepage_gallery', []);
        if (! is_array($legacy)) {
            return [];
        }

        return collect($legacy)->filter(fn ($image) => is_array($image) && ! empty($image['src']))
            ->map(fn ($image) => [
                'src' => asset(implode('/', array_map('rawurlencode', explode('/', $image['src'])))),
                'alt' => $image['alt'] ?? 'Gallery image',
            ])->values()->all();
    }
}
