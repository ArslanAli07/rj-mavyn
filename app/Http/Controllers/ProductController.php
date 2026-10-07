<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        // Cache the full product for 10 minutes — cache is busted by admin on update/delete.
        $product = Cache::remember("product_{$slug}", 600, fn () =>
            Product::active()
                ->with([
                    'category',
                    'images',
                    'occasions',
                    'variants' => fn ($q) => $q->ordered(),
                    'optionGroups' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')
                        ->with(['values' => fn ($vq) => $vq->where('is_active', true)->orderBy('sort_order')->orderBy('id')]),
                ])
                ->where('slug', $slug)
                ->firstOrFail()
        );

        // Cache related products for the same TTL.
        $related = Cache::remember("related_{$product->category_id}_{$product->id}", 600, fn() =>
            Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->select('id', 'title', 'slug', 'price', 'discount_price', 'main_image', 'category_id', 'is_active')
                ->with(['category', 'variants', 'optionGroups.values'])
                ->orderBy('display_order')
                ->latest()
                ->take(4)
                ->get()
        );

        $wishlistedIds = auth()->check()
            ? auth()->user()->wishlistedProductIds()
            : [];

        return view('pages.product', compact('product', 'related', 'wishlistedIds'));
    }
}
