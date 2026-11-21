<x-app-layout title="Order Success — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <div class="bg-white border border-cream-150 rounded-3xl p-8 shadow-luxury text-center relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-24 h-24 bg-primary-100 rounded-full blur-xl opacity-40"></div>
            
            {{-- Icon --}}
            <div class="w-16 h-16 rounded-full bg-green-100 text-green-600 flex items-center justify-center mx-auto text-3xl mb-6">
                🎉
            </div>

            <h1 class="font-heading font-bold text-2xl sm:text-3xl text-espresso-800">Order Placed Successfully!</h1>
            <p class="text-sm text-espresso-500 mt-2 max-w-lg mx-auto">
                Thank you for your purchase. Your order has been registered and is now being processed. We will contact you shortly to confirm the delivery timeline.
            </p>

            {{-- Summary Banner --}}
            <div class="bg-cream-50 border border-cream-100 rounded-2xl p-5 my-8 grid grid-cols-2 gap-4 text-xs max-w-md mx-auto">
                <div class="text-left flex flex-col gap-0.5 border-r border-cream-200">
                    <span class="text-espresso-400 font-medium">Order Number</span>
                    <span class="text-espresso-800 font-bold text-sm">{{ $order->order_number }}</span>
                </div>
                <div class="text-right flex flex-col gap-0.5">
                    <span class="text-espresso-400 font-medium">Total Amount</span>
                    <span class="text-primary-600 font-bold text-sm">{{ order_price($order, $order->grand_total) }}</span>
                </div>
            </div>

            {{-- Order details snapshot --}}
            <div class="text-left border border-cream-200 rounded-2xl p-6 mb-8 text-xs space-y-4">
                <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-2">Delivery & Customer Details</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <span class="text-espresso-400 block font-medium">Customer Name:</span>
                        <span class="font-bold text-espresso-800 text-sm">{{ $order->customer_name }}</span>
                    </div>
                    <div>
                        <span class="text-espresso-400 block font-medium">Contact Phone:</span>
                        <span class="font-bold text-espresso-800 text-sm">{{ $order->customer_phone }}</span>
                    </div>
                </div>

                <div>
                    <span class="text-espresso-400 block font-medium">Shipping Address:</span>
                    <span class="font-bold text-espresso-800 text-sm">
                        {{ $order->full_delivery_address }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-cream-100">
                    <div>
                        <span class="text-espresso-400 block font-medium">Payment Method:</span>
                        <span class="font-bold text-espresso-800 uppercase text-sm">{{ $order->payment_method_label }}</span>
                    </div>
                    <div>
                        <span class="text-espresso-400 block font-medium">Payment Status:</span>
                        <span class="font-bold text-yellow-600 uppercase text-sm">{{ $order->payment_status }}</span>
                    </div>
                </div>
            </div>

            @if($order->needs_measurements)
                <div class="border border-cream-200 rounded-2xl p-5 mb-6 max-w-md mx-auto text-sm text-left">
                    <span class="font-bold block mb-1">Next step: send your measurements</span>
                    <p class="text-espresso-600 mb-3 leading-relaxed">Your order includes a Custom Size or made-to-measure piece. Send your body measurements with order number {{ $order->order_number }} so we can start production.</p>
                    <a href="{{ route('guide.measurement-form', ['order' => $order->order_number]) }}" class="btn-primary inline-block px-5 py-2 rounded-full text-xs">Send measurements</a>
                    <a href="{{ route('guide.how-to-measure') }}" class="underline text-xs ml-3">How to measure</a>
                </div>
            @endif

            <div class="bg-primary-50 border border-primary-100 rounded-2xl p-5 mb-8 max-w-md mx-auto text-xs">
                <span class="text-primary-700 font-bold block mb-1">Live Order Tracking Link</span>
                <p class="text-espresso-600 mb-3 leading-relaxed">Copy this link to track your order status anytime:</p>
                <div class="flex items-center gap-2 bg-white border border-cream-200 p-2 rounded-xl">
                    <input type="text" readonly value="{{ route('orders.track-guest', $order->tracking_token) }}" id="trackingLink"
                           class="flex-1 bg-transparent border-0 outline-none text-espresso-750 select-all p-0 text-xs focus:ring-0">
                    <button onclick="window.copyTextToClipboard(document.getElementById('trackingLink').value)"
                            class="text-[10px] font-semibold bg-primary-100 text-primary-700 px-2.5 py-1.5 rounded-lg hover:bg-primary-200 transition-colors">
                        Copy
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('shop') }}" class="btn-gold py-3 px-8 text-sm font-semibold tracking-wide">
                    Continue Shopping
                </a>
                @guest
                    <a href="{{ route('orders.track-guest', $order->tracking_token) }}" class="btn-secondary py-3 px-8 text-sm">
                        Track Your Order
                    </a>
                @else
                    <a href="{{ route('orders.show', $order->id) }}" class="btn-secondary py-3 px-8 text-sm">
                        Track Your Order
                    </a>
                @endguest
            </div>

        </div>

    </div>

</x-app-layout>
