<x-admin-layout title="Order {{ $order->order_number }} management">

    {{-- Header Actions --}}
    <div class="mb-5 d-flex flex-wrap align-items-center justify-content-between gap-4 border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Manage order: <span class="order-id">{{ $order->order_number }}</span></h1>
            <p class="small text-body-secondary mt-1">Review items, shipping, payment snapshot, and update execution status.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.orders.invoice', $order->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary py-2 px-3 small fw-semibold d-inline-flex align-items-center gap-2">
                <svg class="admin-width-3-5 admin-height-3-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print Invoice
            </a>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
                ← Back to orders
            </a>
        </div>
    </div>

    <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">

        {{-- Left: Order details, items breakdown, shipping address --}}
        <div class="admin-span-lg-8 admin-stack-6">

            {{-- Order details snapshot --}}
            <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Delivery & contact snapshot</h3>

                <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                    <div>
                        <span class="text-body-secondary d-block fw-medium">Customer Name:</span>
                        <span class="fw-semibold text-body">{{ $order->customer_name }}</span>
                    </div>
                    <div>
                        <span class="text-body-secondary d-block fw-medium">Contact Phone:</span>
                        <span class="fw-semibold text-body">{{ $order->customer_phone }}</span>
                    </div>
                </div>

                <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                    <div>
                        <span class="text-body-secondary d-block fw-medium">Email Address:</span>
                        <span class="fw-semibold text-body">{{ $order->customer_email }}</span>
                    </div>
                    @if($order->user)
                        <div>
                            <span class="text-body-secondary d-block fw-medium">User Profile:</span>
                            <a href="{{ route('admin.customers.show', $order->user_id) }}" class="text-primary fw-semibold link-underline-opacity-100-hover">View registered profile</a>
                        </div>
                    @endif
                </div>

                <div class="">
                    <span class="text-body-secondary d-block fw-medium">Detailed Delivery Address:</span>
                    <span class="fw-semibold text-body lh-lg">
                        {{ $order->delivery_address }}, {{ $order->delivery_area }}, {{ $order->delivery_city }}, {{ $order->delivery_country }}
                    </span>
                </div>
            </div>

            {{-- Ordered items --}}
            <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
                <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Ordered items</h3>

                <div class="admin-divided mb-4">
                    @foreach($order->items as $item)
                        <div class="d-flex align-items-center gap-4 py-3 admin-font-sm-sm">
                            <img src="{{ $item->product_image_url }}"
                                 alt="{{ $item->product_title }}"
                                 class="admin-width-12 admin-height-12 rounded-2 object-fit-cover border border-light-subtle flex-shrink-0">
                            <div class="flex-grow-1 admin-min-width-0">
                                <p class="fw-semibold text-body text-truncate">{{ $item->product_title }}</p>
                                @include('components.item-configuration', ['item' => $item, 'class' => 'mt-1 admin-stack-1', 'labelClass' => 'small text-body-secondary', 'optionClass' => 'small text-body-secondary', 'titleClass' => 'fw-medium text-body', 'adjustmentClass' => 'text-body-secondary num'])
                                <p class="text-body-secondary mt-1">SKU: <span class="sku">{{ $item->product_sku }}</span> · Qty: {{ $item->quantity }} @ {{ format_price($item->unit_price) }}</p>
                                @if($allocation = $item->inventoryReservation)
                                    <p class="small text-body-secondary mt-1">Stock: {{ match($allocation->state) {
                                        'reserved' => 'Reserved pending staff confirmation', 'consumed' => 'Deducted from on-hand stock',
                                        'released' => 'Reservation released', 'restocked' => 'Restored after cancellation', default => 'Review required',
                                    } }}</p>
                                @endif
                            </div>
                            <span class="fw-semibold text-body flex-shrink-0 price num">{{ format_price($item->total_price) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="bg-body-tertiary border border-light-subtle rounded-2 p-4 admin-max-width-sm ms-auto admin-stack-2">
                    <div class="d-flex justify-content-between text-body-secondary">
                        <span>Subtotal:</span>
                        <span class="fw-semibold text-body price num">{{ format_price($order->subtotal) }}</span>
                    </div>
                    @if($order->discount_amount > 0)
                        <div class="d-flex justify-content-between text-success">
                            <span>Discount (Coupon):</span>
                            <span class="fw-semibold price num">-{{ format_price($order->discount_amount) }}</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between text-body-secondary">
                        <span>Delivery Fee:</span>
                        <span class="fw-semibold text-body price num">{{ format_price($order->delivery_fee) }}</span>
                    </div>
                    <div class="d-flex justify-content-between fw-semibold text-body border-top border-light-subtle pt-2">
                        <span>Grand Total:</span>
                        <span class="text-primary price num">{{ format_price($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right: Status management & notes --}}
        <div class="admin-span-lg-4 admin-stack-6">

            {{-- Status Update Form --}}
            <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Update execution</h3>

                @if(auth('admin')->user()->hasPermission('orders.manage'))
                <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="admin-stack-4" data-admin-order-form data-original-status="{{ $order->status }}" data-original-payment="{{ $order->payment_status }}">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="status" class="form-label small">Order execution status</label>
                        <select id="status" name="status" class="form-select small bg-body-tertiary">
                            <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending confirmation</option>
                            <option value="confirmed" {{ $order->status === 'confirmed' ? 'selected' : '' }}>Confirmed (paid/processing)</option>
                            <option value="preparing" {{ $order->status === 'preparing' ? 'selected' : '' }}>Preparing</option>
                            <option value="out_for_delivery" {{ $order->status === 'out_for_delivery' ? 'selected' : '' }}>Out for delivery</option>
                            <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered (succeeded)</option>
                            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>

                    <div>
                        @unless(auth('admin')->user()->hasPermission('orders.payment'))<input type="hidden" name="payment_status" value="{{ $order->payment_status }}">@endunless
                        <label for="payment_status" class="form-label small">Payment status</label>
                        <select @disabled(! auth('admin')->user()->hasPermission('orders.payment')) id="payment_status" name="payment_status" class="form-select small bg-body-tertiary">
                            <option value="pending" {{ $order->payment_status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="paid" {{ $order->payment_status === 'paid' ? 'selected' : '' }}>Paid (confirmed)</option>
                            <option value="failed" {{ $order->payment_status === 'failed' ? 'selected' : '' }}>Failed</option>
                            <option value="refunded" {{ $order->payment_status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                        </select>
                    </div>

                    <div>
                        <label for="admin_notes" class="form-label small">Internal admin notes</label>
                        <textarea id="admin_notes" name="admin_notes" rows="3" placeholder="Add packaging info, rider info, or reason for cancellation..."
                                  class="form-control small">{{ $order->admin_notes }}</textarea>
                    </div>

                    <div class="d-flex align-items-center gap-2 pt-2 pb-1">
                        <input type="hidden" name="send_email" value="0">
                        <input type="checkbox" id="send_email" name="send_email" value="1" checked
                               class="form-check-input">
                        <label for="send_email" class="small text-body fw-medium admin-clickable user-select-none">
                            Send status update email to customer
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 justify-content-center small py-2">
                        Save status update
                    </button>
                </form>
                @endif
            </div>

            {{-- Notes & Payments Snapshot info --}}
            <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4 lh-lg">
                <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Payment & notes</h3>

                <div class="admin-stack-2 text-body fw-normal">
                    <div class="d-flex justify-content-between border-bottom border-light-subtle pb-2">
                        <span>Payment Channel:</span>
                        <span class="fw-semibold text-body">{{ $order->payment_method_label }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-light-subtle pb-2">
                        <span>Payment Status:</span>
                        <span class="fw-semibold text-body">{{ ucfirst($order->payment_status) }}</span>
                    </div>
                </div>

                @if($order->payment_receipt && auth('admin')->user()->hasPermission('orders.receipts'))
                    <div class="border-top border-light-subtle pt-3 mt-3">
                        <span class="fw-semibold text-body d-block mb-2">Uploaded Payment Receipt:</span>
                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.orders.receipt'))
<a href="{{ route('admin.orders.receipt', $order) }}" target="_blank" class="text-primary link-underline-opacity-100-hover fw-semibold d-block mb-2">View full size screenshot ↗</a>
@endif
                        <img src="{{ route('admin.orders.receipt', $order) }}" alt="Payment Receipt" class="mw-100 h-auto rounded-2 border border-light-subtle">
                    </div>
                @endif

                @if($order->order_notes)
                    <div class="bg-body-tertiary border border-light-subtle p-3 rounded-2">
                        <span class="fw-semibold text-body d-block mb-1">Customer Order Notes:</span>
                        <p class="text-body-secondary fst-italic">"{{ $order->order_notes }}"</p>
                    </div>
                @endif
            </div>

        </div>

    </div>

</x-admin-layout>
