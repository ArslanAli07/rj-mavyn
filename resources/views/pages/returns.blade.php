<x-app-layout 
    title="Refund Policy — Happy Tea" 
    page-key="returns"
    meta-description="Read Happy Tea's Refund & Return Policy. Learn about our order cancellation guidelines, prompt issue reporting via WhatsApp, and fast refund options in Sialkot."
    meta-keywords="happy tea refund policy, happy tea return policy, boba tea refund sialkot, drink cancellation, tea refund sialkot"
>

    <x-breadcrumb :items="['Refund Policy' => '']" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        <div class="text-center max-w-xl mx-auto mb-16 animate-fade-up">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">Refund Policy</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6 text-sm text-espresso-700 leading-relaxed font-light">
            <p>
                At <span class="font-semibold text-espresso-800">Happy Tea</span>, we take pride in serving handcrafted, freshly brewed beverages and treats. Because our items are perishable food and drink products, strict food safety and hygiene regulations apply. We do not accept physical returns of opened or consumed items once delivered.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">1. Order Cancellations & Modifications</h3>
            <p>
                Due to our quick preparation and same-day express delivery:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Cancellation Window:</strong> You may cancel or modify your order within <strong>5 to 10 minutes</strong> of placing it, before our kitchen begins brewing and preparation.</li>
                <li><strong>After Preparation / Dispatch:</strong> Once preparation begins or our delivery driver departs with your order, the order cannot be cancelled or altered.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">2. Reporting Issues (Damaged, Incorrect, or Missing Items)</h3>
            <p>
                Since our dedicated delivery fleet delivers same-day fresh orders within an hour, we request you to check your order upon arrival.
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Reporting Timeframe:</strong> Please report any issues immediately to the driver or via WhatsApp message upon delivery.</li>
                <li><strong>Required Evidence:</strong> Please provide your Order Number, a brief description of the issue, and clear photos or videos showing the damaged packaging, spilled drink, or incorrect items received.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">3. Resolution & Refund Options</h3>
            <p>
                Once your claim is reviewed and verified by our team, we will provide one of the following resolutions based on your preference:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Instant Remake & Replacement:</strong> A fresh item dispatched immediately via our express delivery driver at no extra charge.</li>
                <li><strong>Store Credit Voucher:</strong> A coupon code of equal value for your next online order.</li>
                <li><strong>Direct Refund:</strong> A reversal/refund sent directly to your original payment channel (Easypaisa, JazzCash, or Bank Account) within <strong>24 hours</strong>. For Cash on Delivery (COD) orders, refunds are sent via JazzCash, Easypaisa or Bank Account.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">4. In-Store & Takeaway Purchases</h3>
            <p>
                For orders placed in-store or collected for takeaway at our outlet, please check your items at the counter. Any errors or quality concerns will be remade or corrected immediately on the spot before leaving the shop.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">5. Contact Customer Support</h3>
            <p>
                If you have any questions or require assistance regarding refunds, cancellations, or quality claims, please contact our support team via WhatsApp or email:
            </p>
            @php
                $siteEmail = trim((string) setting('site_email'));
                $sitePhone = trim((string) setting('site_phone'));
            @endphp
            <ul class="list-disc pl-5 space-y-1">
                @if($sitePhone)
                    <li><strong>Phone / WhatsApp:</strong> <span class="font-semibold text-espresso-800">{{ $sitePhone }}</span></li>
                @endif
                @if($siteEmail)
                    <li><strong>Email:</strong> <span class="font-semibold text-espresso-800">{{ $siteEmail }}</span></li>
                @endif
            </ul>
        </div>

    </div>

</x-app-layout>
