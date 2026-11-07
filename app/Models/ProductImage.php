<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'image', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (empty($this->image)) {
            return asset('images/placeholder.svg');
        }

        // Images published with the document root are addressed directly; the
        // rest live on the public storage disk.
        if (str_starts_with($this->image, 'assets/') || str_starts_with($this->image, 'images/')) {
            $segments = array_map('rawurlencode', explode('/', $this->image));

            return asset(implode('/', $segments));
        }

        return asset('storage/' . $this->image);
    }
}
