<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 4mm 4mm;
        }
        * {
            box-sizing: border-box;
            page-break-inside: avoid;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
            font-size: 10pt;
            line-height: 1.25;
            text-align: center;
        }
        .center {
            text-align: center;
        }
        .brand {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .muted {
            font-size: 10pt;
            color: #111;
        }
        .rule {
            border-top: 1px dashed #000;
            margin: 5px 0;
            height: 0;
        }
        
        /* 1-Column Metadata Styles */
        .meta-block {
            margin-bottom: 4px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .meta-label {
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            color: #333;
        }
        .meta-value {
            font-size: 10pt;
        }

        /* Items & Totals Table */
        .row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .row td {
            padding: 2px 0;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .col-item {
            width: 50%;
        }
        .col-amount {
            width: 50%;
            text-align: right;
            white-space: nowrap;
        }
        
        /* Larger Item Details */
        .item-name {
            font-weight: bold;
            font-size: 10pt;
        }
        .item-meta {
            font-size: 10pt;
            padding-left: 2px;
        }
        .right {
            text-align: right;
            white-space: nowrap;
        }
        .total-label {
            font-weight: bold;
            font-size: 10pt;
        }
        .total-value {
            font-weight: bold;
            font-size: 10pt;
            text-align: right;
        }
        .notes {
            font-size: 10pt;
        }
        .cut {
            text-align: center;
            font-size: 9px;
            margin-top: 6px;
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

    <!-- 1-Column Layout for Order Details -->
    <div class="meta-block">
        <div class="meta-label">Date</div>
        <div class="meta-value">{{ $order->created_at->tz('Asia/Karachi')->format('d M Y h:i A') }}</div>
    </div>
    <div class="meta-block">
        <div class="meta-label">Payment</div>
        <div class="meta-value">{{ $order->payment_method_label }} ({{ ucfirst($order->payment_status) }})</div>
    </div>
    <div class="meta-block">
        <div class="meta-label">Customer</div>
        <div class="meta-value">{{ $order->customer_name }}</div>
    </div>
    <div class="meta-block">
        <div class="meta-label">Phone</div>
        <div class="meta-value">{{ $order->customer_phone }}</div>
    </div>
    @if($order->delivery_address)
        <div class="meta-block">
            <div class="meta-label">Address</div>
            <div class="meta-value">{{ $order->delivery_address }}</div>
        </div>
    @endif

    <div class="rule"></div>

    <!-- Items Section (25% Item Width) -->
    <table class="row">
        <tr>
            <td class="col-item"><strong>Item</strong></td>
            <td class="col-amount"><strong>Amount</strong></td>
        </tr>
        @foreach($order->items as $item)
            <tr>
                <td class="col-item">
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
                    <div class="item-meta">{{ $item->quantity }} x {{ order_price($order, $item->unit_price) }}</div>
                </td>
                <td class="col-amount">{{ order_price($order, $item->total_price) }}</td>
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
</body>
</html>