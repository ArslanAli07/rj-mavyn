@php($paid = $order->payment_status === 'paid' && ! $payment->needs_review)
<x-app-layout :title="($paid ? 'Thank you — ' : 'Payment — ').$order->order_number">
    @include('guide._partials', ['noTabs' => true])
    <div class="gd">
        <div class="rj-container" style="max-width:760px">
            <header class="gd-head">
                <span class="gd-eyebrow">Order {{ $order->order_number }}</span>
                <h1 class="gd-title">{{ $paid ? 'Thank you, your order is confirmed' : 'Payment for '.$order->order_number }}</h1>
            </header>

            {{-- Payment state: logic unchanged; only presentation. --}}
            @if($payment->needs_review)
                <div class="gd-alert gd-alert--error" role="status">Your payment needs review by the store. Please contact us before trying another payment.</div>
            @elseif($order->payment_status === 'paid')
                <div class="gd-alert" role="status">Payment received. A confirmation email is on its way to {{ $order->customer_email }}.</div>
            @elseif($order->payment_status === 'refunded')
                <div class="gd-alert" role="status">Payment refunded.</div>
            @elseif($order->status === 'cancelled')
                <div class="gd-alert gd-alert--error" role="status">This order is cancelled. Contact the store if you have already paid.</div>
            @else
                <div class="gd-alert" role="status">Payment is pending. Continue to the payment provider or check again shortly if processing is in progress.</div>
                <form method="POST" action="{{ route('payments.start', $order) }}">
                    @csrf
                    <button type="submit" class="gd-btn">Continue payment</button>
                </form>
            @endif

            <section class="gd-section">
                <h2 class="gd-h2">Order summary</h2>
                <div class="gd-table-wrap">
                    <table class="gd-table" style="min-width:0">
                        <tbody>
                            @foreach($order->items as $item)
                                <tr><td style="white-space:normal">{{ $item->product_title }}@if($item->variant_name)<span class="gd-help" style="display:block">{{ $item->variant_name }}</span>@endif</td><td>× {{ $item->quantity }}</td><td style="text-align:right">{{ order_price($order, $item->total_price) }}</td></tr>
                            @endforeach
                            <tr><td colspan="2">Delivery</td><td style="text-align:right">{{ $order->delivery_method_label ? $order->delivery_method_label.' · ' : '' }}{{ $order->delivery_fee_label }}</td></tr>
                            @if((float) $order->discount_amount > 0)<tr><td colspan="2">Discount</td><td style="text-align:right">−{{ order_price($order, $order->discount_amount) }}</td></tr>@endif
                            @if((float) $order->tax_amount > 0)<tr><td colspan="2">Tax</td><td style="text-align:right">{{ order_price($order, $order->tax_amount) }}</td></tr>@endif
                            <tr><th colspan="2" style="background:none">Total</th><th style="text-align:right;background:none">{{ order_price($order, $order->grand_total) }} {{ $order->currency }}</th></tr>
                        </tbody>
                    </table>
                </div>
                <p class="gd-help" style="margin-top:12px">Ship to: {{ $order->full_delivery_address }}</p>
            </section>

            @if($order->needs_measurements && $order->status !== 'cancelled')
                <section class="gd-section">
                    <h2 class="gd-h2">Next step: send your measurements</h2>
                    <p class="gd-lede">Your order includes a Custom Size or made-to-measure piece. Send your body measurements with order number {{ $order->order_number }} so we can start production.</p>
                    <div class="gd-btns">
                        <a class="gd-btn" href="{{ route('guide.measurement-form', ['order' => $order->order_number]) }}">Send measurements</a>
                        <a class="gd-btn gd-btn--ghost" href="{{ route('guide.how-to-measure') }}">How to measure</a>
                    </div>
                </section>
            @endif

            <section class="gd-section" style="margin-bottom:40px">
                <h2 class="gd-h2">What happens next</h2>
                <p class="gd-lede">We check every order before production and make it by hand in our workshop. You will get an email with tracking when it ships.</p>
                <div class="gd-btns">
                    <a class="gd-btn" href="{{ route('orders.track-guest', $order->tracking_token) }}">View your order</a>
                    <a class="gd-btn gd-btn--ghost" href="{{ route('guide.how-it-works') }}">How it works</a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
