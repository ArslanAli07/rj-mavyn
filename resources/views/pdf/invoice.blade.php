<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        /* 58mm roll with 3mm margins: 32 Courier columns at 7.5pt (0.6em advance) = 144pt of 147pt. */
        @page {
            margin: 3mm 3mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 7.5pt;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        /* Preserve column padding inside each receipt line only; newlines between lines must not render. */
        div {
            white-space: pre;
            height: 1.3em;
            overflow: hidden;
        }
    </style>
</head>
<body>
@php
    // Receipt width in characters; the controller sizes the paper to fit exactly this many columns.
    $LW = 32;

    // Closures (not named functions) so the view can render more than once per request.
    $padLine = function ($left, $right, $width = 32) {
        $left = (string) $left;
        $right = (string) $right;
        $space = $width - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            $left = mb_substr($left, 0, max($width - mb_strlen($right) - 2, 0)) . '.';
            $space = $width - mb_strlen($left) - mb_strlen($right);
        }
        return $left . str_repeat(' ', max($space, 1)) . $right;
    };

    $centerLine = function ($text, $width = 32) {
        $text = (string) $text;
        if (mb_strlen($text) >= $width) return $text;
        return str_repeat(' ', (int) floor(($width - mb_strlen($text)) / 2)) . $text;
    };

    $ruleLine = fn ($width = 32, $char = '-') => str_repeat($char, $width);

    // Word-wrap to the receipt width (hard-breaking long words) so nothing is clipped at the page edge.
    $wrap = function ($text, $width = 32, $indent = '') {
        $lines = [];
        foreach (preg_split('/\R/u', trim((string) $text)) as $paragraph) {
            $current = '';
            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $room = $width - mb_strlen($indent);
                while (mb_strlen($word) > $room) {
                    if ($current !== '') { $lines[] = $indent . $current; $current = ''; }
                    $lines[] = $indent . mb_substr($word, 0, $room);
                    $word = mb_substr($word, $room);
                }
                if ($current === '') { $current = $word; }
                elseif (mb_strlen($indent . $current . ' ' . $word) <= $width) { $current .= ' ' . $word; }
                else { $lines[] = $indent . $current; $current = $word; }
            }
            if ($current !== '') { $lines[] = $indent . $current; }
        }
        return $lines ?: [''];
    };

    $shipping = $order->shipping_snapshot;
    $deliveryAmount = $order->delivery_fee > 0
        ? order_price($order, $order->delivery_fee)
        : (($shipping || $order->delivery_zone_id) ? 'Free' : 'To confirm');
    $address = collect([$order->delivery_address, trim(implode(' ', array_filter([$order->delivery_city, $order->delivery_postal_code]))), $order->delivery_country])
        ->filter(fn ($part) => trim((string) $part) !== '')->implode("\n");

    $siteName = $order->store_name;
    $sitePhone = trim((string) setting('site_phone'));
@endphp
@foreach($wrap(mb_strtoupper($siteName), $LW) as $line)
<div>{{ $centerLine($line, $LW) }}</div>
@endforeach
@foreach($wrap($order->brand_snapshot['tagline'] ?? '', $LW) as $line)
<div>{{ $centerLine($line, $LW) }}</div>
@endforeach

