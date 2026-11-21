@php
    $g = \App\Http\Controllers\GuideController::facts();
    $days = $g['fit_window_days'];
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
@endphp
<x-legal.page title="Refund Policy" heading="Returns, Replacements & Refunds" variant="policy" page-key="returns" custom-key="returns_policy"
    :toc="['glance' => 'At a glance', 'standard' => 'Standard Size', 'custom' => 'Custom orders', 'faulty' => 'Faulty items', 'how' => 'How to return', 'refunds' => 'Refunds']"
    :see="['Shipping and delivery times' => route('shipping'), 'Size chart' => route('guide.size-guide'), 'Customer service' => route('contact'), 'Terms of service' => route('terms')]"
    :description="'What you can return to '.$store.', how to return it, and how refunds work for standard, custom and faulty items.'"
    intro="Every piece is made to order for you. Please check the size guide before you order. If something is not right, this page explains what you can return and how.">
    <section id="glance">
        <h2>At a glance</h2>
        <div class="lg-table-wrap">
            <table>
                <thead><tr><th>Your order</th><th>Can I return it?</th></tr></thead>
                <tbody>
                    <tr><td>Standard Size, unused</td><td>Yes, within {{ $days }} days of delivery. A 25% restocking fee applies, and you pay the return shipping.</td></tr>
                    <tr><td>Custom Size or Custom Made</td><td>No, unless it is faulty or we made a mistake.</td></tr>
                    <tr><td>Faulty item or our mistake</td><td>Yes. We repair, replace or refund it, and we pay the shipping.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    <section id="standard">
        <h2>Returning a Standard Size item</h2>
        <ul>
            <li>Tell us within <strong>{{ $days }} days</strong> of delivery.</li>
            <li>The item must be unused and unworn, in its original packing, with the tags attached.</li>
            <li>You pay the return shipping, and a <strong>25% restocking fee</strong> is deducted from your refund.</li>
            <li>Items bought in a sale or with a discount can only be returned if they are faulty.</li>
            <li>If you order more than two items at the same time, the items can only be returned if they are faulty.</li>
        </ul>
    </section>
    <section id="custom">
        <h2>Custom Size and Custom Made orders</h2>
        <p>These are cut and made to your measurements or your design, so they cannot be returned or exchanged, unless they are faulty or we made a mistake. Before you order, check your measurements with the <a href="{{ route('guide.how-to-measure') }}">measuring guide</a>.</p>
    </section>
    <section id="faulty">
        <h2>Faulty items and our mistakes</h2>
        <ul>
            <li>Tell us within {{ $days }} days of delivery and send photos of the fault.</li>
            <li>We repair, replace or refund the item. We also pay the shipping costs.</li>
            <li>Items that you have worn a lot, altered or damaged yourself are not covered.</li>
        </ul>
    </section>
    <section id="how">
        <h2>How to return an item</h2>
        <ul>
            <li>Request a return before you send anything. Give your name, order number and the email address you used to order. For a faulty item, add photos.</li>
            <li>We reply within 48 working hours with the return address.</li>
            <li>Send the item with a tracked postal service, so it is protected on the way.</li>
        </ul>
        <p><a class="gd-btn" style="color:#fff;text-decoration:none" href="{{ route('contact') }}">Request a return</a></p>
    </section>
    <section id="refunds">
        <h2>Refunds</h2>
        <ul>
            <li>We refund you within 14 days of receiving your return.</li>
            <li>The refund goes to the payment method you used.</li>
            <li>Shipping costs are not refunded, unless the item was faulty or we made a mistake.</li>
        </ul>
    </section>
</x-legal.page>
