<x-app-layout
    :title="$product->meta_title ?: $product->title . ' — '.setting('site_name', config('site.name', 'Store'))"
    :meta-description="$product->meta_description ?: $product->short_description"
    :meta-keywords="$product->meta_keywords"
    :og-image="$product->main_image_url">

    @foreach($display->notices as $notice)
        <p class="max-w-7xl mx-auto px-4 py-2">{{ $notice }}</p>
    @endforeach

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="[
        'Shop' => route('shop'),
        $product->category?->name => route('category.show', $product->category?->slug),
        $product->title => ''
    ]" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16"
         x-data="{
            activeImage: @js($product->detail_image['url']),
            activeSrcset: @js($product->detail_image['srcset'] ?? ''),
            activeAlt: @js($product->main_image_alt),
            quantity: 1,
            wishlisted: false,
            currencySymbol: @js($currencySymbol),
            variants: @js($productVariantsPayload),
            optionGroups: @js($productOptionGroupsPayload),
            selectedVariantId: {{ $defaultVariant?->id ? (int) $defaultVariant->id : 'null' }},
            selectedOptionIds: {},
            init() {
                this.optionGroups.forEach(group => {
                    this.selectedOptionIds[group.id] = group.selection_type === 'single' ? null : [];
                });
                @auth
                    this.wishlisted = {{ in_array($product->id, $storeWishlistedIds) ? 'true' : 'false' }};
                @else
                    let localWishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                    this.wishlisted = localWishlist.includes({{ $product->id }});
                @endauth
            },
            get selectedVariant() {
                return this.variants.find(v => v.id === this.selectedVariantId) || null;
            },
            get selectedOptionIdList() {
                return this.optionGroups.flatMap(group => {
                    const selected = this.selectedOptionIds[group.id];
                    if (group.selection_type === 'single') {
                        return selected ? [selected] : [];
                    }
                    return Array.isArray(selected) ? selected : [];
                });
            },
            get optionsTotal() {
                let total = 0;
                this.optionGroups.forEach(group => {
                    const selected = this.selectedOptionIds[group.id];
                    const ids = group.selection_type === 'single'
                        ? (selected ? [selected] : [])
                        : (Array.isArray(selected) ? selected : []);
                    group.values.forEach(value => {
                        if (ids.includes(value.id)) {
                            total += Math.round(Number(value.price_adjustment || 0) * 100);
                        }
                    });
                });
                return total / 100;
            },
            get unitPrice() {
                const base = this.selectedVariant ? Number(this.selectedVariant.effective_price) : {{ $product->effective_price === null ? 'null' : (float) $product->effective_price }};
                return (Math.round(base * 100) + Math.round(this.optionsTotal * 100)) / 100;
            },
            formatMoney(amount) {
                if (amount === null || !this.selectedVariant) return 'Unavailable in this currency';
                return this.currencySymbol + ' ' + Number(amount).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            },
            selectSingleOption(group, valueId) {
                // Optional single groups can be cleared by tapping the selected choice again.
                if (this.selectedOptionIds[group.id] === valueId && group.min_selections < 1) {
                    this.selectedOptionIds[group.id] = null;
                    return;
                }
                this.selectedOptionIds[group.id] = valueId;
            },
            clearOptionGroup(group) {
                if (group.min_selections > 0) {
                    return;
                }
                this.selectedOptionIds[group.id] = group.selection_type === 'single' ? null : [];
            },
            toggleMultiOption(group, valueId) {
                const current = Array.isArray(this.selectedOptionIds[group.id]) ? [...this.selectedOptionIds[group.id]] : [];
                const index = current.indexOf(valueId);
                if (index >= 0) {
                    if (current.length <= group.min_selections) {
                        return;
                    }
                    current.splice(index, 1);
                } else {
                    if (group.max_selections && current.length >= group.max_selections) {
                        return;
                    }
                    current.push(valueId);
                }
                this.selectedOptionIds[group.id] = current;
            },
            configurationValid() {
                if (!this.selectedVariant || this.selectedVariant.available_quantity === 0) return false;
                if (Number(this.quantity) > Math.min(20, this.selectedVariant.available_quantity ?? 20)) return false;
                return this.optionGroups.every(group => {
                    const selected = this.selectedOptionIds[group.id];
                    const count = group.selection_type === 'single'
                        ? (selected ? 1 : 0)
                        : (Array.isArray(selected) ? selected.length : 0);
                    if (count < group.min_selections) return false;
                    if (group.max_selections && count > group.max_selections) return false;
                    return true;
                });
            }
         }">

        {{-- Product Details Section --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12 items-start bg-white border border-cream-150 rounded-3xl p-6 sm:p-8">

            {{-- Left: Images Gallery --}}
            <div class="space-y-4">
                {{-- Main Image Container --}}
                <div class="aspect-square rounded-2xl overflow-hidden bg-cream-50 border border-cream-150 relative img-zoom">
                    <img :src="activeImage" :srcset="activeSrcset" sizes="(max-width: 1024px) 100vw, 50vw" :alt="activeAlt" class="w-full h-full object-cover">

                    @if($product->is_on_sale)
                        <span class="absolute top-4 left-4 badge-sale text-sm px-3 py-1">Sale (-{{ $product->discount_percentage }}%)</span>
                    @endif
                </div>

                {{-- Gallery Thumbnails --}}
                @if(count($product->gallery_images) > 0)
                    <div class="grid grid-cols-4 gap-3">
                        {{-- First thumbnail is always main image --}}
                        <button @click="activeImage = @js($product->detail_image['url']); activeSrcset = @js($product->detail_image['srcset'] ?? ''); activeAlt = @js($product->main_image_alt)"
                                :class="activeImage === '{{ $product->detail_image['url'] }}' ? 'ring-2 ring-primary-500' : 'opacity-70 hover:opacity-100'"
                                class="aspect-square rounded-xl overflow-hidden bg-cream-50 border border-cream-100 transition-all">
                            <img src="{{ $product->main_image_url }}" alt="{{ $product->main_image_alt }}" class="w-full h-full object-cover">
                        </button>
                        @foreach($product->gallery_images as $img)
                            <button @click="activeImage = @js($img['url']); activeSrcset = @js($img['srcset'] ?? ''); activeAlt = @js($img['alt_text'])"
                                    :class="activeImage === '{{ $img['url'] }}' ? 'ring-2 ring-primary-500' : 'opacity-70 hover:opacity-100'"
                                    class="aspect-square rounded-xl overflow-hidden bg-cream-50 border border-cream-100 transition-all">
                                <img src="{{ $img['url'] }}" srcset="{{ $img['srcset'] ?? '' }}" sizes="25vw" alt="{{ $img['alt_text'] }}" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Right: Product Details Info --}}
            <div class="flex flex-col">
                {{-- Category and badges --}}
                <div class="flex items-center justify-between gap-4">
                    <span class="text-xs font-semibold text-primary-600 tracking-widest uppercase">{{ $product->category?->name }}</span>
                    @if($product->available_for_sale)
                        <span class="badge bg-green-100 text-green-700 text-xs px-2.5 py-0.5">In Stock</span>
                    @else
                        <span class="badge bg-red-100 text-red-700 text-xs px-2.5 py-0.5">Out of Stock</span>
                    @endif
                </div>

                {{-- Title --}}
                <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800 mt-2 leading-tight">{{ $product->title }}</h1>

                {{-- SKU --}}
                <p class="text-xs text-espresso-400 mt-1">SKU: <span class="font-mono">{{ $product->sku }}</span></p>

                {{-- Stars/Rating placeholder --}}
                <div class="flex items-center gap-1.5 mt-3">
                    <div class="flex text-yellow-500 gap-0.5">
                        @for($i=0; $i<5; $i++)
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        @endfor
                    </div>
                    <span class="text-xs text-espresso-500 font-medium">(5.0 · 12 reviews)</span>
                </div>

                {{-- Pricing --}}
                <div class="flex items-baseline gap-3 mt-5 pb-5 border-b border-cream-100">
                    <span class="text-2xl sm:text-3xl font-bold text-espresso-850" x-text="formatMoney(unitPrice)"></span>
                    <template x-if="selectedVariant && selectedVariant.is_on_sale && optionsTotal === 0">
                        <span class="text-base text-espresso-400 line-through" x-text="selectedVariant.formatted_price"></span>
                    </template>
                </div>

                {{-- Brief description --}}
                <p class="text-sm text-espresso-600 my-5 leading-relaxed font-light">
                    {{ $product->short_description }}
                </p>

                {{-- Variant & Options --}}
                <div class="mb-6 space-y-5">
                    <template x-if="variants.length > 0">
                        <div class="space-y-3">
                            <span class="block text-xs font-semibold text-espresso-700 uppercase tracking-wider">Select Variant:</span>
                            <div class="flex flex-wrap gap-3">
                                <template x-for="v in variants" :key="v.id">
                                    <button type="button"
                                            @click="selectedVariantId = v.id; quantity = Math.min(quantity, v.available_quantity ?? 20)" :disabled="v.available_quantity === 0"
                                            :class="selectedVariantId === v.id ? 'border-primary-500 bg-primary-50 text-primary-900 ring-2 ring-primary-500/20' : 'border-cream-300 bg-white text-espresso-700 hover:border-cream-400'"
                                            class="px-4 py-2 text-sm font-semibold border rounded-xl transition-all duration-200 focus:outline-none">
                                        <span x-text="v.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-for="group in optionGroups" :key="group.id">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="block text-xs font-semibold text-espresso-700 uppercase tracking-wider" x-text="group.name"></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-espresso-400 font-medium" x-text="group.is_required ? 'Required' : 'Optional'"></span>
                                    <button type="button"
                                            x-show="!group.is_required && (group.selection_type === 'single' ? selectedOptionIds[group.id] !== null : (selectedOptionIds[group.id] || []).length > 0)"
                                            @click="clearOptionGroup(group)"
                                            class="text-[10px] font-semibold text-primary-600 hover:text-primary-800 underline underline-offset-2">
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="value in group.values" :key="value.id">
                                    <button type="button"
                                            @click="group.selection_type === 'single' ? selectSingleOption(group, value.id) : toggleMultiOption(group, value.id)"
                                            :class="(group.selection_type === 'single' ? selectedOptionIds[group.id] === value.id : (selectedOptionIds[group.id] || []).includes(value.id))
                                                ? 'border-primary-500 bg-primary-50 text-primary-900 ring-2 ring-primary-500/20'
                                                : 'border-cream-300 bg-white text-espresso-700 hover:border-cream-400'"
                                            class="px-3 py-2 text-sm font-medium border rounded-xl transition-all duration-200 focus:outline-none">
                                        <span x-text="value.name"></span>
                                        <template x-if="value.formatted_adjustment">
                                            <span class="text-espresso-500 ml-1" x-text="value.formatted_adjustment"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    @if($showServings)
                    <template x-if="selectedVariant && selectedVariant.servings">
                        <div class="flex items-center gap-2 bg-cream-50/70 border border-cream-100 rounded-xl p-3 text-xs w-fit">
                            <span class="text-espresso-400 font-medium">Serves:</span>
                            <span class="text-espresso-800 font-bold" x-text="selectedVariant.servings"></span>
                        </div>
                    </template>
                    @endif
                </div>

                {{-- Add to Cart Form --}}
                @if(!ordering_manually_enabled())
                    <div class="bg-cream-50 border border-cream-200 rounded-2xl p-4 mt-6">
                        <p class="text-sm font-semibold text-espresso-800">Online ordering is paused</p>
                        <p class="text-xs text-espresso-500 mt-1 leading-relaxed">We’re busy in-store right now. Checkout on our support team, or keep browsing the catalog — we’ll start taking online orders again soon.</p>
                    </div>
                @elseif(!shop_is_open())
                    <div class="bg-cream-50 border border-cream-200 rounded-2xl p-4 mt-6">
                        <p class="text-sm font-semibold text-espresso-800">The shop is currently closed</p>
                        <p class="text-xs text-espresso-500 mt-1 leading-relaxed">Online ordering is available from {{ ordering_hours_label() }}. You can still browse the catalog in the meantime.</p>
                    </div>
                @elseif($product->available_for_sale)
                    <form action="{{ route('cart.add') }}" method="POST" class="space-y-6" @submit="if (!configurationValid()) { $event.preventDefault(); window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Please complete the required options.', type: 'error' } })); }">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="product_variant_id" :value="selectedVariantId">
                        <template x-for="optionId in selectedOptionIdList" :key="'opt-' + optionId">
                            <input type="hidden" name="option_value_ids[]" :value="optionId">
                        </template>

                        <div>
                            <label for="custom_writing" class="block text-xs font-semibold text-espresso-700 uppercase tracking-wider mb-2">Special Request (Optional):</label>
                            <input type="text" id="custom_writing" name="special_request" placeholder="e.g. Less ice, 50% sugar" maxlength="255"
                                   class="input-luxury text-sm">
                        </div>

                        {{-- Quantity Selector & Add To Cart Button --}}
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center border border-cream-300 rounded-full bg-cream-50 p-1 shrink-0">
                                <button type="button" @click="if(quantity > 1) quantity--" class="w-8 h-8 rounded-full flex items-center justify-center text-espresso-700 hover:bg-cream-200 transition-colors font-bold text-lg select-none">-</button>
                                <input type="number" name="quantity" x-model="quantity" readonly class="w-12 text-center bg-transparent border-0 text-sm font-bold text-espresso-800 focus:ring-0 p-0">
                                <button type="button" @click="if(quantity < Math.min(20, selectedVariant?.available_quantity ?? 20)) quantity++" class="w-8 h-8 rounded-full flex items-center justify-center text-espresso-700 hover:bg-cream-200 transition-colors font-bold text-lg select-none">+</button>
                            </div>

                            <button type="submit" class="btn-gold flex-1 py-3 justify-center text-sm font-semibold tracking-wide" :disabled="!configurationValid()" :class="!configurationValid() ? 'opacity-60 cursor-not-allowed' : ''">
                                Add to Cart
                            </button>

                            {{-- Wishlist toggle --}}
                            @auth
                                <button type="button"
                                        @click="
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
                                        class="w-12 h-12 rounded-full border border-cream-350 flex items-center justify-center text-espresso-600 hover:bg-cream-100 transition-colors shrink-0"
                                        title="Wishlist">
                                    <svg class="w-5 h-5 transition-colors duration-250" :class="wishlisted ? 'fill-red-500 text-red-500' : 'text-espresso-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                </button>
                            @else
                                <button type="button"
                                        @click="
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
                                        class="w-12 h-12 rounded-full border border-cream-350 flex items-center justify-center text-espresso-600 hover:bg-cream-100 transition-colors shrink-0"
                                        title="Wishlist">
                                    <svg class="w-5 h-5 transition-colors duration-250" :class="wishlisted ? 'fill-red-500 text-red-500' : 'text-espresso-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                </button>
                            @endauth
                        </div>
                    </form>
                @else
                    <div class="bg-red-50 border border-red-150 rounded-2xl p-4 text-center mt-6">
                        <span class="text-sm font-semibold text-red-700">Currently Unavailable</span>
                        <p class="text-xs text-red-500 mt-1">This product is sold out or unavailable. Contact us on WhatsApp to inquire.</p>
                    </div>
                @endif

            </div>
        </div>

        {{-- Details & Ingredients Tabs --}}
        <div class="mt-12 bg-white border border-cream-150 rounded-3xl p-6 sm:p-8" x-data="{ tab: 'description' }">
            <div class="flex border-b border-cream-200 gap-6">
                <button @click="tab = 'description'"
                        :class="tab === 'description' ? 'border-primary-500 text-primary-600 font-semibold' : 'border-transparent text-espresso-500'"
                        class="pb-3 border-b-2 text-sm uppercase tracking-wider transition-colors duration-200">Description</button>
                @if($showIngredients && $product->ingredients)
                    <button @click="tab = 'ingredients'"
                            :class="tab === 'ingredients' ? 'border-primary-500 text-primary-600 font-semibold' : 'border-transparent text-espresso-500'"
                            class="pb-3 border-b-2 text-sm uppercase tracking-wider transition-colors duration-200">Ingredients</button>
                @endif
                <button @click="tab = 'delivery'"
                        :class="tab === 'delivery' ? 'border-primary-500 text-primary-600 font-semibold' : 'border-transparent text-espresso-500'"
                        class="pb-3 border-b-2 text-sm uppercase tracking-wider transition-colors duration-200">Delivery & Info</button>
            </div>

            <div class="py-6">
                {{-- Description Tab --}}
                <div x-show="tab === 'description'" class="prose prose-sm prose-espresso max-w-none leading-relaxed text-espresso-700 font-light">
                    {!! nl2br(e($product->description)) !!}
                </div>

                {{-- Ingredients Tab --}}
                @if($showIngredients && $product->ingredients)
                    <div x-show="tab === 'ingredients'" class="text-sm text-espresso-750 font-light leading-relaxed">
                        <h4 class="font-bold text-espresso-850 mb-2">Ingredients Listing:</h4>
                        <p class="mb-4">{{ $product->ingredients }}</p>
                        @if($showAllergens && $product->allergens)
                            <div class="bg-yellow-50 border border-yellow-250 text-yellow-850 text-xs rounded-xl p-3.5 mt-2">
                                <span class="font-bold">Allergen Notice:</span> Contains {{ $product->allergens }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Delivery Tab --}}
                <div x-show="tab === 'delivery'" class="text-sm text-espresso-750 font-light space-y-4 leading-relaxed">
                    <p>
                        Delivery availability and charges are confirmed at checkout.
                    </p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Delivery availability and charges are confirmed at checkout.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Related Products --}}
        @if($related->count() > 0)
            <div class="mt-16">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-8">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-heading font-bold text-espresso-800">You Might Also Like</h2>
                        <div class="gold-line w-16 mt-2"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
                    @foreach($related as $relProduct)
                        <x-product-card :product="$relProduct" />
                    @endforeach
                </div>
            </div>
        @endif

    </div>

</x-app-layout>
