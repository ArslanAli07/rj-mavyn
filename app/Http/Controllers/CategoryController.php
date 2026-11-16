<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CategoryTree;
use App\Services\Catalog\ProductTitleAttributes;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryTree $tree) {}

    public function show(string $slug): View|RedirectResponse
    {
        $category = Category::active()->where('slug', $slug)->firstOrFail();

        // A group with subcategories lands on its leading (best) one — the
        // same page as clicking that subcategory in the rail, so the grid
        // always matches the highlighted entry. `?all=1` keeps the
        // whole-group view reachable from the rail title.
        if (! request()->boolean('all') && $category->parent_id === null) {
            $firstChild = $this->tree->branch($category)->first();
            if ($firstChild) {
                $target = route('category.show', $firstChild->slug);
                $query = collect(request()->query())->except(['all', 'page'])->all();

                return redirect()->to($query ? $target.'?'.http_build_query($query) : $target, 302);
            }
        }

        // A group shows everything filed underneath it, not only its own products.
        $query = Product::active()
            ->whereIn('category_id', $category->descendantIds())
            ->with(['category', 'variants', 'optionGroups.values'])
            ->orderBy('display_order')
            ->latest();

        // Colour and gender facets are read from the products' own titles over
        // the whole branch, so a filter never hides a label the shopper could
        // still want to undo.
        [$colors, $genders] = $this->facets($query);
        ProductTitleAttributes::apply($query, $this->validatedColor($colors), $this->validatedGender($genders));

        $products = $query->paginate(config('site.per_page', 12))->withQueryString();

        $children = $this->tree->branch($category);
        $trail = $this->tree->trail($category);
        $categories = $this->tree->flat();
        $tree = $this->tree->tree();

        return view('pages.category', compact(
            'category', 'products', 'children', 'trail', 'categories', 'tree', 'colors', 'genders'
        ) + [
            'selectedCategory' => $category,
        ]);
    }

    /** Colour + gender choices the current category actually carries. */
    private function facets(EloquentBuilder $query): array
    {
        // Clone first: toBase() hands back the live underlying query, so the
        // title-only select must not leak into the paged product query.
        $titles = (clone $query)->toBase()->get(['title'])->pluck('title');

        return [ProductTitleAttributes::colors($titles), ProductTitleAttributes::genders($titles)];
    }

    /** Colour requested by the shopper, only if the category carries it. */
    private function validatedColor(?Collection $colors): ?string
    {
        $color = trim((string) request('color'));

        return $color !== '' && ($colors?->contains($color) ?? false) ? $color : null;
    }

    /** Gender requested by the shopper, only if the category carries it. */
    private function validatedGender(?Collection $genders): ?string
    {
        $gender = trim((string) request('gender'));

        return $gender !== '' && ($genders?->contains($gender) ?? false) ? $gender : null;
    }
}
