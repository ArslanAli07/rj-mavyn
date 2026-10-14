<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 4mm 3mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
            font-size: 15px;
            line-height: 1.35;
        }
        .center {
            text-align: center;
        }
        .brand {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .muted {
            font-size: 8px;
            color: #222;
        }
        .rule {
            border-top: 1px dashed #000;
            margin: 6px 0;
            height: 0;
        }
        .row {
            width: 100%;
            border-collapse: collapse;
        }
        .row td {
            padding: 1px 0;
            vertical-align: top;
        }
        .label {
            width: 38%;
        }
        .item-name {
            font-weight: bold;
            font-size: 9px;
        }
        .item-meta {
            font-size: 8px;
            padding-left: 4px;
        }
        .qty {
            white-space: nowrap;
        }
        .right {
            text-align: right;
            white-space: nowrap;
        }
        .total-label {
            font-weight: bold;
            font-size: 10px;
        }
        .total-value {
            font-weight: bold;
            font-size: 10px;
            text-align: right;
        }
        .notes {
            font-size: 8px;
        }
        .cut {
            text-align: center;
            font-size: 8px;
            margin-top: 8px;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    @php
        $siteName = $order->store_name;
        $siteAddress = trim((string) setting('site_address'));
        $sitePhone = trim((string) setting('site_phone'));
    @endphp

    <div class="center">
        <div class="brand">{{ $siteName }}</div>
        <div class="muted">{{ $order->brand_snapshot['tagline'] ?? '' }}</div>
        @if($siteAddress)
            <div class="muted">{{ $siteAddress }}</div>
        @endif
        @if($sitePhone)
            <div class="muted">{{ $sitePhone }}</div>
        @endif
    </div>

    <div class="rule"></div>

    <div class="center">
        <strong>INVOICE</strong><br>
        {{ $order->order_number }}
    </div>

    <div class="rule"></div>

    <table class="row">
        <tr>
            <td class="label">Date</td>
            <td style="text-align: right;">{{ $order->created_at->tz('Asia/Karachi')->format('d M Y h:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Payment</td>
            <td style="text-align: right;">{{ $order->payment_method_label }} ({{ ucfirst($order->payment_status) }})</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td style="text-align: right;">{{ $order->status_label }}</td>
        </tr>
        <tr>
            <td class="label">Customer</td>
            <td style="text-align: right;">{{ $order->customer_name }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td>
            <td style="text-align: right;">{{ $order->customer_phone }}</td>
        </tr>
        <tr>
            <td class="label">Area</td>
            <td style="text-align: right;">{{ $order->delivery_area }}{{ $order->delivery_city ? ', '.$order->delivery_city : '' }}</td>
        </tr>
        @if($order->delivery_address)
            <tr>
                <td class="label">Address</td>
                <td style="text-align: right;">{{ $order->delivery_address }}</td>
            </tr>
        @endif
    </table>

    <div class="rule"></div>

    <table class="row">
        <tr>
            <td><strong>Item</strong></td>
            <td class="right" style="width: 28%;"><strong>Amount</strong></td>
        </tr>
        @foreach($order->items as $item)
            <tr>
                <td>
                    <div class="item-name">{{ $item->product_title }}</div>
                    @if($item->variant_label)
                        <div class="item-meta">{{ $item->variant_label }}</div>
                    @endif
                    @foreach($item->options_snapshot ?? [] as $option)
                        <div class="item-meta">
                            {{ $option['group_name'] ?? 'Option' }}: {{ $option['value_name'] ?? '' }}
                            @if(!empty($option['price_adjustment']) && (float) $option['price_adjustment'] != 0)
                                ({{ (float) $option['price_adjustment'] > 0 ? '+' : '' }}{{ format_price($option['price_adjustment']) }})
                            @endif
                        </div>
                    @endforeach
                    @if($item->special_request)
                        <div class="item-meta">Note: {{ $item->special_request }}</div>
                    @endif
                    <div class="item-meta qty">{{ $item->quantity }} x {{ order_price($order, $item->unit_price) }}</div>
                </td>
                <td class="right">{{ order_price($order, $item->total_price) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="rule"></div>

    <table class="row">
        <tr>
            <td>Subtotal</td>
            <td class="right">{{ order_price($order, $order->subtotal) }}</td>
        </tr>
        @if($order->discount_amount > 0)
            <tr>
                <td>Discount</td>
                <td class="right">-{{ order_price($order, $order->discount_amount) }}</td>
            </tr>
        @endif
        @if($order->tax_amount > 0)
            <tr>
                <td>Tax</td>
                <td class="right">{{ order_price($order, $order->tax_amount) }}</td>
            </tr>
        @endif
        <tr>
            <td>Delivery</td>
            <td class="right">
                @if($order->delivery_fee > 0)
                    {{ order_price($order, $order->delivery_fee) }}
                @elseif(is_null($order->delivery_zone_id))
                    To confirm
                @else
                    Free
                @endif
            </td>
        </tr>
        <tr>
            <td class="total-label">TOTAL</td>
            <td class="total-value">{{ order_price($order, $order->grand_total) }}</td>
        </tr>
    </table>

    @if($order->order_notes)
        <div class="rule"></div>
        <div class="notes"><strong>Notes:</strong> {{ $order->order_notes }}</div>
    @endif

    <div class="rule"></div>

    <div class="center muted">
        Thank you for choosing {{ $siteName }}.
        @if($sitePhone)
            <br>Questions? {{ $sitePhone }}
        @endif
    </div>
    <div class="cut">* * *</div>
</body>
</html>
