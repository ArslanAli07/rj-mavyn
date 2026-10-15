@props(['product'])

<div class="product-card group"
     x-data="{
         wishlisted: false,
         init() {
             @auth
                 this.wishlisted = {{ in_array($product->id, $storeWishlistedIds) ? 'true' : 'false' }};
             @else
                 let localWishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                 this.wishlisted = localWishlist.includes({{ $product->id }});
             @endauth
         }
     }">
    {{-- Product Image & Badge Container --}}
    <div class="product-card-inner aspect-square overflow-hidden bg-cream-100 img-zoom relative border border-cream-200/50 rounded-t-2xl">
        <a href="{{ route('product.show', $product->slug) }}" class="block w-full h-full hover-trigger">
            @php
                $hasRealImage = (bool) ($product->main_image_asset_id || $product->main_image);
                $productImg = $hasRealImage
                    ? $product->main_image_url
                    : asset('images/placeholder.svg');
            @endphp
            <img src="{{ $productImg }}" srcset="{{ $product->main_image_srcset }}" sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
                 alt="{{ $product->main_image_alt }}"
                 class="w-full h-full {{ $hasRealImage ? 'object-cover' : 'object-contain p-6 opacity-75' }} transition-transform duration-700 group-hover:scale-110"
                 loading="lazy">
        </a>

        {{-- Badges --}}
        <div class="absolute top-2 left-2 sm:top-3 sm:left-3 flex flex-col gap-1 sm:gap-1.5 z-10">
            @if($product->is_featured)
                <span class="badge-featured">Featured</span>
            @endif
            @if($product->is_bestseller)
                <span class="badge-bestseller">Bestseller</span>
            @endif
            @if($product->is_new_arrival)
                <span class="badge-new">New</span>
            @endif
            @if($product->is_on_sale)
                <span class="badge-sale">Sale (-{{ $product->discount_percentage }}%)</span>
            @endif
        </div>

        {{-- Wishlist Button --}}
        @auth
            <button @click="
                fetch('{{ route('wishlist.toggle', $product->id) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    wishlisted = data.added;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message, type: data.added ? 'success' : 'info' } }));
                })
            "
            class="absolute top-2 right-2 sm:top-3 sm:right-3 text-espresso-850 hover:scale-110 active:scale-95 transition-all z-10 hover-trigger drop-shadow-sm"
            title="Add to Wishlist">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 transition-colors duration-250"
                     :class="wishlisted ? 'fill-red-500 text-red-500' : 'text-espresso-600'"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>
        @else
            <button @click="
                let localWishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                if (localWishlist.includes({{ $product->id }})) {
                    localWishlist = localWishlist.filter(id => id !== {{ $product->id }});
                    wishlisted = false;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Removed from wishlist.', type: 'info' } }));
                } else {
                    localWishlist.push({{ $product->id }});
                    wishlisted = true;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Added to wishlist!', type: 'success' } }));
                }
                localStorage.setItem('wishlist', JSON.stringify(localWishlist));
            "
            class="absolute top-2 right-2 sm:top-3 sm:right-3 text-espresso-850 hover:scale-110 active:scale-95 transition-all z-10 hover-trigger drop-shadow-sm"
            title="Add to Wishlist">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 transition-colors duration-250"
                     :class="wishlisted ? 'fill-red-500 text-red-500' : 'text-espresso-600'"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>
        @endauth

        <!-- Sliding Action Strip / Options Panel -->
        <x-product-card-add :product="$product" />
    </div>

    {{-- Product Body --}}
    <div class="p-3 sm:p-5 flex flex-col justify-between h-28 sm:h-36">
        <div>
            {{-- Category name --}}
            <span class="text-[9px] sm:text-[10px] text-primary-600 tracking-wider uppercase font-semibold mb-0.5 sm:mb-1 block">{{ $product->category?->name }}</span>

            {{-- Title --}}
            <a href="{{ route('product.show', $product->slug) }}" class="block hover-trigger">
                <h3 class="font-heading font-semibold text-espresso-850 text-sm sm:text-base line-clamp-1 leading-tight hover:text-primary-500 transition-colors">{{ $product->title }}</h3>
            </a>

            {{-- Short description --}}
            <p class="text-[11px] sm:text-xs text-espresso-500 mt-0.5 sm:mt-1 line-clamp-1 leading-relaxed">{{ $product->short_description }}</p>
        </div>

        <div class="flex items-center justify-between gap-2 mt-1.5 sm:mt-2 pt-1.5 sm:pt-2 border-t border-cream-200/60">
            {{-- Price --}}
            <div class="flex flex-col">
                @if($product->is_on_sale)
                    <span class="text-[11px] sm:text-xs text-espresso-400 line-through leading-none">{{ format_price($product->price) }}</span>
                    <span class="text-sm sm:text-base font-bold text-red-650 leading-none mt-0.5">{{ format_price($product->effective_price) }}</span>
                @else
                    <span class="text-sm sm:text-base font-bold text-espresso-800 leading-none mt-0.5">{{ format_price($product->price) }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
