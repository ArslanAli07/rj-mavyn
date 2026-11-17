@section('title', 'FAQ')
<x-app-layout :title="'FAQ | '.setting('site_name', config('site.name', 'Store')).' — Size Guides, Delivery & Made to Measure'" :meta-description="'Answers on made-to-measure sizing, leather care, delivery times, returns and payment options for custom leather jackets and motorcycle gear from '.setting('site_name', config('site.name', 'RJ Motorbike')).'.'" page-key="faq">
@push('head')
    {!! seo_schema(app(\App\Services\Seo\StructuredDataService::class)->faqGraph($faqs)) !!}
@endpush
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">FAQ</span>
        </nav>
    </div>

    <section class="rj-section rj-section--tight">
        <div class="rj-container">
            <div class="rj-section-head rj-section-head--center">
                <div class="rj-section-head__copy">
                    <span class="rj-eyebrow">Good to know</span>
                    <h1 class="rj-display rj-title" style="font-size:38px">Frequently asked questions</h1>
                    <p class="rj-lede">Everything about ordering, made-to-measure work, delivery and payments &mdash; answered in one place.</p>
                </div>
            </div>

            @if($faqs->isEmpty())
                <div class="rj-empty" style="max-width:560px;margin-inline:auto">
                    <svg class="icon" style="width:28px;height:28px;margin:0 auto 12px;display:block" aria-hidden="true"><use href="#icon-mail"/></svg>
                    <p><strong>No FAQs published yet.</strong><br>Our FAQs are being written. In the meantime, message us directly and we will help.</p>
                    <p style="margin-top:14px"><a href="{{ route('contact') }}">Contact us <svg class="icon" style="width:14px;height:14px;vertical-align:-2px"><use href="#icon-arrow"/></svg></a></p>
                </div>
            @else
                @foreach($faqs as $category => $items)
                    <div style="max-width:860px;margin:0 auto 44px">
                        <div class="rj-section-head" style="margin-bottom:18px">
                            <span class="rj-eyebrow">{{ $category }}</span>
                        </div>
                        <div class="rj-accordion">
                            @foreach($items as $faq)
                                <details>
                                    <summary>{{ $faq->question }}<svg class="icon"><use href="#icon-plus"/></svg></summary>
                                    <div class="accordion-content">{!! nl2br(e($faq->answer)) !!}</div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            @php
                $whatsappDigits = preg_replace('/[^0-9]/', '', (string) setting('site_whatsapp'));
            @endphp
        </div>
    </section>

    <section class="rj-band" aria-labelledby="faq-help-title">
        <div class="rj-container rj-band__inner">
            <div class="rj-band__copy">
                <span class="rj-chip rj-chip--accent">Still stuck?</span>
                <h2 class="rj-display rj-title" id="faq-help-title" style="font-size:30px">We answer every message.</h2>
                <p class="rj-lede">{{ $whatsappDigits ? 'Message the workshop directly on WhatsApp and get an answer while it is still your day.' : 'Write to us and the workshop will come back to you with the details.' }}</p>
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap">
                @if($whatsappDigits)
                    <a class="rj-button rj-button--accent" href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener"><svg class="icon" aria-hidden="true"><use href="#icon-whatsapp"/></svg> Chat on WhatsApp</a>
                @endif
                <a class="rj-button rj-button--ghost" href="{{ route('contact') }}">Send a message <svg class="icon"><use href="#icon-arrow"/></svg></a>
            </div>
        </div>
    </section>
</x-app-layout>
