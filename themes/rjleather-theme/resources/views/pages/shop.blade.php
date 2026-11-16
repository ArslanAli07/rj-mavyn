@php
    $isShop = request()->routeIs('shop');
    $isSearch = request()->routeIs('search');
    $catalogTitle = $catalogTitle ?? 'The collection';
    $catalogDescription = $catalogDescription ?? 'Hand-cut leather for the road, the arena and everyday wear. Find something that is only yours.';
    $categories = ($categories ?? collect())->values();
    $tree = ($tree ?? collect())->values();
    $selectedCategory = $selectedCategory ?? null;
    $total = method_exists($products, 'total') ? $products->total() : $products->count();
    $hasFilters = $isShop && request()->hasAny(['category','min_price','max_price','in_stock','occasion']);

    /*
     * The catalogue is a real tree: a group owns its subcategories and those
     * own their own collections (MotoGP > Brands > Ducati). The storefront
     * shows the groups, then the branch that is currently open.
     */
    $byId = $categories->keyBy('id');
    $activeRoot = null;
    $branchParent = null;
    if ($selectedCategory) {
        $node = $selectedCategory;
        while ($node && $node->parent_id !== null) {
            $node = $byId->get($node->parent_id);
        }
        $activeRoot = $node;
        $branchParent = $selectedCategory->parent_id ? ($byId->get($selectedCategory->parent_id) ?? $selectedCategory) : $selectedCategory;
    }
    $branch = $branchParent ? $categories->where('parent_id', $branchParent->id)->values() : collect();
    $flatten = function ($nodes, $depth = 0) use (&$flatten) {
        $flat = [];
        foreach ($nodes as $node) {
            $flat[] = ['label' => $node->name, 'slug' => $node->slug, 'depth' => $depth];
            $flat = [...$flat, ...$flatten($node->children ?? collect(), $depth + 1)];
        }
        return $flat;
    };
    $flatOptions = $flatten($tree);
    $currentSort = (string) request('sort', 'featured');
