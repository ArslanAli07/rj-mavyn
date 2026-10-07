<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'selection_type',
        'min_selections',
        'max_selections',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_selections' => 'integer',
            'max_selections' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isSingle(): bool
    {
        return $this->selection_type === 'single';
    }

    public function isMultiple(): bool
    {
        return $this->selection_type === 'multiple';
    }

    public function effectiveMinSelections(): int
    {
        if ($this->is_required) {
            return max(1, (int) $this->min_selections);
        }

        return (int) $this->min_selections;
    }

    public function effectiveMaxSelections(): ?int
    {
        if ($this->isSingle()) {
            return 1;
        }

        return $this->max_selections;
    }
}
