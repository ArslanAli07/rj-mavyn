<?php

namespace App\Models;

use App\Models\Concerns\HasLibraryMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasLibraryMedia;

    protected $fillable = [
        'name', 'slug', 'description', 'image', 'display_order', 'is_active', 'show_on_homepage',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        $image = $this->libraryImages('catalog.category.hero', 'product-card')[0] ?? null;
        if ($image) {
            return $image['url'];
        }
        if (empty($this->image)) {
            return asset('images/placeholder.svg');
        }

        if (str_starts_with($this->image, 'assets/') || str_starts_with($this->image, 'images/')) {
            $segments = array_map('rawurlencode', explode('/', $this->image));

            return asset(implode('/', $segments));
        }

        return asset('storage/'.$this->image);
    }

    public function getImageAssetIdAttribute(): ?int
    {
        return $this->libraryAssetIds('catalog.category.hero')[0] ?? null;
    }

    public function getImageSrcsetAttribute(): string
    {
        return $this->libraryImages('catalog.category.hero', 'product-card')[0]['srcset'] ?? '';
    }

    public function getImageAltAttribute(): string
    {
        return $this->libraryImages('catalog.category.hero')[0]['alt_text'] ?? $this->name;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
