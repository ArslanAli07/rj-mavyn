<x-admin-layout title="Order {{ $order->order_number }} Management">

    {{-- Header Actions --}}
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Manage Order: {{ $order->order_number }}</h1>
            <p class="text-xs text-espresso-500 mt-1">Review items, shipping, payment snapshot, and update execution status.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.orders.invoice', $order->id) }}" target="_blank" rel="noopener noreferrer" class="btn-primary py-2 px-4 text-xs font-semibold inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print Invoice
            </a>
            <a href="{{ route('admin.orders.index') }}" class="btn-secondary py-2 px-4 text-xs">
                ← Back to Orders
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- Left: Order details, items breakdown, shipping address --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Order details snapshot --}}
            <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Delivery & Contact Snapshot</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-espresso-400 block font-medium">Customer Name:</span>
                        <span class="font-bold text-espresso-800 text-sm">{{ $order->customer_name }}</span>
                    </div>
                    <div>
                        <span class="text-espresso-400 block font-medium">Contact Phone:</span>
                        <span class="font-bold text-espresso-800 text-sm">{{ $order->customer_phone }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-espresso-400 block font-medium">Email Address:</span>
                        <span class="font-bold text-espresso-800 text-sm">{{ $order->customer_email }}</span>
                    </div>
                    @if($order->user)
                        <div>
                            <span class="text-espresso-400 block font-medium">User Profile:</span>
                            <a href="{{ route('admin.customers.show', $order->user_id) }}" class="text-primary-600 font-bold hover:underline">View Registered Profile</a>
                        </div>
                    @endif
                </div>

                <div class="text-xs">
                    <span class="text-espresso-400 block font-medium">Detailed Delivery Address:</span>
                    <span class="font-bold text-espresso-800 text-sm leading-relaxed">
                        {{ $order->delivery_address }}, {{ $order->delivery_area }}, {{ $order->delivery_city }}, {{ $order->delivery_country }}
                    </span>
                </div>
            </div>

            {{-- Ordered items --}}
            <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
                <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Ordered Items</h3>

                <div class="divide-y divide-cream-100 mb-6">
                    @foreach($order->items as $item)
                        <div class="flex items-center gap-4 py-4 text-xs sm:text-sm">
                            <img src="{{ $item->product_image_url }}"
                                 alt="{{ $item->product_title }}"
                                 class="w-12 h-12 rounded-xl object-cover border border-cream-100 shrink-0">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-espresso-800 text-sm truncate">{{ $item->product_title }}</p>
                                @include('components.item-configuration', ['item' => $item, 'class' => 'mt-0.5 space-y-0.5', 'labelClass' => 'text-[10px] text-espresso-600', 'optionClass' => 'text-[10px] text-espresso-600'])
                                <p class="text-espresso-450 mt-0.5">SKU: {{ $item->product_sku }} · Qty: {{ $item->quantity }} @ {{ format_price($item->unit_price) }}</p>
                            </div>
                            <span class="font-bold text-espresso-800 shrink-0">{{ format_price($item->total_price) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="bg-cream-50/50 border border-cream-100 rounded-2xl p-5 max-w-sm ml-auto text-xs space-y-2">
                    <div class="flex justify-between text-espresso-600">
                        <span>Subtotal:</span>
                        <span class="font-bold text-espresso-800">{{ format_price($order->subtotal) }}</span>
                    </div>
                    @if($order->discount_amount > 0)
                        <div class="flex justify-between text-green-600">
                            <span>Discount (Coupon):</span>
                            <span class="font-bold">-{{ format_price($order->discount_amount) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-espresso-600">
                        <span>Delivery Fee:</span>
                        <span class="font-bold text-espresso-800">{{ format_price($order->delivery_fee) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-espresso-800 text-sm border-t border-cream-150 pt-2">
                        <span>Grand Total:</span>
                        <span class="text-primary-650">{{ format_price($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right: Status management & notes --}}
        <div class="lg:col-span-4 space-y-6">

            {{-- Status Update Form --}}
            <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Update Execution</h3>

                <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="status" class="label-luxury text-xs">Order Execution Status</label>
                        <select id="status" name="status" class="input-luxury text-xs bg-cream-50/20">
                            <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending Confirmation</option>
                            <option value="confirmed" {{ $order->status === 'confirmed' ? 'selected' : '' }}>Confirmed (Paid/Processing)</option>
                            <option value="preparing" {{ $order->status === 'preparing' ? 'selected' : '' }}>Preparing</option>
                            <option value="out_for_delivery" {{ $order->status === 'out_for_delivery' ? 'selected' : '' }}>Out For Delivery</option>
                            <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered (Succeeded)</option>
                            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label for="payment_status" class="label-luxury text-xs">Payment Status</label>
                        <select id="payment_status" name="payment_status" class="input-luxury text-xs bg-cream-50/20">
                            <option value="pending" {{ $order->payment_status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="paid" {{ $order->payment_status === 'paid' ? 'selected' : '' }}>Paid (Confirmed)</option>
                            <option value="failed" {{ $order->payment_status === 'failed' ? 'selected' : '' }}>Failed</option>
                            <option value="refunded" {{ $order->payment_status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                        </select>
                    </div>

                    <div>
                        <label for="admin_notes" class="label-luxury text-xs">Internal Admin Notes</label>
                        <textarea id="admin_notes" name="admin_notes" rows="3" placeholder="Add packaging info, rider info, or reason for cancellation..."
                                  class="input-luxury text-xs">{{ $order->admin_notes }}</textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2 pb-1">
                        <input type="hidden" name="send_email" value="0">
                        <input type="checkbox" id="send_email" name="send_email" value="1" checked
                               class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                        <label for="send_email" class="text-xs text-espresso-750 font-medium cursor-pointer select-none">
                            Send Status Update Email to Customer
                        </label>
                    </div>

                    <button type="submit" class="btn-gold w-full justify-center text-xs py-2.5">
                        Save Status Update
                    </button>
                </form>
            </div>

            {{-- Notes & Payments Snapshot info --}}
            <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4 text-xs leading-relaxed">
                <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Payment & Notes</h3>

                <div class="space-y-2 text-espresso-750 font-light">
                    <div class="flex justify-between border-b border-cream-50 pb-2">
                        <span>Payment Channel:</span>
                        <span class="font-bold text-espresso-850 uppercase">{{ $order->payment_method }}</span>
                    </div>
                    <div class="flex justify-between border-b border-cream-50 pb-2">
                        <span>Payment Status:</span>
                        <span class="font-bold text-espresso-850 uppercase">{{ $order->payment_status }}</span>
                    </div>
                </div>

                @if($order->payment_receipt)
                    <div class="border-t border-cream-100 pt-3 mt-3">
                        <span class="font-bold text-espresso-800 block mb-1.5">Uploaded Payment Receipt:</span>
                        <a href="{{ route('admin.orders.receipt', $order) }}" target="_blank" class="text-primary-600 hover:underline font-semibold block mb-2">View Full Size Screenshot ↗</a>
                        <img src="{{ route('admin.orders.receipt', $order) }}" alt="Payment Receipt" class="max-w-full h-auto rounded-xl border border-cream-200">
                    </div>
                @endif

                @if($order->order_notes)
                    <div class="bg-cream-50 border border-cream-150 p-4 rounded-xl">
                        <span class="font-bold text-espresso-800 block mb-1">Customer Order Notes:</span>
                        <p class="text-espresso-600 italic">"{{ $order->order_notes }}"</p>
                    </div>
                @endif
            </div>

        </div>

    </div>

</x-admin-layout>
