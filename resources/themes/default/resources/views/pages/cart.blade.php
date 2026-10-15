<x-app-layout title="Your Shopping Cart — {{ setting('site_name', config('site.name', 'Store')) }}">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['Cart' => route('cart.index')]" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        <div class="mb-8">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">Shopping Cart</h1>
            <p class="text-sm text-espresso-500 mt-1">Review your sweet items before completing your order.</p>
        </div>

        @if($items->isEmpty())
            <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center max-w-md mx-auto">
                <div class="text-5xl mb-4">🛒</div>
                <h2 class="font-heading text-lg font-bold text-espresso-800">Your cart is empty</h2>
                <p class="text-xs text-espresso-500 mt-2 mb-6">Your cart is empty — time to pick a favorite item.</p>
                <a href="{{ route('shop') }}" class="btn-primary w-full py-2.5 text-xs">Browse the Catalog</a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                
                {{-- Left: Cart Items List --}}
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white border border-cream-150 rounded-3xl overflow-hidden">
                        
                        {{-- Desktop Table Header --}}
                        <div class="hidden sm:grid grid-cols-12 gap-4 bg-cream-50 px-6 py-4 text-xs font-semibold text-espresso-500 uppercase tracking-wider">
                            <div class="col-span-6">Product</div>
                            <div class="col-span-2 text-center">Price</div>
                            <div class="col-span-2 text-center">Quantity</div>
                            <div class="col-span-2 text-right">Total</div>
                        </div>

                        {{-- Item Rows --}}
                        <div class="divide-y divide-cream-100">
                            @foreach($items as $item)
                                <div class="grid grid-cols-12 gap-4 items-center px-6 py-6 text-sm">
                                    {{-- Product info --}}
                                    <div class="col-span-12 sm:col-span-6 flex gap-4">
                                        <img src="{{ $item->product?->main_image_url ?? asset('images/placeholder.svg') }}"
                                             alt="{{ $item->product?->title }}" 
                                             class="w-16 h-16 rounded-xl object-cover border border-cream-150 shrink-0">
                                        <div class="min-w-0">
                                            <a href="{{ route('product.show', $item->product?->slug) }}" class="font-heading font-bold text-espresso-850 hover:text-primary-600 transition-colors text-base">{{ $item->product?->title }}</a>
                                            <p class="text-xs text-espresso-400 mt-0.5">Category: {{ $item->product?->category?->name }}</p>
                                            @include('components.item-configuration', ['item' => $item])
                                            
                                            {{-- Delete Button Mobile / Inline --}}
                                            <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="mt-2">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    Remove Item
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- Price --}}
                                    <div class="col-span-4 sm:col-span-2 text-left sm:text-center mt-2 sm:mt-0">
                                        <span class="sm:hidden text-xs text-espresso-400 block font-medium">Price:</span>
                                        <span class="font-semibold text-espresso-800">{{ format_price($item->price) }}</span>
                                    </div>

                                    {{-- Quantity Selector --}}
                                    <div class="col-span-4 sm:col-span-2 text-center mt-2 sm:mt-0 flex justify-start sm:justify-center">
                                        <form action="{{ route('cart.update', $item->id) }}" method="POST" class="inline-flex items-center border border-cream-300 rounded-full bg-cream-50 p-1">
                                            @csrf @method('PATCH')
                                            
                                            {{-- Decrement button --}}
                                            <button type="submit" name="quantity" value="{{ $item->quantity - 1 }}" 
                                                    class="w-6 h-6 rounded-full flex items-center justify-center text-espresso-750 hover:bg-cream-200 transition-colors text-sm select-none"
                                                    {{ $item->quantity <= 1 ? 'disabled' : '' }}>-</button>
                                            
                                            <span class="w-8 text-center text-xs font-bold text-espresso-800">{{ $item->quantity }}</span>
                                            
                                            {{-- Increment button --}}
                                            <button type="submit" name="quantity" value="{{ $item->quantity + 1 }}" 
                                                    class="w-6 h-6 rounded-full flex items-center justify-center text-espresso-750 hover:bg-cream-200 transition-colors text-sm select-none">+</button>
                                        </form>
                                    </div>

                                    {{-- Total --}}
                                    <div class="col-span-4 sm:col-span-2 text-right mt-2 sm:mt-0">
                                        <span class="sm:hidden text-xs text-espresso-400 block font-medium">Total:</span>
                                        <span class="font-bold text-primary-600">{{ format_price($item->line_total) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Right: Cart Summary Panel --}}
                <div class="space-y-6">
                    
                    {{-- Billing Totals --}}
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm">
                        <h2 class="font-heading font-bold text-espresso-800 text-lg mb-4">Order Summary</h2>
                        
                        <div class="space-y-3 pb-4 border-b border-cream-100 text-sm">
                            <div class="flex justify-between text-espresso-600">
                                <span>Subtotal</span>
                                <span class="font-semibold text-espresso-800">{{ format_price($totals['subtotal']) }}</span>
                            </div>
                            
                            @if($totals['discount'] > 0)
                                <div class="flex justify-between text-green-600 items-center">
                                    <span class="flex items-center gap-1">
                                        Discount
                                        @if($coupon)
                                            <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold uppercase">{{ $coupon->code }}</span>
                                        @endif
                                    </span>
                                    <span class="font-semibold">-{{ format_price($totals['discount']) }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between text-espresso-600">
                                <span>Delivery Fee</span>
                                <span class="font-semibold text-espresso-800">
                                    @if(is_null($totals['delivery_fee']))
                                        Depends on Location
                                    @elseif($totals['delivery_fee'] > 0)
                                        {{ format_price($totals['delivery_fee']) }}
                                    @else
                                        Free
                                    @endif
                                </span>
                            </div>
                        </div>

                        {{-- Coupon Box --}}
                        <div class="py-4 border-b border-cream-100">
                            @if($coupon)
                                <div class="flex items-center justify-between bg-green-50 text-green-700 border border-green-200 rounded-2xl p-3 text-xs">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-bold">Coupon "{{ $coupon->code }}" Applied</span>
                                        <span>Saved {{ format_price($totals['discount']) }}</span>
                                    </div>
                                    <form action="{{ route('cart.coupon.remove') }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 font-bold">Remove</button>
                                    </form>
                                </div>
                            @else
                                <form action="{{ route('cart.coupon') }}" method="POST" class="flex gap-2">
                                    @csrf
                                    <input type="text" name="code" placeholder="Promo/Coupon Code" required
                                           class="w-full px-3 py-2 border border-cream-250 rounded-xl text-xs placeholder-espresso-300 focus:ring-primary-300 focus:border-primary-400">
                                    <button type="submit" class="btn-primary py-2 px-4 text-xs shrink-0 rounded-xl">Apply</button>
                                </form>
                            @endif
                        </div>

                        @guest('web')
                            <p class="text-xs text-espresso-400 mt-2">Guest coupon limits are checked using your checkout email and IP address.</p>
                        @endguest

                        {{-- Grand Total --}}
                        <div class="flex justify-between items-baseline font-heading font-bold text-espresso-800 text-xl pt-4">
                            <span>Total</span>
                            <span class="text-primary-600">{{ format_price($totals['grand_total']) }}</span>
                        </div>

                        <!-- {{-- Delivery Notice --}}
                        <p class="text-[10px] text-espresso-400 mt-4 leading-relaxed bg-cream-50 p-3 rounded-2xl border border-cream-100">
                            Delivery availability and charges are confirmed at checkout.
                        </p> -->

                        {{-- Checkout Action --}}
                        <a href="{{ route('checkout.index') }}" class="btn-gold w-full justify-center py-3 mt-6 text-sm font-semibold tracking-wide shadow-md">
                            Proceed to Checkout →
                        </a>
                    </div>
                </div>

            </div>
        @endif

    </div>

</x-app-layout>
