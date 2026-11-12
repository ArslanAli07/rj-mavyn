<x-app-layout :title="'Refund Policy — '.setting('site_name', config('site.name', 'Store'))" page-key="returns">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">Refund Policy</span>
        </nav>
    </div>

    <section class="rj-section rj-section--tight">
        <div class="rj-container">
            <div class="rj-section-head" style="margin-bottom:22px">
                <span class="rj-eyebrow">Legal</span>
                <h1 class="rj-display rj-title" style="font-size:38px">Returns &amp; refunds</h1>
            </div>
            <div class="rj-prose" style="max-width:820px">
                <p style="white-space:pre-line">{{ setting('returns_policy', 'Every RJ Motorbike piece is made to order. Contact us within 7 days of delivery if something is not right and we will work it out with you.') }}</p>
            </div>
            <p style="margin-top:26px"><a class="rj-btn rj-btn--secondary" href="{{ route('contact') }}">Contact us <svg class="icon" style="width:14px;height:14px;vertical-align:-2px" aria-hidden="true"><use href="#icon-arrow"/></svg></a></p>
        </div>
    </section>
</x-app-layout>
