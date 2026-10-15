<?php

namespace App\Models;

use App\Models\Concerns\HasCurrencyPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    use HasCurrencyPrices;

    protected $fillable = [
        'price_currency',
        'name', 'delivery_fee', 'is_active', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getDeliveryFeeAttribute($value): ?string
    {
        return $this->currencyDecimal('delivery_fee', $value);
    }
}
