<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use App\Services\Catalog\CategoryTree;
use App\Services\Catalog\ProductTitleAttributes;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private readonly CategoryTree $tree) {}

    public function index(Request $request): View
    {
        $request->validate([
            'category' => 'nullable|string|max:255',
            'occasion' => 'nullable|string|max:255',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'sort' => 'nullable|string|max:40',
            'color' => 'nullable|string|max:40',
            'gender' => 'nullable|string|max:40',
        ]);
        $priced = app(PricingService::class)->catalogQuery(Product::active());
        $query = Product::query()->fromSub($priced, 'products')->with(['category', 'variants', 'optionGroups.values']);

        $categories = $this->tree->flat();

        // Category filter: resolve slug → id first, then filter on FK (uses index)
        // instead of whereHas which generates a correlated subquery. Choosing a
        // group also shows everything filed underneath it.
        $selectedCategory = null;
        if ($request->filled('category')) {
            $selectedCategory = $categories->firstWhere('slug', $request->category);
            $query->whereIn('category_id', $selectedCategory ? $selectedCategory->descendantIds() : [0]);
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

        // Colour and gender facets come from the products' own titles; options
        // are read across the un-filtered catalogue, matching how the shop page
        // opens before a section is chosen.
        [$colors, $genders] = $this->facets();
        ProductTitleAttributes::apply($query, $this->validatedColor($colors), $this->validatedGender($genders));

        $this->applySort($query, (string) $request->input('sort', ''));

        $products = $query->paginate(config('site.per_page', 12))->withQueryString();

        // Sidebar data is static — cache it so it doesn't re-query on every filter change.
        $occasions = Cache::remember('active_occasions', 3600, fn () => Occasion::active()->orderBy('display_order')->get()
        );

        $tree = $this->tree->tree();

        return view('pages.shop', compact(
            'products', 'categories', 'occasions', 'tree', 'selectedCategory', 'colors', 'genders'
        ));
    }

    /** Colour + gender choices across the active catalogue, from titles. */
    private function facets(): array
    {
        $titles = Product::active()->get('title')->pluck('title');

        return [ProductTitleAttributes::colors($titles), ProductTitleAttributes::genders($titles)];
    }

    /** Colour requested by the shopper, only if the store carries it. */
    private function validatedColor($colors): ?string
    {
        $color = trim((string) request('color'));

        return $color !== '' && ($colors?->contains($color) ?? false) ? $color : null;
    }

    /** Gender requested by the shopper, only if the store carries it. */
    private function validatedGender($genders): ?string
    {
        $gender = trim((string) request('gender'));

        return $gender !== '' && ($genders?->contains($gender) ?? false) ? $gender : null;
    }

    /**
     * Order the catalogue the way the storefront's sort control asks for.
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price-low', 'price_asc' => $query->orderByRaw('catalog_price_minor IS NULL')->orderBy('catalog_price_minor', 'asc'),
            'price-high', 'price_desc' => $query->orderByRaw('catalog_price_minor IS NULL')->orderBy('catalog_price_minor', 'desc'),
            'new' => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
            default => $query->orderByRaw('(SELECT display_order FROM categories WHERE categories.id = products.category_id)')
                ->orderBy('display_order')
                ->latest(),
        };
    }
}
