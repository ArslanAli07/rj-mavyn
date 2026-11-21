@php
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
@endphp
<x-legal.page title="Privacy Policy" page-key="privacy" custom-key="privacy_policy"
    :toc="['who' => 'Who we are', 'collect' => 'What we collect', 'use' => 'How we use it', 'share' => 'Who we share it with', 'cookies' => 'Cookies', 'keep' => 'How long we keep it', 'rights' => 'Your rights', 'contact' => 'Contact']"
    :see="['Terms & conditions' => route('terms'), 'Refund policy' => route('returns'), 'Contact us' => route('contact')]"
    :description="'What personal data '.$store.' collects, why we use it, who we share it with, and the choices you have.'"
    intro="This policy explains what personal data we collect when you visit or buy from this website, why we use it, who we share it with, and the choices you have.">
    <section id="who">
        <h2>1. Who we are</h2>
        <p>{{ $store }} runs this website and is responsible for your personal data.</p>
        @include('legal._who')
    </section>
    <section id="collect">
        <h2>2. What we collect</h2>
        <div class="lg-table-wrap">
            <table>
                <thead><tr><th>Type of data</th><th>Examples</th></tr></thead>
                <tbody>
                    <tr><td>Contact and order details</td><td>Name, email, phone number, billing and shipping address, the items and options you order, order notes.</td></tr>
                    <tr><td>Measurements and designs</td><td>Body measurements, height and weight you send for a Custom Size or Custom Made order, and any designs, logos or photos you upload.</td></tr>
                    <tr><td>Account details</td><td>Your login email and password (stored encrypted), saved addresses, wish list and order history.</td></tr>
                    <tr><td>Payment details</td><td>Paid through Stripe. We see the payment status and, for cards, only the card type and last four digits. We never see or store your full card number.</td></tr>
                    <tr><td>Messages</td><td>Emails, messages and forms you send us.</td></tr>
                    <tr><td>Device and usage data</td><td>IP address, browser and device type, collected by our servers and by the cookies described in section 5.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    <section id="use">
        <h2>3. How we use your data</h2>
        <ul>
            <li><strong>To make and deliver your order:</strong> to check your size and details, make your order, ship it, and handle returns and refunds.</li>
            <li><strong>To help you:</strong> to answer your questions.</li>
            <li><strong>To keep payments safe:</strong> to prevent fraud and check suspicious orders.</li>
            <li><strong>To send you offers:</strong> only if you sign up for our newsletter. Every email has an unsubscribe link.</li>
            <li><strong>To meet our legal duties,</strong> such as keeping tax and accounting records.</li>
        </ul>
        <p>We use your measurements only to make your order and future orders you place with us. We do not sell your personal data.</p>
    </section>
    <section id="share">
        <h2>4. Who we share it with</h2>
        <p>We share only the data each partner needs to do their job:</p>
        <ul>
            <li>Our workshop in Pakistan, which makes your order: your name, order details, measurements and design.</li>
            <li>Couriers, to deliver your parcel: your name, address and phone number.</li>
            <li>Our payment provider, Stripe, to take your payment and check it for fraud.</li>
            <li>Our hosting and email providers, who store our website and send our emails.</li>
            <li>Authorities, when the law requires it.</li>
        </ul>
        <p>Some of these partners are in countries outside your own. Where the law requires it, we rely on safeguards such as standard contractual clauses.</p>
    </section>
    <section id="cookies">
        <h2>5. Cookies</h2>
        <p>Cookies are small files saved in your browser. We use only the cookies the shop needs to work: they keep your cart and login, remember your currency, and protect forms and checkout. We do not use advertising or tracking cookies. You can block or delete cookies in your browser, but the cart and checkout will then not work.</p>
    </section>
    <section id="keep">
        <h2>6. How long we keep your data</h2>
        <ul>
            <li>Order details and measurements: for as long as you are a customer, so you can reorder with the same fit, and as long as tax and accounting laws require.</li>
            <li>Account details: until you close your account.</li>
            <li>Newsletter: until you unsubscribe.</li>
            <li>Messages: for as long as needed to help you and to handle any follow-up.</li>
        </ul>
        <p>Our website uses an encrypted connection (HTTPS), and access to customer data is limited to the people who need it to handle your order.</p>
    </section>
    <section id="rights">
        <h2>7. Your rights</h2>
        <p>You can ask us to:</p>
        <ul>
            <li>tell you what personal data we hold about you and give you a copy,</li>
            <li>correct data that is wrong,</li>
            <li>delete your data, unless we must keep it by law (for example order records),</li>
            <li>stop sending you marketing, at any time.</li>
        </ul>
        <p>If you live where a data protection law applies, such as the EU, UK or California, you may have further rights, including to object to or limit our use of your data and to complain to your data protection authority.</p>
        <p>To use any of these rights, <a href="{{ route('contact') }}">contact us</a>. There is no charge. We may ask you to confirm your identity, and we reply within one month.</p>
    </section>
    <section id="contact">
        <h2>8. Children, changes and contact</h2>
        <p>Our website is not meant for children under 16, and we do not knowingly collect their data. Orders for children must be placed by a parent or guardian.</p>
        <p>We may update this policy from time to time. The date at the top shows the latest version. For any privacy question, <a href="{{ route('contact') }}">contact us</a>.</p>
    </section>
</x-legal.page>