@endphp
<x-app-layout :title="$catalogTitle.' — '.setting('site_name', config('site.name'))">
<div class="rj-catalog">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span>
            @if($isShop)
                <span aria-current="page">Collection</span>
            @elseif($isSearch)
                <span aria-current="page">Search</span>
            @else
                @foreach(($trail ?? collect([$selectedCategory]))->filter() as $step)
                    @php $stepName = preg_replace('/^for\s+/i', '', $step->name); @endphp
                    @if($loop->last)<span aria-current="page">{{ $stepName }}</span>@else<a href="{{ route('category.show', $step->slug) }}">{{ $stepName }}</a><span>/</span>@endif
                @endforeach
            @endif
        </nav>
        <header class="rj-catalog__head">
            <div class="rj-catalog__head-copy">
                <h1 class="rj-catalog__title">{{ $catalogTitle }} <span class="rj-catalog__count">({{ $total }})</span></h1>
                <p class="rj-catalog__lede">{{ $catalogDescription }}</p>
            </div>
            @if($isSearch)
                <form action="{{ route('search') }}" class="rj-catalog__search" role="search">
                    <label class="sr-only" for="catalog-search">Search collection</label>
                    <input id="catalog-search" name="q" value="{{ $q ?? '' }}" placeholder="Search our collection" maxlength="255">
                    <button type="submit" class="rj-button rj-button--outline rj-button--sm">Search</button>
                </form>
            @endif
        </header>
    </div>

    <div class="rj-container">
        <div class="rj-catalog__layout">
        {{-- The sidebar belongs to the section: the heading links the section,
             the rows are exactly that section's own categories, and the page the
             shopper is on (or the branch it sits under) stays highlighted. --}}
        <aside class="rj-catalog__rail">
            <div class="rj-rail">
                @if($selectedCategory && $tree->isNotEmpty())
                    @php
                        $railRoot = $activeRoot ?? $selectedCategory;
                        $railRootNode = $tree->firstWhere('id', $railRoot->id);
                        $railGroups = $railRootNode && $railRootNode->children->isNotEmpty() ? $railRootNode->children : collect();
                        $railRootClean = preg_replace('/^for\s+/i', '', $railRoot->name);
                    @endphp
                    <h2 class="rj-rail__title"><a href="{{ route('category.show', $railRoot->slug) }}?all=1">{{ $railRootClean }}</a></h2>
                    @php
                        // Browsing the group's full view (?all=1): no subcategory is
                        // picked, so none is highlighted — the grid shows everything.
                        $browsingRailRoot = $selectedCategory->id === $railRoot->id && !request()->boolean('all');
                        $railPreselected = $browsingRailRoot ? $railGroups->first() : null;
                    @endphp
                    <nav class="rj-rail__nav" aria-label="{{ $railRootClean }} categories">
                        @foreach($railGroups as $group)
                            <a href="{{ route('category.show', $group->slug) }}" @class(['rj-rail__link', 'is-active' => ($selectedCategory->id === $group->id || $selectedCategory->parent_id === $group->id || ($railPreselected && $group->id === $railPreselected->id))])>{{ preg_replace('/^for\s+/i', '', $group->name) }}</a>
                        @endforeach
                    </nav>
                @else
                    <h2 class="rj-rail__title">Collections</h2>
                    <nav class="rj-rail__nav" aria-label="Collection categories">
                        <a href="{{ route('shop') }}" @class(['rj-rail__link', 'is-active' => $isShop && !$selectedCategory && !request('category')])>All products</a>
                        @foreach($tree as $group)
                            <a href="{{ route('category.show', $group->slug) }}" @class(['rj-rail__link', 'is-active' => $activeRoot && $activeRoot->id === $group->id])>{{ preg_replace('/^for\s+/i', '', $group->name) }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </aside>
        <div class="rj-catalog__main">
        <div class="rj-catalog__toolbar">
            <span class="rj-catalog__meta">{{ $total }} {{ $total === 1 ? 'Product' : 'Products' }}</span>
            <div class="rj-catalog__tools">
                @php
                    // Facets come from the controller; a label only appears on a
                    // section that actually carries it, and the gender facet
                    // only appears where both lines exist.
                    $colorChoices = ($colors ?? collect());
                    $genderChoices = collect(['men', 'women'])->intersect($genders ?? collect())->values();
                    $unisexPresent = ($genders ?? collect())->contains('unisex');
                    $showGender = $genderChoices->count() > 1 || ($genderChoices->count() === 1 && $unisexPresent);
                @endphp

                <form method="GET" action="{{ request()->url() }}" class="rj-filter-toolbar">
                    @foreach(request()->except(['sort', 'color', 'gender', 'page']) as $key => $value)
                        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach

                    <div class="rj-filter-toolbar__group">
                        {{-- Custom Sort Dropdown --}}
                        @php
                            $sortLabels = [
                                'featured' => 'Recommended',
                                'price-low' => 'Price: Low to High',
                                'price-high' => 'Price: High to Low',
                                'new' => 'Newest',
                            ];
                            $currentSortLabel = $sortLabels[$currentSort] ?? 'Recommended';
                        @endphp
                        <div class="rj-filter-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                            <input type="hidden" name="sort" value="{{ $currentSort }}" x-ref="sortInput">
                            <button type="button" class="rj-filter-pill" @click="open = !open" :aria-expanded="open">
                                <span class="rj-filter-pill__label">Sort:</span>
                                <span class="rj-filter-pill__value">{{ $currentSortLabel }}</span>
                                <svg class="icon icon-chevron" :class="{ 'is-flipped': open }" aria-hidden="true"><use href="#icon-chevron"/></svg>
                            </button>
                            <div class="rj-filter-dropdown__menu" x-show="open" x-cloak x-transition.opacity.duration.150ms>
                                @foreach($sortLabels as $value => $label)
                                    <button type="button" class="rj-filter-dropdown__item @if($currentSort === $value) is-active @endif" @click="$refs.sortInput.value = '{{ $value }}'; $refs.sortInput.form.submit()">
                                        <span>{{ $label }}</span>
                                        @if($currentSort === $value)<svg class="icon icon-check" aria-hidden="true"><use href="#icon-check"/></svg>@endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Custom Colour Dropdown --}}
                        @if($colorChoices->isNotEmpty())
                            @php
                                $selectedColor = request('color');
                                $selectedColorLabel = $selectedColor ? Illuminate\Support\Str::headline($selectedColor) : 'All Colours';
                            @endphp
                            <div class="rj-filter-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                <input type="hidden" name="color" value="{{ $selectedColor }}" x-ref="colorInput">
                                <button type="button" class="rj-filter-pill @if($selectedColor) is-filtered @endif" @click="open = !open" :aria-expanded="open">
                                    <span class="rj-filter-pill__label">Colour:</span>
                                    <span class="rj-filter-pill__value">{{ $selectedColorLabel }}</span>
                                    <svg class="icon icon-chevron" :class="{ 'is-flipped': open }" aria-hidden="true"><use href="#icon-chevron"/></svg>
                                </button>
                                <div class="rj-filter-dropdown__menu" x-show="open" x-cloak x-transition.opacity.duration.150ms>
                                    <button type="button" class="rj-filter-dropdown__item @if(!$selectedColor) is-active @endif" @click="$refs.colorInput.value = ''; $refs.colorInput.form.submit()">
                                        <span>All Colours</span>
                                        @if(!$selectedColor)<svg class="icon icon-check" aria-hidden="true"><use href="#icon-check"/></svg>@endif
                                    </button>
                                    @foreach($colorChoices as $choice)
                                        <button type="button" class="rj-filter-dropdown__item @if($selectedColor === $choice) is-active @endif" @click="$refs.colorInput.value = '{{ $choice }}'; $refs.colorInput.form.submit()">
                                            <span>{{ Illuminate\Support\Str::headline($choice) }}</span>
                                            @if($selectedColor === $choice)<svg class="icon icon-check" aria-hidden="true"><use href="#icon-check"/></svg>@endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @elseif(request('color'))
                            <input type="hidden" name="color" value="{{ request('color') }}">
                        @endif

                        {{-- Custom Gender Dropdown --}}
                        @if($showGender)
                            @php
                                $selectedGender = request('gender');
                                $selectedGenderLabel = $selectedGender ? ucfirst($selectedGender) : 'All Genders';
                            @endphp
                            <div class="rj-filter-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                <input type="hidden" name="gender" value="{{ $selectedGender }}" x-ref="genderInput">
                                <button type="button" class="rj-filter-pill @if($selectedGender) is-filtered @endif" @click="open = !open" :aria-expanded="open">
                                    <span class="rj-filter-pill__label">Gender:</span>
                                    <span class="rj-filter-pill__value">{{ $selectedGenderLabel }}</span>
                                    <svg class="icon icon-chevron" :class="{ 'is-flipped': open }" aria-hidden="true"><use href="#icon-chevron"/></svg>
                                </button>
                                <div class="rj-filter-dropdown__menu" x-show="open" x-cloak x-transition.opacity.duration.150ms>
                                    <button type="button" class="rj-filter-dropdown__item @if(!$selectedGender) is-active @endif" @click="$refs.genderInput.value = ''; $refs.genderInput.form.submit()">
                                        <span>All Genders</span>
                                        @if(!$selectedGender)<svg class="icon icon-check" aria-hidden="true"><use href="#icon-check"/></svg>@endif
                                    </button>
                                    @foreach($genderChoices as $choice)
                                        <button type="button" class="rj-filter-dropdown__item @if($selectedGender === $choice) is-active @endif" @click="$refs.genderInput.value = '{{ $choice }}'; $refs.genderInput.form.submit()">
                                            <span>{{ ucfirst($choice) }}</span>
                                            @if($selectedGender === $choice)<svg class="icon icon-check" aria-hidden="true"><use href="#icon-check"/></svg>@endif
                                        </button>
                                    @endforeach
                                    @if($unisexPresent)
                                        <button type="button" class="rj-filter-dropdown__item @if($selectedGender === 'unisex') is-active @endif" @click="$refs.genderInput.value = 'unisex'; $refs.genderInput.form.submit()">
                                            <span>Unisex</span>
                                            @if($selectedGender === 'unisex')<svg class="icon icon-check" aria-hidden="true"><use href="#icon-check"/></svg>@endif
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @elseif(request('gender'))
                            <input type="hidden" name="gender" value="{{ request('gender') }}">
                        @endif
                    </div>
                </form>

                @if($isShop)
                    <button type="button" class="rj-button rj-button--outline rj-button--sm rj-filter-dialog-btn" data-dialog="filter-dialog">
                        <span>More Filters</span>
                        <svg class="icon"><use href="#icon-filter"/></svg>
                    </button>
                @endif
            </div>
        </div>

        @php
            // Applied facet chips read the same on a section page as in the shop.
            $hasFilters = $hasFilters || (request()->filled('color') || request()->filled('gender'));
        @endphp
        @if($hasFilters)
            <div class="rj-catalog__applied">
                <span>Filters applied</span>
                @if($selectedCategory)<span>{{ preg_replace('/^for\s+/i', '', $selectedCategory->name) }}</span>@endif
                @if(request('color'))<span>Colour: {{ Illuminate\Support\Str::headline(request('color')) }}</span>@endif
                @if(request('gender'))<span>Gender: {{ ucfirst(request('gender')) }}</span>@endif
                <a href="{{ $selectedCategory ? route('category.show', $selectedCategory->slug) : route('shop') }}">Clear filters</a>
            </div>
        @endif

        <div class="rj-grid rj-catalog__grid">
            @foreach($products as $product)<x-product-card :product="$product" />@endforeach
        </div>

        @if($products->isEmpty())
            <div class="rj-empty">
                <h2 class="rj-title">{{ $isSearch && strlen($q ?? '') < 2 ? 'Find your next favourite.' : 'No products found.' }}</h2>
                <p>{{ $isSearch && strlen($q ?? '') < 2 ? 'Enter at least two characters to search the collection.' : 'Try another search or explore the full collection.' }}</p>
                <a class="rj-button rj-button--outline" href="{{ route('shop') }}">View the collection <svg class="icon"><use href="#icon-arrow"/></svg></a>
            </div>
        @endif

        @if(method_exists($products, 'hasPages') && $products->hasPages())
            @php
                $currentPage = $products->currentPage();
                $lastPage = $products->lastPage();
                $window = 2;
                $first = max(1, $currentPage - $window);
                $last = min($lastPage, $currentPage + $window);
            @endphp
            <nav class="rj-pagination" aria-label="Pagination">
                <a class="@disabled($currentPage === 1)" href="{{ $products->previousPageUrl() }}" rel="prev">Previous</a>
                @if($first > 1)
                    <a href="{{ $products->url(1) }}">1</a>
                    @if($first > 2)<span aria-hidden="true">&hellip;</span>@endif
                @endif
                @for($page = $first; $page <= $last; $page++)
                    <a class="@if($page === $currentPage) is-current @endif" href="{{ $products->url($page) }}" @if($page === $currentPage) aria-current="page" @endif>{{ $page }}</a>
                @endfor
                @if($last < $lastPage)
                    @if($last < $lastPage - 1)<span aria-hidden="true">&hellip;</span>@endif
                    <a href="{{ $products->url($lastPage) }}">{{ $lastPage }}</a>
                @endif
                <a class="@disabled($currentPage === $lastPage)" href="{{ $products->nextPageUrl() }}" rel="next">Next</a>
            </nav>
        @endif

        @if($products->isNotEmpty())
            <div class="rj-catalog__end">
                <span>Showing {{ $products->count() }} of {{ $total }} products.</span>
                <button type="button" class="rj-textlink" data-back-to-top>Back to top <svg class="icon icon-sm"><use href="#icon-arrow"/></svg></button>
            </div>
        @endif
        </div>
        </div>
    </div>

    {{-- Editorial band and the short answers shoppers look for before they buy. --}}
    <section class="rj-section rj-catalog__editorial">
        <div class="rj-container">
            <div class="rj-split">
                <span class="rj-split__media">
                    <span class="rj-media-frame" data-ratio="4/3"><span>Leather workshop</span></span>
                </span>
                <div class="rj-split__copy">
                    <span class="rj-eyebrow">Care &amp; craft</span>
                    <h2 class="rj-display rj-title">Made to last.<br>Made to be yours.</h2>
                    <p class="rj-lede">Full-grain hides, hand-cut panels and a finish that improves with wear. See how every piece is made.</p>
                    <div><a class="rj-button rj-button--outline" href="{{ route('about') }}">Our story <svg class="icon"><use href="#icon-arrow"/></svg></a></div>
                </div>
            </div>
        </div>
    </section>

    <section class="rj-section rj-section--tight">
        <div class="rj-container">
            <div class="rj-section-head rj-section-head--center">
                <div class="rj-section-head__copy">
                    <span class="rj-eyebrow">Good to know</span>
                    <h2 class="rj-display rj-title">A little help finding your favourite</h2>
                </div>
            </div>
            <div class="rj-accordion">
                <details>
                    <summary>Need help choosing?<svg class="icon"><use href="#icon-plus"/></svg></summary>
                    <p>Explore each product's available options and details, or <a href="{{ route('contact') }}">contact us</a> for help.</p>
                </details>
                <details>
                    <summary>Shipping information<svg class="icon"><use href="#icon-plus"/></svg></summary>
                    <p>Read our <a href="{{ route('shipping') }}">shipping policy</a> for delivery information.</p>
                </details>
                <details>
                    <summary>Returns and exchanges<svg class="icon"><use href="#icon-plus"/></svg></summary>
                    <p>See our <a href="{{ route('returns') }}">returns policy</a> for eligibility and instructions.</p>
                </details>
            </div>
        </div>
    </section>
