@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'My Favourites — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('account.dashboard') }}">My account</a><span>/</span><span aria-current="page">Wishlist</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-account">
                <x-account-nav active="wishlist" />

                <div>
                    @if($wishlist->isEmpty())
                        <div class="rj-panel">
                            <div class="rj-panel__head"><h2>My wishlist</h2></div>
                            <div class="rj-empty" style="border-width:0">
                                <svg class="icon" style="width:28px;height:28px;margin:0 auto 12px;display:block" aria-hidden="true"><use href="#icon-heart"/></svg>
                                <p><strong>Nothing saved yet.</strong><br>Tap the heart on any piece and it will be waiting here.</p>
                                <p style="margin-top:14px"><a href="{{ route('shop') }}">Browse the collection <svg class="icon" style="width:14px;height:14px;vertical-align:-2px"><use href="#icon-arrow"/></svg></a></p>
                            </div>
                        </div>
                    @else
                        <div class="rj-grid">
                            @foreach($wishlist as $item)
                                @if($item->product)
                                    <x-product-card :product="$item->product" />
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
