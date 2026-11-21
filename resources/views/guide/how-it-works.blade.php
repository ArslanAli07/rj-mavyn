@php
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
    $ways = $guide['ways'];
    $customCategory = \App\Models\Category::query()->where('is_active', true)->where('slug', 'customize')->first();
    $customRaceSuit = \App\Models\Product::query()->where('is_active', true)->where('title', 'like', 'Custom Race Suit%')->orderBy('id')->first();
    $currency = setting('store_currency_symbol', '$');
@endphp
<x-app-layout :title="'How Ordering Works — '.$store" :meta-description="'How ordering works at '.$store.': standard sizes, custom sizes and fully custom leather, from choosing your size and sending measurements to production, delivery and fit.'" page-key="how-it-works">
    <div class="gd">
        <div class="rj-container">
            <nav class="rj-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">How it works</span>
            </nav>

            @include('guide._partials', ['noTabs' => true])
            <header class="gd-head gd-head--center">
                <h1 class="gd-title"><span>How it works</span></h1>
                <p class="gd-lede">Every piece is hand-stitched to order in our workshop. Here is how ordering works, from choosing your size to delivery.</p>
            </header>

            <section class="gd-section" aria-labelledby="ways">
                <h2 class="gd-h2" id="ways">Three ways to order</h2>
                <div class="gd-grid3">
                    <article class="gd-card">
                        <h3>{{ $ways['standard']['title'] }}</h3>
                        <p>{{ $ways['standard']['summary'] }}</p>
                        <p><a class="gd-link" href="{{ route('guide.size-guide') }}">Open the size guide</a></p>
                        <span class="gd-pill">Ready to ship in {{ $ways['standard']['lead_time'] }}</span>
                    </article>
                    <article class="gd-card">
                        <h3>{{ $ways['custom_size']['title'] }}</h3>
                        <p>{{ $ways['custom_size']['summary'] }} Pick <strong>Custom Size</strong> in the size options, then send us your measurements.</p>
                        <p><a class="gd-link" href="{{ route('guide.how-to-measure') }}">How to measure</a></p>
                        <span class="gd-pill">Ready to ship in {{ $ways['custom_size']['lead_time'] }}</span>
                    </article>
                    <article class="gd-card">
                        <h3>{{ $ways['custom_made']['title'] }}</h3>
                        <p>{{ $ways['custom_made']['summary'] }} Start from one of our customisable designs and describe what you want in the order instructions.</p>
                        @if($customCategory)
                            <p><a class="gd-link" href="{{ route('category.show', $customCategory->slug) }}">Browse customisable designs</a></p>
                        @endif
                        <span class="gd-pill">Ready to ship in {{ $ways['custom_made']['lead_time'] }}</span>
                    </article>
                </div>
            </section>

            <section class="gd-section" aria-labelledby="steps">
                <h2 class="gd-h2" id="steps">Step by step</h2>
                <ol class="gd-steps">
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">1</span>
                        <div>
                            <h3>Choose your product and size</h3>
                            <p>Pick a standard size from the <a class="gd-link" href="{{ route('guide.size-guide') }}">size guide</a>, or choose Custom Size in the size options. For your own design, start from our
                                @if($customCategory)<a class="gd-link" href="{{ route('category.show', $customCategory->slug) }}">custom leather</a>@else custom leather @endif
                                @if($customRaceSuit) or <a class="gd-link" href="{{ route('product.show', $customRaceSuit->slug) }}">custom race suit</a>@endif
                                and set your leather, colours and extras. Describe your design or changes in the order instructions; we can also take design files by email after you order.</p>
                        </div>
                    </li>
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">2</span>
                        <div>
                            <h3>Place your order</h3>
                            <p>Pay securely by card at checkout. We email you an order confirmation straight away.</p>
                        </div>
                    </li>
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">3</span>
                        <div>
                            <h3>Send your measurements (Custom Size and Custom Made)</h3>
                            <p>Ask someone to measure you, using our <a class="gd-link" href="{{ route('guide.how-to-measure') }}">measuring guide</a>. Then send the numbers with your order number using the <a class="gd-link" href="{{ route('guide.measurement-form') }}">measurement form</a>.</p>
                        </div>
                    </li>
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">4</span>
                        <div>
                            <h3>We check the details</h3>
                            <p>Our team checks every order, its size and its details before production. If anything needs a second look, we contact you.</p>
                        </div>
                    </li>
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">5</span>
                        <div>
                            <h3>We make your order</h3>
                            <p>Your order is cut and hand-stitched in our workshop. Standard sizes are ready in {{ $ways['standard']['lead_time'] }}, Custom Size in {{ $ways['custom_size']['lead_time'] }} and Custom Made in {{ $ways['custom_made']['lead_time'] }}.</p>
                        </div>
                    </li>
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">6</span>
                        <div>
                            <h3>Delivery</h3>
                            <p>When your order ships, we email you the tracking number. Delivery usually takes {{ $guide['delivery_time'] }}@if($guide['free_shipping_over'] !== ''). Shipping is free worldwide on orders over {{ $currency }}{{ $guide['free_shipping_over'] }}@endif. See <a class="gd-link" href="{{ route('shipping') }}">shipping and delivery</a>.</p>
                        </div>
                    </li>
                    <li class="gd-step">
                        <span class="gd-step__n" aria-hidden="true">7</span>
                        <div>
                            <h3>After delivery</h3>
                            <p>Try your order on and let us know how it fits. If something is not right, contact us within {{ $guide['fit_window_days'] }} days and we will sort it out. Our <a class="gd-link" href="{{ route('returns') }}">returns policy</a> explains your options.</p>
                        </div>
                    </li>
                </ol>
            </section>

            <section class="gd-section" aria-labelledby="questions" style="margin-bottom:40px">
                <h2 class="gd-h2" id="questions">Questions?</h2>
                <p class="gd-lede">
                    @if($guide['support_email'] !== '')
                        Email <a class="gd-link" href="mailto:{{ $guide['support_email'] }}">{{ $guide['support_email'] }}</a>. We reply within 24 hours.
                    @else
                        We reply within 24 hours.
                    @endif
                </p>
                <div class="gd-btns">
                    <a class="gd-btn" href="{{ route('guide.size-guide') }}">Open the size guide</a>
                    <a class="gd-btn gd-btn--ghost" href="{{ route('contact') }}">Customer service</a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
