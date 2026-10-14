<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 4mm 4mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10pt;
            line-height: 1.3;
            white-space: pre;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
@php
    // Calibrate: print str_repeat('X', $LW) and adjust LW until it exactly fills one line
    $LW = 32;

    function padLine($left, $right, $width = 32) {
        $left = (string) $left;
        $right = (string) $right;
        $space = $width - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            $left = mb_substr($left, 0, max($width - mb_strlen($right) - 2, 0)) . '.';
            $space = $width - mb_strlen($left) - mb_strlen($right);
        }
        return $left . str_repeat(' ', max($space, 1)) . $right;
    }

    function centerLine($text, $width = 32) {
        $text = (string) $text;
        if (mb_strlen($text) >= $width) return $text;
        $pad = (int) floor(($width - mb_strlen($text)) / 2);
        return str_repeat(' ', $pad) . $text;
    }

    function ruleLine($width = 32, $char = '-') {
        return str_repeat($char, $width);
    }

    $siteName = $order->store_name;
    $sitePhone = trim((string) setting('site_phone'));
@endphp
<div>{{ centerLine(strtoupper($siteName), $LW) }}</div>
<div>{{ centerLine($order->brand_snapshot['tagline'] ?? '', $LW) }}</div>

@if($sitePhone)
<div>{{ centerLine($sitePhone, $LW) }}</div>
@endif
<div>{{ ruleLine($LW) }}</div>
<div>{{ centerLine('INVOICE', $LW) }}</div>
<div>{{ centerLine($order->order_number, $LW) }}</div>
<div>{{ ruleLine($LW) }}</div>
<div>DATE</div>
<div>{{ $order->created_at->tz('Asia/Karachi')->format('d M Y h:i A') }}</div>
<div></div>
<div>PAYMENT</div>
<div>{{ $order->payment_method_label }} ({{ ucfirst($order->payment_status) }})</div>
<div></div>
<div>CUSTOMER</div>
<div>{{ $order->customer_name }}</div>
<div></div>
<div>PHONE</div>
<div>{{ $order->customer_phone }}</div>
@if($order->delivery_address)
<div></div>
<div>ADDRESS</div>
<div>{{ $order->delivery_address }}</div>
@endif
<div>{{ ruleLine($LW) }}</div>
<div>{{ padLine('ITEM', 'AMOUNT', $LW) }}</div>
<div>{{ ruleLine($LW) }}</div>
@foreach($order->items as $item)
<div>{{ strtoupper($item->product_title) }}</div>
@if($item->variant_label)
<div>  {{ $item->variant_label }}</div>
@endif
@foreach($item->options_snapshot ?? [] as $option)
@php
    $optLine = '  ' . ($option['group_name'] ?? 'Option') . ': ' . ($option['value_name'] ?? '');
    if (!empty($option['price_adjustment']) && (float) $option['price_adjustment'] != 0) {
        $optLine .= ' (' . ((float) $option['price_adjustment'] > 0 ? '+' : '') . format_price($option['price_adjustment']) . ')';
    }
@endphp
<div>{{ $optLine }}</div>
@endforeach
@if($item->special_request)
<div>  Note: {{ $item->special_request }}</div>
@endif
<div>{{ padLine($item->quantity . ' x ' . order_price($order, $item->unit_price), order_price($order, $item->total_price), $LW) }}</div>
<div></div>
@endforeach
<div>{{ ruleLine($LW) }}</div>
<div>{{ padLine('Subtotal', order_price($order, $order->subtotal), $LW) }}</div>
@if($order->discount_amount > 0)
<div>{{ padLine('Discount', '-' . order_price($order, $order->discount_amount), $LW) }}</div>
@endif
@if($order->tax_amount > 0)
<div>{{ padLine('Tax', order_price($order, $order->tax_amount), $LW) }}</div>
@endif
<div>{{ padLine('Delivery', $order->delivery_fee > 0 ? order_price($order, $order->delivery_fee) : (is_null($order->delivery_zone_id) ? 'To confirm' : 'Free'), $LW) }}</div>
<div>{{ ruleLine($LW) }}</div>
<div>{{ padLine('TOTAL', order_price($order, $order->grand_total), $LW) }}</div>
<div>{{ ruleLine($LW) }}</div>
@if($order->order_notes)
<div>NOTES:</div>
<div>{{ $order->order_notes }}</div>
<div>{{ ruleLine($LW) }}</div>
@endif
<div>{{ centerLine('Thank you for choosing', $LW) }}</div>
<div>{{ centerLine($siteName . '.', $LW) }}</div>
@if($sitePhone)
<div>{{ centerLine('Questions? ' . $sitePhone, $LW) }}</div>
@endif
</body>
</html>