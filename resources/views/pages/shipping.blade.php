<x-app-layout 
    title="Shipping & Delivery Policy — Happy Tea" 
    page-key="shipping"
    meta-description="Read Happy Tea's Shipping & Delivery Policy. Learn about our 30-60 minute same-day beverage delivery across Sialkot city, delivery charges, and free delivery eligibility."
    meta-keywords="happy tea shipping policy, happy tea delivery, tea delivery sialkot, boba tea home delivery, fresh beverage delivery sialkot"
>

    <x-breadcrumb :items="['Shipping Policy' => '']" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        <div class="text-center max-w-xl mx-auto mb-16 animate-fade-up">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">Shipping & Delivery Policy</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6 text-sm text-espresso-700 leading-relaxed font-light">
            <p>
                At <span class="font-semibold text-espresso-800">Happy Tea</span>, we are dedicated to serving your favorite handcrafted drinks and treats fresh, flavorful, and fast. This policy outlines our delivery coverage, timelines, and guidelines.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">1. Delivery Coverage (Sialkot City)</h3>
            <p>
                We currently deliver exclusively within <strong>Sialkot city</strong> and designated municipal areas. All deliveries are fulfilled by our own in-house fleet of dedicated delivery drivers to maintain optimal food temperature, packaging safety, and speedy service.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">2. Estimated Delivery Timelines</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Standard Delivery:</strong> Orders are freshly brewed and typically delivered within <strong>30 to 60 minutes</strong> of order confirmation during our operational hours.</li>
                <li><strong>Peak Times & Adverse Conditions:</strong> Delivery times may experience slight delays during extreme weather, peak rush hours, or unexpected road closures. Our dispatch team will keep you informed in such events.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">3. Delivery Fees & Free Delivery</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Calculated at Checkout:</strong> Delivery fees are determined by your selected delivery zone/address in Sialkot and displayed before you place your order.</li>
                <li><strong>Free Delivery:</strong> Orders exceeding our promotional cart threshold automatically qualify for Free Delivery at checkout.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">4. Order Confirmation & Dispatch</h3>
            <p>
                Orders are processed through our kitchen upon order confirmation by our team.
            </p>
            <h3 class="font-heading font-bold text-base text-espresso-800">5. Handover & Customer Availability</h3>
            <p>
                To ensure seamless delivery of fresh beverages:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Our driver will contact you via phone or WhatsApp upon arriving at your address.</li>
                <li>Please ensure the contact number provided at checkout is active and reachable.</li>
                <li>Drivers can wait for a maximum of <strong>10 to 15 minutes</strong> at the delivery location before having to proceed with subsequent deliveries to keep orders fresh for other customers.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">6. Delivery Assistance & Inquiries</h3>
            <p>
                If you have any questions regarding your active delivery or need delivery support, please contact our team:
            </p>
            @php
                $siteEmail = trim((string) setting('site_email'));
                $sitePhone = trim((string) setting('site_phone'));
                $siteAddress = trim((string) setting('site_address'));
            @endphp
            <ul class="list-disc pl-5 space-y-1">
                @if($sitePhone)
                    <li><strong>Phone / WhatsApp:</strong> <span class="font-semibold text-espresso-800">{{ $sitePhone }}</span></li>
                @endif
                @if($siteEmail)
                    <li><strong>Email:</strong> <span class="font-semibold text-espresso-800">{{ $siteEmail }}</span></li>
                @endif
                @if($siteAddress)
                    <li><strong>Location:</strong> <span class="font-semibold text-espresso-800">{{ $siteAddress }}</span></li>
                @endif
            </ul>
        </div>

    </div>

</x-app-layout>
