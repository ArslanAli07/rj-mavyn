<x-app-layout title="Order {{ $order->order_number }} Details — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Account Sidebar --}}
            <aside class="w-full lg:w-64 bg-white border border-cream-150 rounded-3xl p-6 shrink-0 h-fit space-y-2">
                <div class="flex items-center gap-3 pb-5 mb-4 border-b border-cream-100">
                    <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-base">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-heading font-bold text-espresso-800 text-sm truncate">{{ auth()->user()->name }}</h4>
                        <p class="text-[10px] text-espresso-400 font-medium truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>

                <a href="{{ route('account.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>🏠</span> Dashboard
                </a>
                <a href="{{ route('account.profile') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>👤</span> Profile Details
                </a>
                <a href="{{ route('account.addresses') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>📍</span> Address Book
                </a>
                <a href="{{ route('orders.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-500 text-white shadow-sm">
                    <span>📦</span> Order History
                </a>
                <a href="{{ route('account.wishlist') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>❤️</span> My Wishlist
                </a>
                <a href="{{ route('account.password') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>🔒</span> Change Password
                </a>
                <hr class="border-cream-100 my-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-650 hover:bg-red-50 transition-colors text-left">
                        <span>🚪</span> Log Out
                    </button>
                </form>
            </aside>

            {{-- Main Content --}}
            <div class="flex-1 space-y-6">

                {{-- Order details header card --}}
                <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between border-b border-cream-100 pb-4 mb-6 gap-4">
                        <div>
                            <span class="text-xs text-espresso-400 font-semibold uppercase">Order Details</span>
                            <h2 class="font-heading font-bold text-espresso-800 text-lg sm:text-xl mt-0.5">Order #{{ $order->order_number }}</h2>
                            <p class="text-xs text-espresso-500 mt-1">Placed on {{ store_time($order->created_at)->format('M d, Y \a\t h:i A') }}</p>
                        </div>
                        <div class="flex flex-col sm:items-end gap-2 shrink-0">
                            <span class="status-{{ $order->status }} uppercase text-xs px-3 py-1 rounded-full font-bold">
                                {{ str_replace('_', ' ', $order->status) }}
                            </span>
                            @if($order->payment_method !== 'cod' && $order->status === 'pending')
                                <a href="{{ route('checkout.payment-instructions', $order->id) }}" class="text-xs font-semibold text-primary-600 hover:underline">
                                    View Payment Instructions →
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Order Status Timeline --}}
                    <div class="mb-10">
                        <h3 class="font-heading font-bold text-sm text-espresso-800 mb-6 uppercase tracking-wider">Tracking Timeline</h3>

                        @php
                            $statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
                            $currentIdx = array_search($order->status, $statuses);
                            if ($order->status === 'cancelled') {
                                $statuses = ['pending', 'cancelled'];
                                $currentIdx = 1;
                            }
                        @endphp

                        <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6 md:gap-0">
                            {{-- Connector Line --}}
                            <div class="absolute left-4 top-2 bottom-2 md:left-6 md:right-6 md:top-1/2 md:bottom-auto h-full md:h-0.5 bg-cream-200 -z-10 w-0.5 md:w-auto"></div>
                            @if($currentIdx !== false && $order->status !== 'cancelled')
                                <div class="absolute left-4 top-2 md:left-6 md:top-1/2 md:bottom-auto h-0.5 bg-primary-500 -z-10 transition-all duration-500"
                                     style="height: calc({{ ($currentIdx / 4) * 100 }}% - 16px); width: 0.5px; left: 16px;"></div>
                                <div class="absolute hidden md:block left-6 right-6 top-1/2 h-0.5 bg-primary-500 -z-10 transition-all duration-500"
                                     style="width: calc({{ ($currentIdx / 4) * 100 }}% - 48px);"></div>
                            @endif

                            @foreach($statuses as $idx => $s)
                                @php
                                    $isPast = $currentIdx !== false && $idx <= $currentIdx;
                                    $isCurrent = $order->status === $s;
                                @endphp
                                <div class="flex md:flex-col items-center md:text-center gap-3 md:gap-2 relative z-10">
                                    <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-xs transition-all duration-300
                                                {{ $isPast ? 'bg-primary-500 text-white shadow-md' : 'bg-cream-150 text-espresso-400 border border-cream-200' }}">
                                        @if($isPast)
                                            ✓
                                        @else
                                            {{ $idx + 1 }}
                                        @endif
                                    </div>
                                    <div class="text-left md:text-center">
                                        <span class="block text-xs font-bold uppercase tracking-wider {{ $isCurrent ? 'text-primary-600' : 'text-espresso-700' }}">
                                            {{ str_replace('_', ' ', $s) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Order Items Table --}}
                    <div class="border-t border-cream-100 pt-6">
                        <h3 class="font-heading font-bold text-sm text-espresso-800 mb-4 uppercase tracking-wider">Ordered Items</h3>

                        <div class="divide-y divide-cream-50 mb-6">
                            @foreach($order->items as $item)
                                <div class="flex items-center gap-4 py-3.5 text-xs sm:text-sm">
                                    <img src="{{ $item->product_image_url }}"
                                         alt="{{ $item->product_title }}"
                                         class="w-12 h-12 rounded-xl object-cover border border-cream-100 shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-espresso-800 truncate">{{ $item->product_title }}</p>
                                        @include('components.item-configuration', ['item' => $item, 'class' => 'mt-0.5 space-y-0.5', 'labelClass' => 'text-xs text-espresso-600', 'optionClass' => 'text-xs text-espresso-600'])
                                        <p class="text-espresso-450 mt-0.5">SKU: {{ $item->product_sku }} · Qty: {{ $item->quantity }} @ {{ order_price($order, $item->unit_price) }}</p>
                                    </div>
                                    <span class="font-bold text-espresso-800 shrink-0">{{ order_price($order, $item->total_price) }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Price Breakdown --}}
                        <div class="bg-cream-50/50 rounded-2xl p-5 border border-cream-100 max-w-md ml-auto text-xs sm:text-sm space-y-2.5">
                            <div class="flex justify-between text-espresso-600">
                                <span>Subtotal:</span>
                                <span class="font-semibold text-espresso-800">{{ order_price($order, $order->subtotal) }}</span>
                            </div>
                            @if($order->discount_amount > 0)
                                <div class="flex justify-between text-green-600 font-medium">
                                    <span>Discount:</span>
                                    <span>-{{ order_price($order, $order->discount_amount) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-espresso-600">
                                <span>Delivery Fee:</span>
                                <span class="font-semibold text-espresso-800">
                                    {{ $order->delivery_fee_label }}
                                </span>
                            </div>
                            <div class="flex justify-between font-bold text-espresso-800 text-base border-t border-cream-150 pt-2">
                                <span>Grand Total:</span>
                                <span class="text-primary-650">{{ order_price($order, $order->grand_total) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Customer & Shipping Address details --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-t border-cream-100 pt-6 mt-6 text-xs sm:text-sm">
                        <div class="space-y-2 bg-cream-50/20 border border-cream-150 p-4 rounded-2xl">
                            <h4 class="font-bold text-espresso-800 uppercase tracking-wider text-[10px]">Delivery Address</h4>
                            <p class="text-espresso-700 leading-relaxed font-light">
                                <span class="font-semibold block text-espresso-800">{{ $order->customer_name }}</span>
                                {{ $order->full_delivery_address }}
                            </p>
                            <p class="text-espresso-450 mt-1 font-medium">Phone: {{ $order->customer_phone }}</p>
                        </div>

                        <div class="space-y-2 bg-cream-50/20 border border-cream-150 p-4 rounded-2xl">
                            <h4 class="font-bold text-espresso-800 uppercase tracking-wider text-[10px]">Payment Details</h4>
                            <div class="space-y-1 text-espresso-700 leading-relaxed font-light">
                                <div class="flex justify-between">
                                    <span>Method:</span>
                                    <span class="font-bold text-espresso-800 uppercase">{{ $order->payment_method_label }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Payment Status:</span>
                                    <span class="font-bold text-espresso-800 uppercase">{{ $order->payment_status }}</span>
                                </div>
                            </div>
                            @if($order->order_notes)
                                <div class="border-t border-cream-100 pt-2 mt-2">
                                    <span class="font-semibold text-espresso-800 block mb-0.5">Order Notes:</span>
                                    <p class="text-espresso-600 italic font-light">"{{ $order->order_notes }}"</p>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

</x-app-layout>
