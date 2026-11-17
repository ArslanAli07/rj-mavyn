<?php

namespace App\Models;

use App\Models\Concerns\HasCurrencyPrices;
use App\Models\Concerns\HasLibraryMedia;
use App\Services\InventoryService;
use App\Services\Media\MediaLibraryService;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasCurrencyPrices;
    use HasLibraryMedia;

    protected $attributes = ['inventory_mode' => 'untracked', 'stock_quantity' => 0, 'reserved_quantity' => 0, 'inventory_version' => 0];

    public function delete()
    {
        return DB::transaction(function () {
            app(PricingService::class)->lock(true);
            app(InventoryService::class)->lockProducts([$this->id]);

            $variantIds = $this->variants()->pluck('id');
            $optionIds = DB::table('product_option_values')->join('product_option_groups', 'product_option_groups.id', '=', 'product_option_values.product_option_group_id')->where('product_option_groups.product_id', $this->id)->pluck('product_option_values.id');
            $deleted = parent::delete();
            if ($deleted && app(PricingService::class)->installed()) {
                DB::table('currency_prices')->where('kind', 'variant')->whereIn('entity_id', $variantIds)->delete();
                DB::table('currency_prices')->where('kind', 'option')->whereIn('entity_id', $optionIds)->delete();
                DB::table('currency_prices')->where('kind', $this->priceKind())->where('entity_id', $this->id)->delete();
                app(PricingService::class)->bump();
            }

            return $deleted;
        });
    }

    protected $fillable = [
        'price_currency',
        'category_id', 'title', 'slug', 'sku', 'short_description', 'description',
        'size_chart', 'specifications',
        'meta_title', 'meta_description', 'meta_keywords',
        'ingredients', 'allergens', 'price', 'discount_price', 'weight', 'servings',
        'stock_quantity', 'in_stock', 'is_featured', 'is_bestseller', 'is_new_arrival',
        'inventory_mode',
        'is_active', 'main_image', 'sales_count', 'display_order',
        'plugin_attributes',
    ];

    protected function casts(): array
    {
        return [
            'plugin_attributes' => 'array',
            'size_chart' => 'array',
            'specifications' => 'array',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'in_stock' => 'boolean',
            'is_featured' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_new_arrival' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'stock_quantity' => 'integer', 'reserved_quantity' => 'integer', 'inventory_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->title);
            }
            if (empty($product->sku)) {
                $product->sku = 'SKU-'.strtoupper(Str::random(12));
            }
        });
        static::deleting(fn (Product $product) => app(InventoryService::class)->detachProduct($product));
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function occasions(): BelongsToMany
    {
        return $this->belongsToMany(Occasion::class, 'product_occasion');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function mainImageUsages(): MorphMany
    {
        return $this->morphMany(MediaUsage::class, 'mediable')->where('slot', 'catalog.product.main');
    }

    public function getMainImageAssetIdAttribute(): ?int
    {
        return $this->mainImageUsages()->value('media_asset_id');
    }

    public function getAvailableForSaleAttribute(): bool
    {
        if ($this->effective_price === null) {
            return false;
        }
        foreach ($this->optionGroups->where('is_active', true) as $group) {
            if ($group->values->where('is_active', true)->filter(fn ($value) => $value->price_adjustment !== null)->count() < $group->effectiveMinSelections()) {
                return false;
            }
        }

        return app(InventoryService::class)->available($this);
    }

    public function getMainImageSrcsetAttribute(): string
    {
        return $this->libraryImages('catalog.product.main', 'product-card')[0]['srcset'] ?? '';
    }

    public function getDetailImageAttribute(): array
    {
        return $this->libraryImages('catalog.product.main', 'product-detail')[0] ?? [
            'url' => $this->main_image_url, 'alt_text' => $this->main_image_alt, 'srcset' => '',
        ];
    }

    public function getMainImageAltAttribute(): string
    {
        $custom = $this->mainImageUsages()->with('asset')->first()?->asset?->alt_text;
        if (filled($custom)) {
            return $custom;
        }

        return $this->title . ' — Custom Leather Motorcycle Gear | RJ Motorbike';
    }

    public function getGalleryAssetIdsAttribute(): array
    {
        return $this->libraryAssetIds('catalog.product.gallery');
    }

    public function getGalleryImagesAttribute(): array
    {
        $library = $this->libraryImages('catalog.product.gallery', 'product-detail');

        return $library ?: $this->images->values()->map(fn ($image, $index) => [
            'url' => $image->image_url,
            'alt_text' => $this->title . ' — View ' . ($index + 1) . ' (Custom Leather Detail)',
        ])->all();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function hasRequiredOptions(): bool
    {
        return $this->optionGroups
            ->where('is_active', true)
            ->contains(fn (ProductOptionGroup $group) => $group->effectiveMinSelections() > 0);
    }

    /**
     * Whether product cards should open a size/options picker before add-to-cart.
     * True when there is more than one size OR any option group with more than one choice
     * (or any required option group).
     */
    public function needsCardConfiguration(): bool
    {
        $activeVariants = $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)
            : $this->variants()->where('is_active', true)->get();

        if ($activeVariants->count() > 1) {
            return true;
        }

        $groups = $this->relationLoaded('optionGroups')
            ? $this->optionGroups->where('is_active', true)
            : $this->optionGroups()->where('is_active', true)->with('values')->get();

        foreach ($groups as $group) {
            $activeValues = $group->relationLoaded('values')
                ? $group->values->where('is_active', true)
                : $group->values()->where('is_active', true)->get();

            if ($activeValues->count() > 1 || $group->effectiveMinSelections() > 0) {
                return true;
            }
        }

        return false;
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getEffectivePriceAttribute(): ?string
    {
        if (! request()->is('admin/*') && app(PricingService::class)->installed()) {
            $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->ordered()->get();
            $ordered = $variants->where('is_active', true)->sortBy(fn ($v) => [! $v->is_default, $v->sort_order, $v->id]);
            foreach ($ordered as $variant) {
                if ($variant->effective_price !== null) {
                    return $variant->effective_price;
                }
            }

            return null;
        }

        return $this->discount_price ?? $this->price;
    }

    public function getMainImageUrlAttribute(): string
    {
        // Resolve current usages even when the product itself came from a page cache.
        $asset = $this->mainImageUsages()->with('asset.media')->first()?->asset;
        if ($asset && $asset->status === 'active' && $asset->visibility === 'public' && $asset->getFirstMedia('original')) {
            return app(MediaLibraryService::class)->present($asset, 'product-card')['url'];
        }
        if (empty($this->main_image)) {
            return asset('images/placeholder.svg');
        }

        return $this->publicAssetUrl($this->main_image);
    }

    private function publicAssetUrl(string $path): string
    {
        if (str_starts_with($path, 'assets/') || str_starts_with($path, 'images/')) {
            $segments = array_map('rawurlencode', explode('/', $path));

            return asset(implode('/', $segments));
        }

        return asset('storage/'.$path);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->discount_price !== null && $this->price !== null && $this->discount_price < $this->price;
    }

    public function getDiscountPercentageAttribute(): int
    {
        if (! $this->is_on_sale) {
            return 0;
        }

        return (int) round((($this->price - $this->discount_price) / $this->price) * 100);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_featured', true);
    }

    public function scopeBestsellers(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_bestseller', true);
    }

    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_new_arrival', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('in_stock', true);
    }

    public function getPriceAttribute($value): ?string
    {
        if ($this->exists && ! request()->is('admin/*') && app(PricingService::class)->installed()) {
            $variant = $this->displayVariant();
            if ($variant) {
                return $variant->price;
            }
            $hasVariants = $this->relationLoaded('variants') ? $this->variants->isNotEmpty() : $this->variants()->exists();

            return $hasVariants ? null : $this->currencyDecimal('price', $value);
        }

        return $this->currencyDecimal('price', $value);
    }

    public function getDiscountPriceAttribute($value): ?string
    {
        if ($this->exists && ! request()->is('admin/*') && app(PricingService::class)->installed()) {
            return $this->displayVariant()?->discount_price;
        }

        return $this->currencyDecimal('discount_price', $value);
    }

    private function displayVariant(): ?ProductVariant
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->ordered()->get();

        return $variants->where('is_active', true)->sortBy(fn ($v) => [! $v->is_default, $v->sort_order, $v->id])
            ->first(fn ($v) => $v->effective_price !== null);
    }
}
