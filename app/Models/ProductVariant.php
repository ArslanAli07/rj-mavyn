<?php

namespace App\Models;

use App\Models\Concerns\HasCurrencyPrices;
use App\Services\InventoryService;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProductVariant extends Model
{
    use HasCurrencyPrices;

    protected $attributes = ['track_inventory' => false, 'in_stock' => true, 'stock_quantity' => 0, 'reserved_quantity' => 0, 'inventory_version' => 0];

    public function delete()
    {
        return DB::transaction(function () {
            app(PricingService::class)->lock(true);
            app(InventoryService::class)->lockProducts([$this->product_id]);

            $deleted = parent::delete();
            if ($deleted && app(PricingService::class)->installed()) {
                DB::table('currency_prices')->where('kind', $this->priceKind())->where('entity_id', $this->id)->delete();
                app(PricingService::class)->bump();
            }

            return $deleted;
        });
    }

    protected $fillable = [
        'price_currency',
        'product_id',
        'name',
        'weight',
        'price',
        'discount_price',
        'servings',
        'plugin_attributes',
        'sort_order',
        'is_default',
        'is_active',
        'track_inventory', 'in_stock', 'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'plugin_attributes' => 'array',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean', 'in_stock' => 'boolean', 'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer', 'inventory_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (ProductVariant $variant) => app(InventoryService::class)->detachVariant($variant));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->weight ?: 'Variant');
    }

    public function getEffectivePriceAttribute(): ?string
    {
        return $this->discount_price ?? $this->price;
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

    public function getPriceAttribute($value): ?string
    {
        return $this->currencyDecimal('price', $value);
    }

    public function getDiscountPriceAttribute($value): ?string
    {
        return $this->currencyDecimal('discount_price', $value);
    }
}
