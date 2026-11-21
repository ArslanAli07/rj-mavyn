@php
    $g = \App\Http\Controllers\GuideController::facts();
    $w = $g['ways'];
    $cur = setting('store_currency_symbol', '$');
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
@endphp
<x-legal.page title="Shipping Policy" heading="Production Time, Shipping & Delivery" variant="policy" page-key="shipping" custom-key="shipping_policy"
    :toc="['making' => 'Production time', 'delivery' => 'Delivery time', 'cost' => 'Shipping cost', 'tracking' => 'Tracking', 'duties' => 'Duties and taxes', 'address' => 'Your address']"
    :see="['Returns and refunds' => route('returns'), 'Size chart' => route('guide.size-guide'), 'How to measure' => route('guide.how-to-measure'), 'Customer service' => route('contact')]"
    :description="'Production times, delivery times, shipping costs and tracking for orders from '.$store.'.'"
    intro="Every piece is made to order in our workshop in Pakistan and shipped straight to you. Here is how long each step takes and what it costs.">
    <section id="making">
        <h2>How long it takes to make your order</h2>
        <div class="lg-table-wrap">
            <table>
                <thead><tr><th>Order type</th><th>Ready to ship in</th></tr></thead>
                <tbody>
                    <tr><td>{{ $w['standard']['title'] }}<small>A size from the size chart</small></td><td>{{ $w['standard']['lead_time'] }}</td></tr>
                    <tr><td>{{ $w['custom_size']['title'] }}<small>Our design, cut to your measurements</small></td><td>{{ $w['custom_size']['lead_time'] }}</td></tr>
                    <tr><td>{{ $w['custom_made']['title'] }}<small>Your own design, colours or changes</small></td><td>{{ $w['custom_made']['lead_time'] }}</td></tr>
                </tbody>
            </table>
        </div>
        <p>The time starts when we confirm your order. For Custom Size and Custom Made orders, it starts when we have your measurements and all design details.</p>
    </section>
    <section id="delivery">
        <h2>Delivery time</h2>
        <p>After your order ships, delivery usually takes {{ $g['delivery_time'] }}. It depends on your country and on customs.</p>
    </section>
    <section id="cost">
        <h2>Shipping cost</h2>
        @if($g['free_shipping_over'] !== '')
            <p><strong>Free worldwide shipping</strong> on orders over {{ $cur }}{{ $g['free_shipping_over'] }}.</p>
            <p>Below {{ $cur }}{{ $g['free_shipping_over'] }}, shipping is calculated at checkout from the weight of the parcel and your address.</p>
        @else
            <p>Shipping is calculated at checkout from the weight of the parcel and your address.</p>
        @endif
    </section>
    <section id="tracking">
        <h2>Carriers and tracking</h2>
        <ul>
            <li>We ship with a tracked international courier. The carrier depends on your address and your order.</li>
            <li>We email you the tracking number when your order ships.</li>
        </ul>
    </section>
    <section id="duties">
        <h2>Import duties and taxes</h2>
        <ul>
            <li>Our prices do not include import duties or local taxes.</li>
            <li>Your country may charge import duties or taxes when the parcel arrives. You pay these to the carrier or to customs. We cannot control them.</li>
            <li>Customs can take longer in some countries, which may delay delivery.</li>
            <li>If a parcel is refused because of customs charges, any costs this causes are paid by the buyer.</li>
        </ul>
    </section>
    <section id="address">
        <h2>Your address</h2>
        <ul>
            <li>Please add a phone number to your shipping address. The carrier may need it.</li>
            <li>We cannot deliver to P.O. boxes.</li>
        </ul>
    </section>
</x-legal.page>
