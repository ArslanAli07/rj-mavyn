<x-app-layout title="{{ $category->name }} Creations — Happy Tea">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="[
        'Shop' => route('shop'),
        $category->name => ''
    ]" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        {{-- Category Header --}}
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-cream-200 pb-6">
            <div>
                <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">{{ $category->name }}</h1>
                @if($category->description)
                    <p class="text-sm text-espresso-500 mt-1 max-w-2xl font-light">{{ $category->description }}</p>
                @else
                    <p class="text-sm text-espresso-500 mt-1">Browse our luxury selection of handcrafted {{ strtolower($category->name) }}.</p>
                @endif
            </div>
            
            <a href="{{ route('shop') }}" class="btn-secondary py-2 px-5 text-xs shrink-0 self-start md:self-center">
                ← View All Categories
            </a>
        </div>

        {{-- Product Grid --}}
        @if($products->isEmpty())
            <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center">
                <div class="text-5xl mb-4">🧋</div>
                <h2 class="font-heading text-xl font-bold text-espresso-800">No products found</h2>
                <p class="text-sm text-espresso-500 mt-2 mb-6">We don't have any items in this category at the moment. Please check back later.</p>
                <a href="{{ route('shop') }}" class="btn-primary">Browse other categories</a>
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

    </div>

</x-app-layout>
