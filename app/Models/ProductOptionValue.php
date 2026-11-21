<?php

namespace App\Models;

use App\Models\Concerns\HasCurrencyPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOptionValue extends Model
{
    use HasCurrencyPrices;

    protected $fillable = [
        'price_currency',
        'product_option_group_id',
        'name',
        'price_adjustment',
        'is_active',
        'sort_order',
        'swatch',
        'input_label',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getPriceAdjustmentAttribute($value): ?string
    {
        return $this->currencyDecimal('price_adjustment', $value);
    }
}
