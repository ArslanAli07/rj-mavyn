<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'category' => 'nullable|string|max:255',
            'occasion' => 'nullable|string|max:255',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
        ]);
        $priced = app(PricingService::class)->catalogQuery(Product::active());
        $query = Product::query()->fromSub($priced, 'products')->with(['category', 'variants', 'optionGroups.values']);

        // Category filter: resolve slug → id first, then filter on FK (uses index)
        // instead of whereHas which generates a correlated subquery.
        if ($request->filled('category')) {
            $catId = Category::active()->where('slug', $request->category)->value('id');
            $query->where('category_id', $catId);
        }

        // Occasion filter: resolve slug → id, then join pivot table by ID.
        if ($request->filled('occasion')) {
            $occasionId = Occasion::active()->where('slug', $request->occasion)->value('id');
            $query->whereIn('id', DB::table('product_occasion')
                ->where('occasion_id', $occasionId)
                ->select('product_id'));
        }

        if ($request->filled('min_price')) {
            $query->where('catalog_price_minor', '>=', Money::minor($request->min_price));
        }

        if ($request->filled('max_price')) {
            $query->where('catalog_price_minor', '<=', Money::minor($request->max_price));
        }

        if ($request->boolean('in_stock')) {
            $query->where('in_stock', true);
        }

        if (in_array($request->input('sort'), ['price_asc', 'price_desc'], true)) {
            $query->orderByRaw('catalog_price_minor IS NULL')->orderBy('catalog_price_minor', $request->input('sort') === 'price_asc' ? 'asc' : 'desc');
        }

        // Match homepage order: categories by display_order, then products by display_order
        $query->orderByRaw('(SELECT display_order FROM categories WHERE categories.id = products.category_id)')
            ->orderBy('display_order')
            ->latest();

        $products = $query->paginate(config('site.per_page', 12))->withQueryString();

        // Sidebar data is static — cache it so it doesn't re-query on every filter change.
        $categories = Cache::remember('active_categories', 3600, fn () => Category::active()->orderBy('display_order')->get()
        );
        $occasions = Cache::remember('active_occasions', 3600, fn () => Occasion::active()->orderBy('display_order')->get()
        );

        return view('pages.shop', compact('products', 'categories', 'occasions'));
    }
}
