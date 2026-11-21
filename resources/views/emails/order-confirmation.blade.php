@extends('emails.layout')

@section('title', 'Order Confirmation — '.$order->store_name)

@section('content')
    <div class="welcome">Hello, {{ $order->customer_name }}!</div>
    <p class="intro">
        Thank you for choosing {{ $order->store_name }}. We have successfully registered your order. Our team will contact you shortly on your provided phone number to confirm the dispatch details.
    </p>

    {{-- Summary details --}}
    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Order Number:</span>
            <span class="summary-value">{{ $order->order_number }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Payment Method:</span>
            <span class="summary-value" style="text-transform: uppercase;">{{ $order->payment_method_label }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Delivery Address:</span>
            <span class="summary-value">{{ $order->delivery_address }}, {{ $order->delivery_city }}</span>
        </div>
    </div>

    {{-- Order Items Table --}}
    <table class="table">
        <thead>
            <tr>
                <th>Creations</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>
                        <div style="font-weight: bold; color: #4A2E22;">{{ $item->product_title }}</div>
                        @php
                            $variantLabel = $item->variant_label ?? null;
                            $options = $item->options_snapshot ?? [];
                        @endphp
                        @if($variantLabel)
                            <div style="font-size: 11px; color: #8C7568;">Size: {{ $variantLabel }}</div>
                        @endif
                        @foreach($options as $option)
                            <div style="font-size: 11px; color: #8C7568;">
                                {{ $option['group_name'] ?? 'Option' }}: {{ $option['value_name'] ?? '' }}
                                @if(!empty($option['price_adjustment']) && (float) $option['price_adjustment'] != 0)
                                    ({{ (float) $option['price_adjustment'] > 0 ? '+' : '' }}{{ order_price($order, $option['price_adjustment']) }})
                                @endif
                            </div>
                        @endforeach
                        @foreach(\App\Services\ProductCustomizationService::lines($item->customization) as $custom)
                            <div style="font-size: 11px; color: #8C7568;">{{ $custom['label'] }}: {{ $custom['value'] }}</div>
                        @endforeach
                        @if($item->special_request)
                            <div style="font-size: 11px; color: #8C7568;">Note: {{ $item->special_request }}</div>
                        @endif
                        <div style="font-size: 11px; color: #8C7568;">SKU: {{ $item->product_sku }}</div>
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ order_price($order, $item->total_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pricing Totals --}}
    <div class="total-box">
        Subtotal: <span style="font-weight: normal; color: #2E1B12; margin-right: 15px;">{{ order_price($order, $order->subtotal) }}</span>
        Delivery: <span style="font-weight: normal; color: #2E1B12; margin-right: 15px;">{{ $order->delivery_fee_label }}</span>
        @if($order->discount_amount > 0)
            Discount: <span style="font-weight: normal; color: #8FBF9F; margin-right: 15px;">-{{ order_price($order, $order->discount_amount) }}</span>
        @endif
        @if($order->tax_amount > 0)
            Tax: <span>{{ order_price($order, $order->tax_amount) }}</span>
        @endif
        <div style="margin-top: 10px;">
            Grand Total: <span class="total-value">{{ order_price($order, $order->grand_total) }}</span>
        </div>
    </div>

    @if($order->needs_measurements)
        <div style="margin: 24px 0; padding: 16px 18px; background: #f6f5f2; border-left: 3px solid #17120f;">
            <strong>Next step: send your measurements</strong><br>
            Your order includes a Custom Size or made-to-measure piece. Please send your body measurements with order number <strong>{{ $order->order_number }}</strong> so we can start production.
            <div style="margin-top: 10px;">
                <a href="{{ route('guide.how-to-measure') }}">How to measure</a> &nbsp;·&nbsp;
                <a href="{{ route('guide.measurement-form', ['order' => $order->order_number]) }}">Send your measurements</a>
            </div>
        </div>
    @endif

    <center>
        {{-- Manual methods (bank transfer, wallets) have instructions; card gateways and COD do not. --}}
        @if($order->payment_method !== 'cod' && ! ($order->payment_instructions_snapshot['gateway'] ?? false))
            <a href="{{ route('checkout.payment-instructions', $order->id) }}" class="button" style="margin-right: 10px;">
                View Payment Instructions
            </a>
        @endif
        <a href="{{ route('orders.track-guest', $order->tracking_token) }}" class="button">
            Track Your Order
        </a>
    </center>
@endsection
