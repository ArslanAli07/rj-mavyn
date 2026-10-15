<x-app-layout title="Payment Instructions — Order {{ $order->order_number }} — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        {{-- Success Card --}}
        <div class="bg-white border border-cream-150 rounded-3xl p-8 shadow-luxury text-center relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-24 h-24 bg-primary-100 rounded-full blur-xl opacity-40"></div>
            
            {{-- Icon --}}
            <div class="w-16 h-16 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center mx-auto text-2xl mb-6">
                💳
            </div>

            <h1 class="font-heading font-bold text-2xl sm:text-3xl text-espresso-800">Almost Done! Payment Required</h1>
            <p class="text-sm text-espresso-500 mt-2 max-w-lg mx-auto">
                Thank you for your order. To confirm your purchase, please transfer the amount and send a screenshot of the payment receipt on WhatsApp.
            </p>

            {{-- Summary Banner --}}
            <div class="bg-cream-50 border border-cream-100 rounded-2xl p-5 my-8 grid grid-cols-2 gap-4 text-xs max-w-md mx-auto">
                <div class="text-left flex flex-col gap-0.5 border-r border-cream-200">
                    <span class="text-espresso-400 font-medium">Order Number</span>
                    <span class="text-espresso-800 font-bold text-sm">{{ $order->order_number }}</span>
                </div>
                <div class="text-right flex flex-col gap-0.5">
                    <span class="text-espresso-400 font-medium">Amount to Pay</span>
                    <span class="text-primary-600 font-bold text-sm">{{ order_price($order, $order->grand_total) }}</span>
                </div>
            </div>

            {{-- Dynamic Payment Details --}}
            <div class="text-left border border-cream-200 rounded-2xl p-6 mb-8 bg-cream-50/20">
                @if($paymentInstructions)
                    <h3 class="font-heading font-bold text-base mb-4">{{ $paymentInstructions->label }}</h3>
                    <p class="text-sm whitespace-pre-line">{{ $paymentInstructions->instructions }}</p>
                @else
                    <p>Contact the store to confirm payment instructions for this order.</p>
                @endif
            </div>

            {{-- Instructions List --}}
            <div class="text-left text-xs text-espresso-600 bg-cream-50 p-5 rounded-2xl border border-cream-150 mb-8 space-y-2 leading-relaxed">
                <span class="font-bold text-espresso-800 text-sm block mb-1">How to Complete Your Order:</span>
                <p>1. Open your banking or wallet app and make the transfer of <span class="font-bold text-primary-600">{{ order_price($order, $order->grand_total) }}</span> to the account listed above.</p>
                <p>2. Take a screenshot of the successful payment transaction screen.</p>
                <p>3. Click the WhatsApp button below to open a chat with us. Paste the screenshot and send the pre-filled order text.</p>
                <p>4. Our team will verify the payment and change your order status from **Pending** to **Confirmed** within a few minutes.</p>
            </div>

            {{-- Send Screenshot WhatsApp Action --}}
            @php
                $waNum = preg_replace('/[^0-9]/', '', (string) setting('site_whatsapp'));

                // Construct Order Details text block
                $itemsText = "";
                foreach ($order->items as $item) {
                    $itemsText .= "• " . $item->product_title . " (Qty: " . $item->quantity . ") — " . order_price($order, $item->total_price) . "\n";
                }

                $messageString = "Hi, I have transferred payment for order *" . $order->order_number . "*.\n\n"
                    . "*Order Summary:*\n"
                    . $itemsText . "\n"
                    . "*Subtotal:* " . order_price($order, $order->subtotal) . "\n"
                    . ($order->discount_amount > 0 ? "*Discount:* -" . order_price($order, $order->discount_amount) . "\n" : "")
                    . "*Delivery Fee:* " . ($order->delivery_fee > 0 ? order_price($order, $order->delivery_fee) : (is_null($order->delivery_zone_id) ? "To be confirmed" : "Free")) . "\n"
                    . "*Grand Total:* *" . order_price($order, $order->grand_total) . "*\n\n"
                    . "*Customer Details:*\n"
                    . "Name: " . $order->customer_name . "\n"
                    . "Phone: " . $order->customer_phone . "\n"
                    . "Area/Zone: " . $order->delivery_area . "\n"
                    . "Address: " . str_replace("\n", " ", $order->delivery_address) . "\n\n"
                    . "Attached is my payment screenshot. Please verify and confirm my order.";

                $waText = urlencode($messageString);
            @endphp
            <div class="bg-primary-50 border border-primary-100 rounded-2xl p-5 mb-8 max-w-md mx-auto text-xs">
                <span class="text-primary-700 font-bold block mb-1">Live Order Tracking Link</span>
                <p class="text-espresso-600 mb-3 leading-relaxed">Copy this link to track your order status anytime:</p>
                <div class="flex items-center gap-2 bg-white border border-cream-200 p-2 rounded-xl">
                    <input type="text" readonly value="{{ route('orders.track-guest', $order->tracking_token) }}" id="trackingLink"
                           class="flex-1 bg-transparent border-0 outline-none text-espresso-750 select-all p-0 text-xs focus:ring-0">
                    <button onclick="navigator.clipboard.writeText(document.getElementById('trackingLink').value); window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Tracking link copied!', type: 'success' } }));"
                            class="text-[10px] font-semibold bg-primary-100 text-primary-700 px-2.5 py-1.5 rounded-lg hover:bg-primary-200 transition-colors">
                        Copy
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @if($waNum)
                <a href="https://wa.me/{{ $waNum }}?text={{ $waText }}" target="_blank" rel="noopener"
                   class="btn-gold bg-green-600 hover:bg-green-700 flex items-center justify-center gap-2 py-3 px-8 text-sm font-semibold tracking-wide">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 11.968.01c3.182.001 6.176 1.24 8.427 3.493a11.883 11.883 0 013.48 8.425c-.003 6.556-5.338 11.9-11.908 11.9h-.005c-2.002 0-3.968-.5-5.742-1.45L0 24zm6.59-4.846c1.785.1 3.5.7 5.08 1.45l.36.21 3.74-.98-.99-3.65.23-.37a9.863 9.863 0 001.51-5.26c-.001-5.45-4.43-9.88-9.88-9.88-2.64 0-5.12 1.03-6.98 2.9A9.825 9.825 0 002.89 12c.003 5.45 4.43 9.88 9.89 9.88h.005c2.01 0 4.02-.51 5.75-1.46L6.647 19.15zM17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.08-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51-.17-.01-.37-.01-.57-.01-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z"/></svg>
                    Send Screenshot via WhatsApp
                </a>
                @endif
                @guest
                    <a href="{{ route('orders.track-guest', $order->tracking_token) }}" class="btn-secondary flex items-center justify-center py-3 px-8 text-sm">
                        Track Your Order
                    </a>
                @else
                    <a href="{{ route('orders.show', $order->id) }}" class="btn-secondary flex items-center justify-center py-3 px-8 text-sm">
                        View Order Details
                    </a>
                @endguest
            </div>

        </div>

    </div>

</x-app-layout>
