@extends('emails.layout')

@section('title', 'Order Status Update — Happy Tea')

@section('content')
    <div class="welcome">Hello, {{ $order->customer_name }}!</div>
    
    <p class="intro">
        Your order status has been updated. Here are the latest details regarding your order:
    </p>

    <center>
        <div class="status-badge status-{{ $order->status }}">
            Current Status: {{ $order->status_label }}
        </div>
    </center>

    {{-- Progress step bar (only if not cancelled) --}}
    @if($order->status !== 'cancelled')
        <div class="progress-container">
            <div class="steps">
                {{-- Confirmed Step --}}
                <div class="step {{ in_array($order->status, ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered']) ? 'active' : '' }} {{ in_array($order->status, ['preparing', 'out_for_delivery', 'delivered']) ? 'completed' : '' }}">
                    <div class="step-icon">1</div>
                    Confirmed
                </div>
                
                {{-- Preparing Step --}}
                <div class="step {{ in_array($order->status, ['preparing', 'out_for_delivery', 'delivered']) ? 'active' : '' }} {{ in_array($order->status, ['out_for_delivery', 'delivered']) ? 'completed' : '' }}">
                    <div class="step-icon">2</div>
                    Preparing
                </div>
                
                {{-- Out For Delivery Step --}}
                <div class="step {{ in_array($order->status, ['out_for_delivery', 'delivered']) ? 'active' : '' }} {{ in_array($order->status, ['delivered']) ? 'completed' : '' }}">
                    <div class="step-icon">3</div>
                    On the Way
                </div>
                
                {{-- Delivered Step --}}
                <div class="step {{ $order->status === 'delivered' ? 'active' : '' }}">
                    <div class="step-icon">4</div>
                    Delivered
                </div>
            </div>
        </div>
    @endif

    {{-- Notes from Admin if provided --}}
    @if($order->admin_notes)
        <div class="admin-note">
            <strong>Note from Happy Tea Team:</strong><br>
            "{{ $order->admin_notes }}"
        </div>
    @endif

    {{-- Summary details --}}
    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Order Number:</span>
            <span class="summary-value">{{ $order->order_number }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Grand Total:</span>
            <span class="summary-value">{{ format_price($order->grand_total) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Delivery Address:</span>
            <span class="summary-value">{{ $order->delivery_address }}, {{ $order->delivery_city }}</span>
        </div>
    </div>

    <center>
        <a href="{{ route('orders.track-guest', $order->tracking_token) }}" class="button">
            Track Your Order Realtime
        </a>
    </center>
@endsection
