<?php

namespace App\Models;

use App\Models\Concerns\HasLibraryMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomeSlide extends Model
{
    use HasLibraryMedia;

    protected $fillable = [
        'image_path',
        'mobile_image_path',
        'title',
        'title_highlighted',
        'description',
        'button_text',
        'button_link',
        'left_icon',
        'right_badge_text',
        'right_badge_emoji',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(fn () => Cache::forget('home_slides_list'));
        static::deleted(fn () => Cache::forget('home_slides_list'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->libraryImages('content.hero.desktop', 'hero')[0]['url'] ?? $this->resolvePublicOrStorageUrl($this->image_path);
    }

    public function getMobileImageUrlAttribute(): string
    {
        $image = $this->libraryImages('content.hero.mobile', 'hero')[0] ?? null;
        if ($image) {
            return $image['url'];
        }
        if (empty($this->mobile_image_path)) {
            return $this->image_url;
        }

        return $this->resolvePublicOrStorageUrl($this->mobile_image_path);
    }

    public function getImageSrcsetAttribute(): string
    {
        return $this->libraryImages('content.hero.desktop', 'hero')[0]['srcset'] ?? '';
    }

    public function getMobileImageSrcsetAttribute(): string
    {
        return $this->libraryImages('content.hero.mobile', 'hero')[0]['srcset'] ?? '';
    }

    public function getImageAssetIdAttribute(): ?int
    {
        return $this->libraryAssetIds('content.hero.desktop')[0] ?? null;
    }

    public function getMobileImageAssetIdAttribute(): ?int
    {
        return $this->libraryAssetIds('content.hero.mobile')[0] ?? null;
    }

    public function getImageAltAttribute(): string
    {
        return $this->libraryImages('content.hero.desktop', 'hero')[0]['alt_text'] ?? ($this->title ?? 'Hero banner');
    }

    private function resolvePublicOrStorageUrl(?string $path): string
    {
        if (empty($path)) {
            return asset('images/placeholder.jpg');
        }

        if (str_starts_with($path, 'images/') || str_starts_with($path, 'assets/')) {
            $segments = array_map('rawurlencode', explode('/', $path));

            return asset(implode('/', $segments));
        }

        return asset('storage/'.$path);
    }
}
