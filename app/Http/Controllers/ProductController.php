<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Plugins\PluginRuntime;
use Illuminate\View\View;
use Mavyn\Contracts\Plugins\ProductDisplay;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        // Read current catalog state, including independently edited variants/options.
        $product = Product::active()
            ->with([
                'category',
                'images',
                'occasions',
                'variants' => fn ($q) => $q->ordered(),
                'optionGroups' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')
                    ->with(['values' => fn ($vq) => $vq->where('is_active', true)->orderBy('sort_order')->orderBy('id')]),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->select('id', 'title', 'slug', 'price', 'discount_price', 'main_image', 'category_id', 'is_active')
            ->with(['category', 'variants', 'optionGroups.values'])
            ->orderBy('display_order')
            ->latest()
            ->take(4)
            ->get();

        $wishlistedIds = auth('web')->check()
            ? auth('web')->user()->wishlistedProductIds()
            : [];

        $hooks = app(PluginRuntime::class)->hooks();
        $display = $hooks->apply('core.catalog.product.display', new ProductDisplay($product->id));
        if ($display->productId !== $product->id) {
            throw new \UnexpectedValueException('Display filter changed product identity.');
        }
        $hooks->dispatch('core.catalog.product.displayed', $display);

        return view('pages.product', compact('product', 'related', 'wishlistedIds', 'display'));
    }
}
