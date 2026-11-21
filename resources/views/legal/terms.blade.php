@php
    $g = \App\Http\Controllers\GuideController::facts();
    $w = $g['ways'];
    $cur = setting('store_currency_symbol', '$');
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
@endphp
<x-legal.page title="Terms & Conditions" page-key="terms" custom-key="terms_policy"
    :toc="['who' => 'Who we are', 'order' => 'Orders', 'prices' => 'Prices', 'cancel' => 'Cancelling', 'delivery' => 'Production and delivery', 'returns' => 'Returns', 'gear' => 'Protective gear', 'designs' => 'Designs', 'liability' => 'Responsibility', 'contact' => 'Contact']"
    :see="['Privacy policy' => route('privacy'), 'Refund policy' => route('returns'), 'Shipping policy' => route('shipping')]"
    :description="'The terms that apply when you buy from '.$store.': orders, prices, production, delivery, returns and your rights.'"
    intro="These terms apply when you buy from this website. Please read them before you order. By placing an order, you agree to them.">
    <section id="who">
        <h2>1. Who we are</h2>
        <p>This website is run by {{ $store }}. Your contract is with us.</p>
        @include('legal._who')
    </section>
    <section id="order">
        <h2>2. Your order and our contract</h2>
        <ul>
            <li>When you place an order, we email you to confirm that we have received it. This email does not yet mean we have accepted your order.</li>
            <li>Every item is made to order. Before production, our team checks the size and details of your order and contacts you if anything needs a second look.</li>
            <li>The contract between us starts when we accept your order and start production.</li>
            <li>We may decline an order, for example if a price on the website was clearly wrong, if we cannot deliver to your address, or if a payment check fails. If we decline your order, we tell you by email and refund any payment in full.</li>
        </ul>
    </section>
    <section id="prices">
        <h2>3. Prices and payment</h2>
        <ul>
            <li>Prices are shown on the product pages. The shipping cost for your country is shown at checkout before you pay.@if($g['free_shipping_over'] !== '') Shipping is free worldwide on orders over {{ $cur }}{{ $g['free_shipping_over'] }}.@endif</li>
            <li>The price of each option you choose, such as leather type or extras, is shown on the product page and added to the price.</li>
            <li>You pay the full price when you order, by card through Stripe. Your card details go straight to Stripe; we never see or store your full card number.</li>
            <li>Our prices do not include import duties or local taxes. If your country charges them, you pay them to the carrier or customs when the parcel arrives.</li>
        </ul>
    </section>
    <section id="cancel">
        <h2>4. Cancelling an order</h2>
        <ul>
            <li>You can cancel your order free of charge until production starts. <a href="{{ route('contact') }}">Contact us</a> with your order number and we refund you in full to the payment method you used.</li>
            <li>Once production has started, Custom Size and Custom Made orders cannot be cancelled, because they are made to your measurements or design.</li>
            <li>Once production has started on a Standard Size order, you cannot cancel it, but you can return the item after delivery under our <a href="{{ route('returns') }}">refund policy</a>.</li>
        </ul>
    </section>
    <section id="delivery">
        <h2>5. Production and delivery</h2>
        <ul>
            <li>Usual production times: Standard Size {{ $w['standard']['lead_time'] }}, Custom Size {{ $w['custom_size']['lead_time'] }}, Custom Made {{ $w['custom_made']['lead_time'] }}. Delivery usually takes a further {{ $g['delivery_time'] }}. These are estimates, not guaranteed dates.</li>
            <li>We email you a tracking number when your order ships. See our <a href="{{ route('shipping') }}">shipping policy</a>.</li>
            <li>Please check your shipping address before you order. If a parcel is returned to us because the address was wrong or it was not collected, we contact you about sending it again, and you may have to pay the extra shipping.</li>
            <li>The goods are yours, and at your risk, once they are delivered.</li>
            <li>Handmade leather can vary slightly in colour and grain from the photos. This is part of its natural character, not a fault.</li>
        </ul>
    </section>
    <section id="returns">
        <h2>6. Returns, faults and refunds</h2>
        <p>Returns, faulty items and refunds are covered by our <a href="{{ route('returns') }}">refund policy</a>, which is part of these terms. In short:</p>
        <ul>
            <li>Unused Standard Size items can be returned within {{ $g['fit_window_days'] }} days of delivery. A 25% restocking fee applies and you pay the return shipping.</li>
            <li>Custom Size and Custom Made items cannot be returned unless they are faulty or we made a mistake.</li>
            <li>If an item is faulty or we made a mistake, we repair, replace or refund it and pay the shipping.</li>
            <li>We refund you within 14 days of receiving your return.</li>
        </ul>
    </section>
    <section id="gear">
        <h2>7. Protective gear</h2>
        <ul>
            <li>Our motorcycle gear comes with armour as described on the product page. Armour reduces the force of an impact, but no riding gear can prevent every injury.</li>
            <li>Check that your gear fits well and that all armour is in place before you ride. After a crash, check your gear and armour before riding again.</li>
        </ul>
    </section>
    <section id="designs">
        <h2>8. Designs, logos and your files</h2>
        <ul>
            <li>Some designs are inspired by famous jackets and race suits. They are our own designs, not official team, brand or studio products.</li>
            <li>If you send us a design, logo, name or image to use on your order, you confirm that you have the right to use it. We may refuse a design that we believe infringes someone else's rights.</li>
        </ul>
    </section>
    <section id="liability">
        <h2>9. Our responsibility to you</h2>
        <ul>
            <li>We are responsible for loss or damage that you suffer as a foreseeable result of us breaking these terms. We are not responsible for loss that was not foreseeable, or for business losses.</li>
            <li>We are not responsible for delays caused by events outside our reasonable control, such as strikes, carrier delays, customs, natural disasters or system outages. We will tell you if this affects your order.</li>
            <li>Nothing in these terms limits any rights you have as a consumer that cannot be limited by law.</li>
        </ul>
    </section>
    <section id="contact">
        <h2>10. Changes, disputes and contact</h2>
        <ul>
            <li>We may update these terms from time to time. The terms that apply to your order are the ones shown on this page when you placed it.</li>
            <li>If you have a complaint, please contact us first. Most problems can be solved quickly by message.</li>
        </ul>
        <p>Questions or complaints: <a href="{{ route('contact') }}">contact us</a>. We reply within 24 hours.</p>
    </section>
</x-legal.page>
