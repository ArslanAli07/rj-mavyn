<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::active()->with(['category', 'variants', 'optionGroups.values']);

        // Category filter: resolve slug → id first, then filter on FK (uses index)
        // instead of whereHas which generates a correlated subquery.
        if ($request->filled('category')) {
            $catId = Category::where('slug', $request->category)->value('id');
            if ($catId) {
                $query->where('category_id', $catId);
            }
        }

        // Occasion filter: resolve slug → id, then join pivot table by ID.
        if ($request->filled('occasion')) {
            $occasionId = Occasion::where('slug', $request->occasion)->value('id');
            if ($occasionId) {
                $query->whereIn('id', DB::table('product_occasion')
                    ->where('occasion_id', $occasionId)
                    ->pluck('product_id'));
            }
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->boolean('in_stock')) {
            $query->where('in_stock', true);
        }

        // Match homepage order: categories by display_order, then products by display_order
        $query->orderByRaw('(SELECT display_order FROM categories WHERE categories.id = products.category_id)')
            ->orderBy('display_order')
            ->latest();

        $products = $query->paginate(config('site.per_page', 12))->withQueryString();

        // Sidebar data is static — cache it so it doesn't re-query on every filter change.
        $categories = Cache::remember('active_categories', 3600, fn() =>
            Category::active()->orderBy('display_order')->get()
        );
        $occasions = Cache::remember('active_occasions', 3600, fn() =>
            Occasion::active()->orderBy('display_order')->get()
        );

        return view('pages.shop', compact('products', 'categories', 'occasions'));
    }
}
