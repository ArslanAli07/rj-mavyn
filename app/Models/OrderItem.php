<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'weight',
        'variant_name',
        'options_snapshot',
        'customization',
        'special_request', 'attributes_snapshot',
        'product_title',
        'product_sku',
        'product_image', 'media_asset_id', 'media_snapshot',
        'unit_price',
        'quantity',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'attributes_snapshot' => 'array',            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'options_snapshot' => 'array', 'customization' => 'array', 'media_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getProductImageUrlAttribute(): string
    {
        if ($this->media_asset_id) {
            $asset = MediaAsset::with('media')->find($this->media_asset_id);
            if ($asset && $asset->status === 'active' && $asset->visibility === 'public') {
                return $asset->getFirstMedia('original')?->getUrl() ?? asset('images/placeholder.svg');
            }
        }
        if ($this->product_image && (str_starts_with($this->product_image, 'assets/') || str_starts_with($this->product_image, 'images/'))) {
            return asset(implode('/', array_map('rawurlencode', explode('/', $this->product_image))));
        }

        return $this->product_image
            ? asset('storage/'.$this->product_image)
            : asset('images/placeholder.svg');
    }

    public function getVariantLabelAttribute(): ?string
    {
        return $this->variant_name ?: $this->weight;
    }
}
