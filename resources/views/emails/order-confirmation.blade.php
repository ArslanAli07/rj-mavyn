@extends('emails.layout')

@section('title', 'Order Confirmation — Happy Tea')

@section('content')
    <div class="welcome">Hello, {{ $order->customer_name }}!</div>
    <p class="intro">
        Thank you for choosing Happy Tea. We have successfully registered your order. Our team will contact you shortly on your provided phone number to confirm the dispatch details.
    </p>

    {{-- Summary details --}}
    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Order Number:</span>
            <span class="summary-value">{{ $order->order_number }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Payment Method:</span>
            <span class="summary-value" style="text-transform: uppercase;">{{ $order->payment_method }}</span>
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
                                    ({{ (float) $option['price_adjustment'] > 0 ? '+' : '' }}{{ format_price($option['price_adjustment']) }})
                                @endif
                            </div>
                        @endforeach
                        @if($item->special_request)
                            <div style="font-size: 11px; color: #8C7568;">Note: {{ $item->special_request }}</div>
                        @endif
                        <div style="font-size: 11px; color: #8C7568;">SKU: {{ $item->product_sku }}</div>
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ format_price($item->total_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pricing Totals --}}
    <div class="total-box">
        Subtotal: <span style="font-weight: normal; color: #2E1B12; margin-right: 15px;">{{ format_price($order->subtotal) }}</span>
        Delivery: <span style="font-weight: normal; color: #2E1B12; margin-right: 15px;">{{ $order->delivery_fee > 0 ? format_price($order->delivery_fee) : (is_null($order->delivery_zone_id) ? 'To be confirmed' : 'Free') }}</span>
        @if($order->discount_amount > 0)
            Discount: <span style="font-weight: normal; color: #8FBF9F; margin-right: 15px;">-{{ format_price($order->discount_amount) }}</span>
        @endif
        <div style="margin-top: 10px;">
            Grand Total: <span class="total-value">{{ format_price($order->grand_total) }}</span>
        </div>
    </div>

    <center>
        @if($order->payment_method !== 'cod')
            <a href="{{ route('checkout.payment-instructions', $order->id) }}" class="button" style="margin-right: 10px;">
                View Payment Instructions
            </a>
        @endif
        <a href="{{ route('orders.track-guest', $order->tracking_token) }}" class="button">
            Track Your Order
        </a>
    </center>
@endsection
