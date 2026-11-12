@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'My Favourites — '.setting('site_name', config('site.name', 'Store'))" page-key="wishlist">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">Wishlist</span>
        </nav>

        <header class="rj-page-head">
            <span class="rj-eyebrow">{{ $brand }} &mdash; saved pieces</span>
            <h1 class="rj-display rj-title rj-page-head__title">{{ $wishlist->isEmpty() ? 'Your wishlist' : 'My favourites' }}</h1>
            <p class="rj-lede">Leather you liked enough to keep. Save pieces while you browse and they wait here, ready when you are.</p>
        </header>

        <section class="rj-section rj-section--flush-top" style="padding-bottom:80px">
            @if($wishlist->isEmpty())
                <div class="rj-empty" style="max-width:560px;margin-inline:auto">
                    <svg class="icon" style="width:28px;height:28px;margin:0 auto 12px;display:block" aria-hidden="true"><use href="#icon-heart"/></svg>
                    <p><strong>Nothing saved yet.</strong><br>Tap the heart on any piece and it will be waiting here.</p>
                    <p style="margin-top:14px"><a href="{{ route('shop') }}">Browse the collection <svg class="icon" style="width:14px;height:14px;vertical-align:-2px"><use href="#icon-arrow"/></svg></a></p>
                </div>
            @else
                <div class="rj-grid" style="margin-top:34px">
                    @foreach($wishlist as $item)
                        @if($item->product)
                            <x-product-card :product="$item->product" />
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
