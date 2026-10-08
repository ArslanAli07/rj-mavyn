<x-app-layout title="Shop Catalog — {{ setting('site_name', config('site.name', 'Store')) }}" page-key="shop">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['Shop' => route('shop')]" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16" x-data="{ mobileFiltersOpen: false }">
        
        {{-- Catalog Title --}}
        <div class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Our Catalog</h1>
            <p class="text-sm text-espresso-500 mt-1">Explore our collection and find your next favorite.</p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            {{-- Desktop Filters Sidebar (Hidden on mobile) --}}
            <aside class="hidden lg:block w-64 bg-white border border-cream-150 rounded-2xl p-6 shrink-0 sticky top-24">
                <form action="{{ route('shop') }}" method="GET" class="space-y-6">
                    {{-- Categories --}}
                    <div>
                        <h3 class="font-heading font-bold text-espresso-800 text-sm mb-3">Categories</h3>
                        <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-hide pr-1">
                            <label class="flex items-center gap-2 text-xs font-medium text-espresso-750 cursor-pointer">
                                <input type="radio" name="category" value="" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300" 
                                       {{ !request()->filled('category') ? 'checked' : '' }} onchange="this.form.submit()">
                                <span>All Categories</span>
                            </label>
                            @foreach($categories as $cat)
                                <label class="flex items-center gap-2 text-xs font-medium text-espresso-700 cursor-pointer">
                                    <input type="radio" name="category" value="{{ $cat->slug }}" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300"
                                           {{ request('category') === $cat->slug ? 'checked' : '' }} onchange="this.form.submit()">
                                    <span>{{ $cat->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <hr class="border-cream-100">

                    {{-- Occasions --}}
                    <div>
                        <h3 class="font-heading font-bold text-espresso-800 text-sm mb-3">Occasions</h3>
                        <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-hide pr-1">
                            <label class="flex items-center gap-2 text-xs font-medium text-espresso-750 cursor-pointer">
                                <input type="radio" name="occasion" value="" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300" 
                                       {{ !request()->filled('occasion') ? 'checked' : '' }} onchange="this.form.submit()">
                                <span>All Occasions</span>
                            </label>
                            @foreach($occasions as $occ)
                                <label class="flex items-center gap-2 text-xs font-medium text-espresso-700 cursor-pointer">
                                    <input type="radio" name="occasion" value="{{ $occ->slug }}" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300"
                                           {{ request('occasion') === $occ->slug ? 'checked' : '' }} onchange="this.form.submit()">
                                    <span>{{ $occ->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <hr class="border-cream-100">

                    {{-- Price range --}}
                    <div>
                        <h3 class="font-heading font-bold text-espresso-800 text-sm mb-3">Price Range (PKR)</h3>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" 
                                   class="w-full px-2.5 py-1.5 border border-cream-200 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400">
                            <span class="text-espresso-400 text-xs">-</span>
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" 
                                   class="w-full px-2.5 py-1.5 border border-cream-200 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400">
                        </div>
                    </div>

                    <hr class="border-cream-100">

                    {{-- Stock --}}
                    <div>
                        <label class="flex items-center gap-2 text-xs font-medium text-espresso-700 cursor-pointer">
                            <input type="checkbox" name="in_stock" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300"
                                   {{ request('in_stock') ? 'checked' : '' }} onchange="this.form.submit()">
                            <span>In Stock Only</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-gold w-full text-xs py-2.5">Apply Filters</button>
                    @if(request()->anyFilled(['category', 'occasion', 'min_price', 'max_price', 'in_stock']))
                        <a href="{{ route('shop') }}" class="btn-secondary w-full justify-center text-xs py-2.5 block text-center">Clear Filters</a>
                    @endif
                </form>
            </aside>

            {{-- Products Area --}}
            <div class="flex-1 w-full">
                {{-- Toolbar --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-4 mb-6 flex items-center justify-between gap-4">
                    <button @click="mobileFiltersOpen = true" class="lg:hidden btn-secondary py-2 px-4 text-xs flex items-center gap-1.5 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        Filters
                    </button>
                    
                    <span class="text-xs text-espresso-500 sm:inline-block">Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} results</span>
                </div>

                {{-- Product Grid --}}
                @if($products->isEmpty())
                    <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center">
                        <div class="text-5xl mb-4">🎂</div>
                        <h2 class="font-heading text-xl font-bold text-espresso-800">No products found</h2>
                        <p class="text-sm text-espresso-500 mt-2 mb-6">Try adjusting your filters or search terms.</p>
                        <a href="{{ route('shop') }}" class="btn-primary">Clear all filters</a>
                    </div>
                @else
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-6">
                        @foreach($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    {{ $products->links('components.pagination') }}
                @endif
            </div>

        </div>

        {{-- Mobile Filters Slide-over (Hidden on desktop) --}}
        <div x-show="mobileFiltersOpen" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
            <div x-show="mobileFiltersOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-espresso-800/50 backdrop-blur-xs"></div>

            <div class="fixed inset-0 flex z-50">
                <div x-show="mobileFiltersOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" 
                     class="relative max-w-xs w-full bg-white shadow-xl flex flex-col py-4 pb-12 overflow-y-auto" @click.outside="mobileFiltersOpen = false">
                    
                    <div class="px-4 flex items-center justify-between border-b border-cream-100 pb-4">
                        <h2 class="text-lg font-heading font-bold text-espresso-800">Filters</h2>
                        <button type="button" class="btn-ghost p-1" @click="mobileFiltersOpen = false">
                            <span class="sr-only">Close catalog</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Filters Form Mobile --}}
                    <form action="{{ route('shop') }}" method="GET" class="mt-4 px-4 space-y-6">
                        {{-- Categories --}}
                        <div>
                            <h3 class="font-heading font-bold text-espresso-800 text-sm mb-3">Categories</h3>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs font-medium text-espresso-750 cursor-pointer">
                                    <input type="radio" name="category" value="" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300" 
                                           {{ !request()->filled('category') ? 'checked' : '' }} onchange="this.form.submit()">
                                    <span>All Categories</span>
                                </label>
                                @foreach($categories as $cat)
                                    <label class="flex items-center gap-2 text-xs font-medium text-espresso-700 cursor-pointer">
                                        <input type="radio" name="category" value="{{ $cat->slug }}" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300"
                                               {{ request('category') === $cat->slug ? 'checked' : '' }} onchange="this.form.submit()">
                                        <span>{{ $cat->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <hr class="border-cream-100">

                        {{-- Occasions --}}
                        <div>
                            <h3 class="font-heading font-bold text-espresso-800 text-sm mb-3">Occasions</h3>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs font-medium text-espresso-750 cursor-pointer">
                                    <input type="radio" name="occasion" value="" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300" 
                                           {{ !request()->filled('occasion') ? 'checked' : '' }} onchange="this.form.submit()">
                                    <span>All Occasions</span>
                                </label>
                                @foreach($occasions as $occ)
                                    <label class="flex items-center gap-2 text-xs font-medium text-espresso-700 cursor-pointer">
                                        <input type="radio" name="occasion" value="{{ $occ->slug }}" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300"
                                               {{ request('occasion') === $occ->slug ? 'checked' : '' }} onchange="this.form.submit()">
                                        <span>{{ $occ->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <hr class="border-cream-100">

                        {{-- Price range --}}
                        <div>
                            <h3 class="font-heading font-bold text-espresso-800 text-sm mb-3">Price Range (PKR)</h3>
                            <div class="flex items-center gap-2">
                                <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" 
                                       class="w-full px-2.5 py-1.5 border border-cream-200 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400">
                                <span class="text-espresso-400 text-xs">-</span>
                                <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" 
                                       class="w-full px-2.5 py-1.5 border border-cream-200 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400">
                            </div>
                        </div>

                        <hr class="border-cream-100">

                        {{-- Stock --}}
                        <div>
                            <label class="flex items-center gap-2 text-xs font-medium text-espresso-700 cursor-pointer">
                                <input type="checkbox" name="in_stock" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300"
                                       {{ request('in_stock') ? 'checked' : '' }} onchange="this.form.submit()">
                                <span>In Stock Only</span>
                            </label>
                        </div>

                        <button type="submit" class="btn-gold w-full text-xs py-2.5">Apply Filters</button>
                        @if(request()->anyFilled(['category', 'occasion', 'min_price', 'max_price', 'in_stock']))
                            <a href="{{ route('shop') }}" class="btn-secondary w-full justify-center text-xs py-2.5 block text-center">Clear Filters</a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

    </div>

</x-app-layout>
