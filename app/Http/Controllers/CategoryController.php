<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(string $slug): View
    {
        $category = Category::active()->where('slug', $slug)->firstOrFail();
        $products = Product::active()
            ->where('category_id', $category->id)
            ->with(['category', 'variants', 'optionGroups.values'])
            ->orderBy('display_order')
            ->latest()
            ->paginate(config('site.per_page', 12));

        return view('pages.category', compact('category', 'products'));
    }
}
