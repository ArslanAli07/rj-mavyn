<x-app-layout title="Search Results for &quot;{{ $q }}&quot; — Happy Tea">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['Search' => '']" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        {{-- Search Header --}}
        <div class="mb-10 text-center max-w-xl mx-auto">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">Search Our Menu</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-6"></div>
            
            {{-- Inline Search Form --}}
            <form action="{{ route('search') }}" method="GET" class="flex items-center gap-2 bg-white border border-cream-200 shadow-md p-1.5 rounded-2xl">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search milk tea, boba, latte..." required autocomplete="off"
                       class="flex-1 border-0 focus:ring-0 text-sm px-3 text-espresso-850 placeholder-espresso-300 bg-transparent">
                <button type="submit" class="btn-gold text-xs py-2 px-5 shrink-0">Search</button>
            </form>
        </div>

        {{-- Results Info --}}
        <div class="mb-6 border-b border-cream-200 pb-4">
            @if(strlen($q) < 2)
                <p class="text-sm text-espresso-500">Please enter at least 2 characters to search.</p>
            @else
                <p class="text-sm text-espresso-700">
                    Showing results for "<span class="font-semibold text-espresso-850">{{ $q }}</span>" 
                    <span class="text-xs text-espresso-400 font-medium">({{ $products->total() }} items found)</span>
                </p>
            @endif
        </div>

        {{-- Product Grid --}}
        @if(strlen($q) >= 2)
            @if($products->isEmpty())
                <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center">
                    <div class="text-5xl mb-4">🔍</div>
                    <h2 class="font-heading text-xl font-bold text-espresso-800">No results found</h2>
                    <p class="text-sm text-espresso-500 mt-2 mb-6">We couldn't find any products matching your search query. Please try searching for something else.</p>
                    <div class="flex justify-center gap-2 flex-wrap">
                        @foreach(['Milk Tea', 'Boba', 'Latte', 'Fruit Tea'] as $s)
                            <a href="{{ route('search', ['q' => $s]) }}" class="px-4 py-1.5 bg-cream-100 text-espresso-750 text-xs rounded-full hover:bg-primary-100 hover:text-primary-700 transition-colors font-medium">{{ $s }}</a>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                {{-- Pagination --}}
                {{ $products->links('components.pagination') }}
            @endif
        @endif

    </div>

</x-app-layout>