@if($sitePhone)
<div>{{ $centerLine($sitePhone, $LW) }}</div>
@endif
<div>{{ $ruleLine($LW) }}</div>
<div>{{ $centerLine('INVOICE', $LW) }}</div>
@foreach($wrap($order->order_number, $LW) as $line)
<div>{{ $centerLine($line, $LW) }}</div>
@endforeach
<div>{{ $ruleLine($LW) }}</div>
<div>DATE</div>
<div>{{ store_time($order->created_at)->format('d M Y h:i A T') }}</div>
<div></div>
<div>PAYMENT</div>
@foreach($wrap($order->payment_method_label . ' (' . ucfirst($order->payment_status) . ')', $LW) as $line)
<div>{{ $line }}</div>
@endforeach
<div></div>
<div>CUSTOMER</div>
@foreach($wrap($order->customer_name, $LW) as $line)
<div>{{ $line }}</div>
@endforeach
<div></div>
<div>PHONE</div>
@foreach($wrap($order->customer_phone, $LW) as $line)
<div>{{ $line }}</div>
@endforeach
@if($address !== '')
<div></div>
<div>ADDRESS</div>
@foreach($wrap($address, $LW) as $line)
<div>{{ $line }}</div>
@endforeach
@endif
@if($shipping)
<div></div>
<div>DELIVERY METHOD</div>
@foreach($wrap($shipping['label'] ?? $order->delivery_area, $LW) as $line)
<div>{{ $line }}</div>
@endforeach
@endif
<div>{{ $ruleLine($LW) }}</div>
<div>{{ $padLine('ITEM', 'AMOUNT', $LW) }}</div>
<div>{{ $ruleLine($LW) }}</div>
@foreach($order->items as $item)
@foreach($wrap(mb_strtoupper($item->product_title), $LW) as $line)
<div>{{ $line }}</div>
@endforeach
@if($item->variant_label)
@foreach($wrap($item->variant_label, $LW, '  ') as $line)
<div>{{ $line }}</div>
@endforeach
@endif
@foreach($item->options_snapshot ?? [] as $option)
@php
    $optLine = '  ' . ($option['group_name'] ?? 'Option') . ': ' . ($option['value_name'] ?? '');
    if (!empty($option['price_adjustment']) && (float) $option['price_adjustment'] != 0) {
        $optLine .= ' (' . ((float) $option['price_adjustment'] > 0 ? '+' : '') . order_price($order, $option['price_adjustment']) . ')';
    }
@endphp
@foreach($wrap($optLine, $LW, '  ') as $line)
<div>{{ $line }}</div>
@endforeach
@endforeach
@foreach(\App\Services\ProductCustomizationService::lines($item->customization) as $custom)
@foreach($wrap($custom['label'] . ': ' . $custom['value'], $LW, '  ') as $line)
<div>{{ $line }}</div>
@endforeach
@endforeach
@if($item->special_request)
@foreach($wrap('Note: ' . $item->special_request, $LW, '  ') as $line)
<div>{{ $line }}</div>
@endforeach
@endif
<div>{{ $padLine($item->quantity . ' x ' . order_price($order, $item->unit_price), order_price($order, $item->total_price), $LW) }}</div>
<div></div>
@endforeach
<div>{{ $ruleLine($LW) }}</div>
<div>{{ $padLine('Subtotal', order_price($order, $order->subtotal), $LW) }}</div>
@if($order->discount_amount > 0)
<div>{{ $padLine('Discount', '-' . order_price($order, $order->discount_amount), $LW) }}</div>
@endif
@if($order->tax_amount > 0)
<div>{{ $padLine('Tax', order_price($order, $order->tax_amount), $LW) }}</div>
@endif
<div>{{ $padLine('Delivery', $deliveryAmount, $LW) }}</div>
<div>{{ $ruleLine($LW) }}</div>
<div>{{ $padLine('TOTAL', order_price($order, $order->grand_total), $LW) }}</div>
<div>{{ $ruleLine($LW) }}</div>
@if($order->order_notes)
<div>NOTES:</div>
@foreach($wrap($order->order_notes, $LW) as $line)
<div>{{ $line }}</div>
@endforeach
<div>{{ $ruleLine($LW) }}</div>
@endif
<div>{{ $centerLine('Thank you for choosing', $LW) }}</div>
@foreach($wrap($siteName . '.', $LW) as $line)
<div>{{ $centerLine($line, $LW) }}</div>
@endforeach
@if($sitePhone)
@foreach($wrap('Questions? ' . $sitePhone, $LW) as $line)
<div>{{ $centerLine($line, $LW) }}</div>
@endforeach
@endif
</body>
</html>