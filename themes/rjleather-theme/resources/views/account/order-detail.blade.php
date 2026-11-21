@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
    $statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
    $currentIdx = array_search($order->status, $statuses);
    if ($order->status === 'cancelled') {
        $statuses = ['pending', 'cancelled'];
        $currentIdx = 1;
    }
@endphp
<x-app-layout :title="'Order '.($order->order_number).' — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('orders.index') }}">Orders</a><span>/</span><span aria-current="page">#{{ $order->order_number }}</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-account">
                <x-account-nav active="orders" />

                <div>
                    <div class="rj-panel" style="margin-bottom:30px">
                        <div class="rj-panel__head">
                            <h2>Order #{{ $order->order_number }}</h2>
                            <div style="display:flex;align-items:center;gap:16px">
                                @if($order->payment_method !== 'cod' && $order->status === 'pending')
                                    <a class="rj-textlink" style="font-size:10px" href="{{ route('checkout.payment-instructions', $order->id) }}">Payment instructions</a>
                                @endif
                                <span class="rj-chip {{ $order->status === 'cancelled' ? 'rj-chip--danger' : 'rj-chip--accent' }}">{{ str_replace('_', ' ', $order->status) }}</span>
                            </div>
                        </div>

                        <p style="font-size:12px;color:var(--rj-muted);margin-bottom:18px">Placed on {{ store_time($order->created_at)->format('M d, Y \a\t h:i A') }}</p>

                        <div class="rj-timeline">
                            @foreach($statuses as $idx => $s)
                                @php $isPast = $currentIdx !== false && $idx <= $currentIdx; $isCurrent = $order->status === $s; @endphp
                                <div class="rj-timeline__step {{ $isPast ? 'is-past' : '' }} {{ $isCurrent ? 'is-current' : '' }}">
                                    <span class="rj-timeline__dot">{{ $isPast ? '✓' : $idx + 1 }}</span>
                                    <span>{{ str_replace('_', ' ', $s) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rj-panel" style="margin-bottom:30px">
                        <div class="rj-panel__head"><h2>Ordered items</h2></div>
                        <div class="rj-items">
                            @foreach($order->items as $item)
                                <div class="rj-items__row">
                                    <img src="{{ $item->product_image_url }}" alt="{{ $item->product_title }}" loading="lazy">
                                    <div style="min-width:0">
                                        <b>{{ $item->product_title }}</b>
                                        @include('components.item-configuration', ['item' => $item, 'class' => 'mt-1 space-y-0.5', 'labelClass' => 'text-xs text-espresso-600', 'optionClass' => 'text-xs text-espresso-600'])
                                        <p>SKU {{ $item->product_sku }} &mdash; Qty {{ $item->quantity }} @ {{ order_price($order, $item->unit_price) }}</p>
                                    </div>
                                    <span class="rj-items__price">{{ order_price($order, $item->total_price) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="rj-total" style="margin-top:24px">
                            <div><span>Subtotal</span><span>{{ order_price($order, $order->subtotal) }}</span></div>
                            @if($order->discount_amount > 0)
                                <div><span>Discount</span><span style="color:var(--rj-accent-deep)">-{{ order_price($order, $order->discount_amount) }}</span></div>
                            @endif
                            <div><span>Delivery fee</span><span>{{ $order->delivery_fee_label }}</span></div>
                            <div class="grand"><span>Grand total</span><span>{{ order_price($order, $order->grand_total) }}</span></div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:26px">
                        <div class="rj-panel">
                            <div class="rj-panel__head"><h2>Delivery address</h2></div>
                            <p style="font-size:13px;line-height:1.8;color:var(--rj-muted)">
                                <strong style="color:#000">{{ $order->customer_name }}</strong><br>
                                {{ $order->full_delivery_address }}<br>
                                Phone: {{ $order->customer_phone }}
                            </p>
                        </div>
                        <div class="rj-panel">
                            <div class="rj-panel__head"><h2>Payment details</h2></div>
                            <div style="font-size:13px;color:var(--rj-muted)">
                                Method: <strong style="color:#000;text-transform:uppercase">{{ $order->payment_method_label }}</strong><br>
                                Payment status: <strong style="color:#000;text-transform:uppercase">{{ $order->payment_status }}</strong>
                            </div>
                            @if($order->order_notes)
                                <div style="border-top:1px solid var(--rj-line-soft);margin-top:14px;padding-top:14px">
                                    <span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase">Order notes</span>
                                    <em style="display:block;font-size:13px;line-height:1.7;color:var(--rj-muted);margin-top:6px">&ldquo;{{ $order->order_notes }}&rdquo;</em>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
