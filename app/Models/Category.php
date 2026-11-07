<?php

namespace App\Models;

use App\Models\Concerns\HasLibraryMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasLibraryMedia;

    protected $fillable = [
        'name', 'slug', 'description', 'image', 'display_order', 'is_active', 'show_on_homepage',
        'subgroup', 'nav_badge',
    ];

    public const NAV_BADGES = ['new', 'hot'];

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order')->orderBy('id');
    }

    /** The category and every category beneath it. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue !== []) {
            $children = static::query()->whereIn('parent_id', $queue)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));
            if ($children === []) {
                break;
            }
            $ids = [...$ids, ...$children];
            $queue = $children;
        }

        return $ids;
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
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
