@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
    $aboutText = setting('about_text', 'Hand-cut hides, small workshop runs and a finish that improves with every mile. Every piece is made to measure and built to age beautifully.');

    /*
     * The story page uses the theme's own photography. The merchant's media
     * library stays the fallback for anything the theme does not ship.
     */
    $photo = function (string $path) {
        try {
            return theme_asset('images/'.$path);
        } catch (\InvalidArgumentException) {
            return null;
        }
    };
    $storyPhoto = $photo('gallery/hidetohide.jpeg');
    $craftPhoto = $photo('gallery/caring-for-leather.jpeg');
    $workshopPhoto = $photo('gallery/bespoke-getting.jpeg');
@endphp
<x-app-layout :title="'Our Story — '.setting('site_name', config('site.name', 'Store'))" page-key="about">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">Our story</span>
        </nav>
    </div>

    {{-- Story: photograph beside the retailer's own words --}}
    <section class="rj-section rj-section--tight">
        <div class="rj-container">
            <div class="rj-split">
                <span class="rj-split__media">
                    @if($storyPhoto)
                        <img src="{{ $storyPhoto }}" alt="From hide to hide: how our leather is made" loading="eager">
                    @else
                        <span class="rj-media-frame" data-ratio="4/3"><span>{{ $brand }}</span></span>
                    @endif
                </span>
                <div class="rj-split__copy">
                    <span class="rj-chip rj-chip--accent">Since 2009</span>
                    <h1 class="rj-display rj-title" style="margin-top:18px">Built for the long way round.</h1>
                    <p class="rj-lede" style="white-space:pre-line">{{ $aboutText }}</p>
                    <div class="rj-values" style="width:100%">
                        <div class="rj-values__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-ruler"/></svg>
                            <h3>Made to measure</h3>
                            <p>Size charts, Pantone matching and your own artwork on every commission.</p>
                        </div>
                        <div class="rj-values__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-truck"/></svg>
                            <h3>Shipped worldwide</h3>
                            <p>Small workshop runs, packed by hand and tracked door to door.</p>
                        </div>
                        <div class="rj-values__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-return"/></svg>
                            <h3>Fair exchanges</h3>
                            <p>Clear returns policy and support that answers in hours, not days.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- The making-of: three steps, editorial tone --}}
    <section class="rj-section rj-section--tight" aria-labelledby="craft-title">
        <div class="rj-container">
            <div class="rj-section-head">
                <div class="rj-section-head__copy">
                    <span class="rj-eyebrow">The craft</span>
                    <h2 class="rj-display rj-title" id="craft-title">From hide to hide</h2>
                </div>
                <a class="rj-textlink" href="{{ route('shop') }}">Shop the collection <svg class="icon"><use href="#icon-arrow"/></svg></a>
            </div>
            <div class="rj-grid">
                <article class="rj-article">
                    <span class="rj-article__media">
                        @if($workshopPhoto)
                            <img src="{{ $workshopPhoto }}" alt="Bespoke commissions at the workshop" loading="lazy">
                        @else
                            <span class="rj-media-frame" data-ratio="3/2"><span>The workshop</span></span>
                        @endif
                    </span>
                    <h3>01 &mdash; Sourcing</h3>
                    <p>Full-grain cowhide, lambskin and kangaroo hides, selected for structure and finish, never for volume.</p>
                </article>
                <article class="rj-article">
                    <span class="rj-article__media">
                        @if($craftPhoto)
                            <img src="{{ $craftPhoto }}" alt="Leather care in the workshop" loading="lazy">
                        @else
                            <span class="rj-media-frame" data-ratio="3/2"><span>Repair &amp; care</span></span>
                        @endif
                    </span>
                    <h3>02 &mdash; Hand-cut</h3>
                    <p>Panels are cut individually to your measurements, then stitched by the hands that cut them.</p>
                </article>
                <article class="rj-article">
                    <span class="rj-article__media">
                        @if($photo('gallery/newsletter.jpeg'))
                            <img src="{{ $photo('gallery/newsletter.jpeg') }}" alt="The atelier finishing bench" loading="lazy">
                        @else
                            <span class="rj-media-frame" data-ratio="3/2"><span>The finish</span></span>
                        @endif
                    </span>
                    <h3>03 &mdash; Finished to order</h3>
                    <p>Lining, hardware and monogram chosen by you. Every piece leaves the atelier ready to age well.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- Closing band: the merchant's own promise + the two ways forward --}}
    <section class="rj-band" aria-labelledby="about-band-title">
        <div class="rj-container rj-band__inner">
            <div class="rj-band__copy">
                <span class="rj-chip rj-chip--accent">Made to order</span>
                <h2 class="rj-display rj-title" id="about-band-title">Crafted to last.<br>Made to be yours.</h2>
                <p class="rj-lede">Tell us the brief and the workshop will do the rest &mdash; a jacket for the road, a gift for the team, or a piece that has never been made before.</p>
                <div style="display:flex;flex-wrap:wrap;gap:14px">
                    <a class="rj-button rj-button--accent" href="{{ route('shop') }}">Shop the collection <svg class="icon"><use href="#icon-arrow"/></svg></a>
                    <a class="rj-button rj-button--ghost" href="{{ route('contact') }}">Talk to us <svg class="icon"><use href="#icon-arrow"/></svg></a>
                </div>
            </div>
            <div class="rj-band__strip-wrap" style="display:grid;place-items:center">
                <span class="rj-media-frame rj-media-frame--dark" data-ratio="4/5" style="border:1px solid var(--rj-ink-line)">
                    @if($photo('showcase/leather-collection.jpeg'))
                        <img src="{{ $photo('showcase/leather-collection.jpeg') }}" alt="The {{ $brand }} collection" loading="lazy">
                    @else
                        <span>{{ $brand }}</span>
                    @endif
                </span>
            </div>
        </div>
    </section>
</x-app-layout>