</div>

@if($isShop)
<dialog id="filter-dialog" class="dialog filter-dialog" aria-labelledby="filter-title">
    <div class="dialog__panel">
        <button class="dialog-close" aria-label="Close filters"><svg class="icon"><use href="#icon-close"/></svg></button>
        <h2 id="filter-title">Filter the collection</h2>
        <form action="{{ route('shop') }}" class="rj-filter-form">
            @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
            @if(request('color'))<input type="hidden" name="color" value="{{ request('color') }}">@endif
            @if(request('gender'))<input type="hidden" name="gender" value="{{ request('gender') }}">@endif
            <label>Category
                <select name="category">
                    <option value="">All products</option>
                    @foreach($flatOptions as $option)
                        <option value="{{ $option['slug'] }}" @selected(request('category') === $option['slug'])>{{ str_repeat('— ', $option['depth']) }}{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            @if(($occasions ?? collect())->isNotEmpty())
                <label>Collection
                    <select name="occasion">
                        <option value="">All collections</option>
                        @foreach($occasions as $occasion)<option value="{{ $occasion->slug }}" @selected(request('occasion') === $occasion->slug)>{{ $occasion->name }}</option>@endforeach
                    </select>
                </label>
            @endif
            <label>Minimum price<input name="min_price" type="number" min="0" step="0.01" value="{{ request('min_price') }}"></label>
            <label>Maximum price<input name="max_price" type="number" min="0" step="0.01" value="{{ request('max_price') }}"></label>
            <label class="rj-checkbox"><input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))> In stock only</label>
            <button type="submit" class="rj-button">Apply filters <svg class="icon"><use href="#icon-arrow"/></svg></button>
            <a class="rj-textlink" href="{{ route('shop') }}">Reset filters</a>
        </form>
    </div>
</dialog>
@endif
</x-app-layout>
