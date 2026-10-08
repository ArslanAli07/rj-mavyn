<x-app-layout title="My Wishlist — {{ setting('site_name', config('site.name', 'Store')) }}">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['Wishlist' => '']" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        {{-- Title --}}
        <div class="mb-10 text-center max-w-xl mx-auto">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">My Favorites</h1>
            <div class="gold-line w-16 mx-auto mt-2"></div>
            <p class="text-sm text-espresso-500 mt-2">Your personal collection of must-have Happy Tea favorites.</p>
        </div>

        {{-- Wishlisted items --}}
        @if($wishlist->isEmpty())
            <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center max-w-md mx-auto">
                <div class="text-5xl mb-4">❤️</div>
                <h2 class="font-heading text-lg font-bold text-espresso-800">Your wishlist is empty</h2>
                <p class="text-xs text-espresso-500 mt-2 mb-6">Explore our catalog and save your favorites here for later checkout.</p>
                <a href="{{ route('shop') }}" class="btn-primary w-full py-2.5 text-xs">Start Browsing</a>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
                @foreach($wishlist as $item)
                    @if($item->product)
                        <x-product-card :product="$item->product" />
                    @endif
                @endforeach
            </div>
        @endif

    </div>

</x-app-layout>
