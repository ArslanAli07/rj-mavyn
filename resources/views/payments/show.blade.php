<x-app-layout>
    <section class="max-w-2xl mx-auto px-4 py-12">
        <h1 class="text-2xl font-bold">Payment for {{ $order->order_number }}</h1>
        <p>Total: {{ order_price($order, $order->grand_total) }} ({{ $order->currency }})</p>
        @if($payment->needs_review)
            <p role="status">Your payment needs review by the store. Please contact us before trying another payment.</p>
        @elseif($order->payment_status === 'paid')
            <p role="status">Payment received.</p>
        @elseif($order->payment_status === 'refunded')
            <p role="status">Payment refunded.</p>
        @elseif($order->status === 'cancelled')
            <p role="status">This order is cancelled. Contact the store if you have already paid.</p>
        @else
            <p role="status">Payment is pending. Continue to the payment provider or check again shortly if processing is in progress.</p>
            <form method="POST" action="{{ route('payments.start', $order) }}">
                @csrf
                <button type="submit" class="btn-gold">Continue payment</button>
            </form>
        @endif
        <a href="{{ route('orders.track-guest', $order->tracking_token) }}">View your order</a>
    </section>
</x-app-layout>
