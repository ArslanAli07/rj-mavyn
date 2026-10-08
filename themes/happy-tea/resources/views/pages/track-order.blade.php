<x-app-layout title="Track Order #{{ $order->order_number }} — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        {{-- Order details header card --}}
        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
            <div class="flex flex-wrap items-center justify-between border-b border-cream-100 pb-4 mb-6 gap-4">
                <div>
                    <span class="text-xs text-espresso-400 font-semibold uppercase">Order Tracking</span>
                    <h1 class="font-heading font-bold text-espresso-800 text-xl sm:text-2xl mt-0.5">Order #{{ $order->order_number }}</h1>
                    <p class="text-xs text-espresso-500 mt-1">Placed on {{ $order->created_at->format('M d, Y \a\t h:i A') }}</p>
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
            <div class="mb-12">
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
                                    @if($s === 'preparing')
                                        Being Prepared
                                    @else
                                        {{ str_replace('_', ' ', $s) }}
                                    @endif
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
                            <img src="{{ $item->product_image ? asset('storage/' . $item->product_image) : asset('images/placeholder.jpg') }}" 
                                 alt="{{ $item->product_title }}" 
                                 class="w-12 h-12 rounded-xl object-cover border border-cream-100 shrink-0">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-espresso-800 truncate">{{ $item->product_title }}</p>
                                @include('components.item-configuration', ['item' => $item, 'class' => 'mt-0.5 space-y-0.5', 'labelClass' => 'text-xs text-espresso-600', 'optionClass' => 'text-xs text-espresso-600'])
                                <p class="text-espresso-450 mt-0.5">SKU: {{ $item->product_sku }} · Qty: {{ $item->quantity }} @ {{ format_price($item->unit_price) }}</p>
                            </div>
                            <span class="font-bold text-espresso-800 shrink-0">{{ format_price($item->total_price) }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Price Breakdown --}}
                <div class="bg-cream-50/50 rounded-2xl p-5 border border-cream-100 max-w-md ml-auto text-xs sm:text-sm space-y-2.5">
                    <div class="flex justify-between text-espresso-600">
                        <span>Subtotal:</span>
                        <span class="font-semibold text-espresso-800">{{ format_price($order->subtotal) }}</span>
                    </div>
                    @if($order->discount_amount > 0)
                        <div class="flex justify-between text-green-600 font-medium">
                            <span>Discount:</span>
                            <span>-{{ format_price($order->discount_amount) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-espresso-600">
                        <span>Delivery Fee:</span>
                        <span class="font-semibold text-espresso-800">
                            @if($order->delivery_fee > 0)
                                {{ format_price($order->delivery_fee) }}
                            @elseif(is_null($order->delivery_zone_id))
                                To be confirmed
                            @else
                                Free
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between font-bold text-espresso-800 text-base border-t border-cream-150 pt-2">
                        <span>Grand Total:</span>
                        <span class="text-primary-650">{{ format_price($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

            {{-- Customer & Shipping Address details --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-t border-cream-100 pt-6 mt-6 text-xs sm:text-sm">
                <div class="space-y-2 bg-cream-50/20 border border-cream-150 p-4 rounded-2xl">
                    <h4 class="font-bold text-espresso-800 uppercase tracking-wider text-[10px]">Delivery Address</h4>
                    <p class="text-espresso-700 leading-relaxed font-light">
                        <span class="font-semibold block text-espresso-800">{{ $order->customer_name }}</span>
                        {{ $order->delivery_address }}, {{ $order->delivery_area }}, {{ $order->delivery_city }}, {{ $order->delivery_country }}
                    </p>
                    <p class="text-espresso-450 mt-1 font-medium">Phone: {{ $order->customer_phone }}</p>
                </div>

                <div class="space-y-2 bg-cream-50/20 border border-cream-150 p-4 rounded-2xl">
                    <h4 class="font-bold text-espresso-800 uppercase tracking-wider text-[10px]">Payment Details</h4>
                    <div class="space-y-1 text-espresso-700 leading-relaxed font-light">
                        <div class="flex justify-between">
                            <span>Method:</span>
                            <span class="font-bold text-espresso-800 uppercase">{{ $order->payment_method }}</span>
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

</x-app-layout>
