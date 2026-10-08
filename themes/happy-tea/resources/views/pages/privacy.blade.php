<x-app-layout 
    title="Privacy Policy — {{ setting('site_name', config('site.name', 'Store')) }}" 
    page-key="privacy"
    meta-description="Learn how Happy Tea collects, uses, and safeguards your personal information when ordering beverages and desserts in Sialkot, Pakistan."
    meta-keywords="happy tea privacy policy, user privacy, customer data security sialkot, happy tea"
>
    @if(setting('privacy_policy'))
        <article class="max-w-3xl mx-auto px-4 py-12"><h1 class="text-3xl font-bold">{{ setting('site_name', config('site.name', 'Store')) }}</h1><p class="mt-6 whitespace-pre-line">{{ setting('privacy_policy') }}</p></article>
    @else


    <x-breadcrumb :items="['Privacy Policy' => '']" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        <div class="text-center max-w-xl mx-auto mb-16 animate-fade-up">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">Privacy Policy</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6 text-sm text-espresso-700 leading-relaxed font-light">
            <p>
                At <span class="font-semibold text-espresso-800">Happy Tea</span>, one of our main priorities is the privacy of our visitors. This Privacy Policy explains what information we collect and how we use it.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">1. Information We Collect</h3>
            <p>
                When you register for an account, place an order, or message us, we collect personal information such as:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Your name, email address, phone number, and delivery address.</li>
                <li>Your order history and shopping cart selections.</li>
                <li>Manual payment details (like screenshot references shared via WhatsApp). We <strong>do not</strong> collect bank credentials or PIN codes.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">2. How We Use Your Information</h3>
            <p>
                We use the collected information in various ways, including to:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Process, fulfill, and deliver your Happy Tea orders.</li>
                <li>Understand and analyze how you use our website.</li>
                <li>Communicate with you for order confirmations, delivery tracking, or promotional updates.</li>
                <li>Prevent fraudulent transactions and protect our security.</li>
            </ul>

            <h3 class="font-heading font-bold text-base text-espresso-800">3. Log Files & Cookies</h3>
            <p>
                Happy Tea follows a standard procedure of using log files and cookies. These files log visitors when they visit websites. The information collected includes IP addresses, browser type, Internet Service Provider (ISP), date/time stamp, referring/exit pages, and number of clicks. These are not linked to personally identifiable information.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">4. Third-Party Links</h3>
            <p>
                Our site may link to external services (like WhatsApp or banking apps). We have no control over the content and practices of these sites, and cannot accept responsibility for their privacy policies.
            </p>

            <h3 class="font-heading font-bold text-base text-espresso-800">5. Consent</h3>
            <p>
                By using our website, you hereby consent to our Privacy Policy and agree to its terms.
            </p>
        </div>

    </div>

    @endif
</x-app-layout>
