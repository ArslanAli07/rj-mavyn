<x-app-layout :title="setting('site_name', config('site.name'))" page-key="home">
    @php
        $storeName = setting('site_name', config('site.name', 'RJ Motorbike'));
        $brandName = theme_setting('brand_name', 'RJ Motorbike');

        /*
         * The category tree is the store's structure, shared with the storefront
         * navigation. The landing page mirrors the reference layout: hero, a
         * bestselling carousel, category CTA cards, two more product carousels,
         * a ticker, the community strip and the USP band.
         */
        $groups = $homeCategories->map(fn ($category) => [
            'name' => $category->name,
            'slug' => $category->slug,
            'children' => $category->children->pluck('name')->all(),
            'category' => $category,
        ])->values();

        $allProducts = $homeCategories->flatMap(fn ($category) => $category->products)->unique('id')->values();
        $bestsellers = $allProducts->filter(fn ($product) => $product->is_bestseller)->values();
        if ($bestsellers->count() < 4) {
            $bestsellers = $allProducts->filter(fn ($product) => $product->is_featured)->values();
        }
        if ($bestsellers->count() < 4) {
            $bestsellers = $allProducts;
        }
        $newArrivals = $allProducts->filter(fn ($product) => $product->is_new_arrival)->values();
        if ($newArrivals->count() < 4) {
            $newArrivals = $allProducts->sortByDesc(fn ($product) => $product->created_at?->timestamp ?? 0)->values();
        }

        /*
         * Carousels always carry a full row of goods: when a selection runs
         * short it is topped up from the rest of the catalogue, so the slider
         * always has something to scroll to.
         */
        $padRow = function ($products, int $count = 10) use ($allProducts) {
            $products = $products->unique('id')->values();
            if ($products->count() >= $count) {
                return $products->take($count);
            }
            $extra = $allProducts->reject(fn ($product) => $products->contains('id', $product->id));
            return $products->merge($extra)->take($count)->values();
        };
        /*
         * Top sellers: two spotlight products whose studio photography does not
         * sit well on the shared tile grid are swapped out for the next
         * bestsellers, keeping the row visually uniform.
         */
        $topSellersExcluded = [
            'leather-kaptaan-chappal-tan-men-cowhide',
            'leon-haslam-wsbk-racing-jacket-bmw-2012',
            'black-brown-lambskin-leather-gloves-mens',
            'black-lambskin-leather-dress-gloves-men',
        ];
        $bestsellers = $bestsellers->reject(fn ($product) => in_array($product->slug, $topSellersExcluded, true))->values();
        /*
         * The sellers row also tops up from the wider catalogue, but the
         * swap-out list still applies there — otherwise a rejected product
         * simply returns through the back door.
         */
        $padSellers = function ($products, int $count = 10) use ($allProducts, $topSellersExcluded) {
            $products = $products->unique('id')->values();
            if ($products->count() >= $count) {
                return $products->take($count);
            }
            $extra = $allProducts->reject(fn ($product) => $products->contains('id', $product->id)
                || in_array($product->slug, $topSellersExcluded, true));
            return $products->merge($extra)->take($count)->values();
        };
        $bestsellers = $padSellers($bestsellers);
        $newArrivals = $padRow($newArrivals);
        $ctaCards = $groups->take(3);
        $ctaSecondary = $groups->slice(3)->take(2);

        $bundledPhoto = function (string $path) {
            try {
                return theme_asset('images/'.$path);
            } catch (\InvalidArgumentException) {
                return null;
            }
        };
        $photoFor = fn (string $folder, string $name) => $bundledPhoto($folder.'/'.str($name)->slug().'.jpeg');

        /*
         * Community strip: the theme's bundled workshop photography is the
         * primary source; the merchant's media library tops it up or replaces
         * it once images are uploaded.
         */
        $bundledGallery = collect([
            ['src' => $bundledPhoto('gallery/hidetohide.jpeg'), 'alt' => 'From hide to hide'],
            ['src' => $bundledPhoto('gallery/caring-for-leather.jpeg'), 'alt' => 'Caring for leather'],
            ['src' => $bundledPhoto('gallery/bespoke-getting.jpeg'), 'alt' => 'Bespoke fittings'],
            ['src' => $bundledPhoto('gallery/newsletter.jpeg'), 'alt' => 'The workshop'],
        ])->filter(fn ($image) => filled($image['src']))->values();
        $galleryMedia = $bundledGallery->merge(collect($galleryImages)->take(4))->take(4)->values();

        /*
         * The hero carries the first administrator slide; with no slides in the
         * media library it still opens on a real photograph with the theme's
         * own copy.
         */
        $heroSlides = $slides->take(5);
        $hero = $heroSlides->first();
        $heroImage = null;
        if ($heroSlides->isNotEmpty()) {
            $hasImage = fn (?string $url) => filled($url) && ! str_contains($url, 'placeholder.svg');
            $heroImage = $bundledPhoto('hero/banner-1.jpeg') ?: ($hasImage($hero->image_url) ? $hero->image_url : null);
            $heroMobile = $bundledPhoto('hero/banner-1-mobile.jpeg') ?: ($hasImage($hero->mobile_image_url) ? $hero->mobile_image_url : null);
        } else {
            $heroImage = $bundledPhoto('hero/banner-1.jpeg');
            $heroMobile = $bundledPhoto('hero/banner-1-mobile.jpeg');
        }
        $heroTitle = $hero?->title ?? theme_setting('hero_title', 'Crafted to last. Made to be yours.');
        $heroHighlight = $hero?->title_highlighted;
        $heroText = $hero?->description ?? theme_setting('hero_description');
        $heroButton = [
            'label' => $hero?->button_text ?: theme_setting('hero_button', 'Shop the collection'),
            'url' => $hero?->button_link ?: route('shop'),
        ];

        $tickerWords = collect([
            'Handmade leather', 'Made to order', 'Free worldwide shipping',
            'Full-grain hides', 'Finished by hand', $brandName,
        ]);

        // Carousel rows after the CTA cards: the recommended picks, then the
        // next catalogue groups that actually carry products.
        $productRows = collect([
            ['row' => ['name' => 'Recommended', 'slug' => null, 'category' => null], 'products' => $newArrivals],
        ])->merge(
            $groups
                ->map(fn ($group) => ['row' => $group, 'products' => $group['category']?->products ?? collect()])
                ->filter(fn ($row) => $row['products']->count() >= 4)
                ->take(2)
                ->values()
        )->map(fn ($row) => ['row' => $row['row'], 'products' => $padRow($row['products'])])->values();

        $uspItems = [
            ['title' => 'Made to order', 'text' => 'Every piece is cut and finished by hand after you order.', 'href' => route('about'), 'icon' => 'needle'],
            ['title' => 'Free worldwide shipping', 'text' => 'Complimentary tracked delivery on orders over $650.00 USD.', 'href' => route('shipping'), 'icon' => 'truck'],
            ['title' => 'Delivery & returns', 'text' => 'View timelines and policy for exchanges and returns.', 'href' => route('returns'), 'icon' => 'return'],
            ['title' => 'Size guide', 'text' => 'Find your fit with our detailed measuring charts.', 'href' => route('faq'), 'icon' => 'ruler'],
        ];
    @endphp

    {{-- Hero: full-bleed media with the copy set over the lower left, the way
         the reference landing opens. --}}
    <section class="rj-hero">
        <div class="rj-hero__media">
            <picture>
                @if($heroMobile ?? null)<source media="(max-width: 767px)" srcset="{{ $heroMobile }}">@endif
                <img src="{{ $heroImage }}" alt="{{ $hero?->image_alt ?? $heroTitle }}" fetchpriority="high">
            </picture>
            <span class="rj-hero__scrim"></span>
        </div>
        <div class="rj-container rj-hero__inner">
            <div class="rj-hero__content">
                <h1 class="rj-hero__title">Custom Leather Jackets &amp; Motorcycle Gear</h1>
                <p class="rj-hero__tagline" style="font-size: 1.125rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 0.75rem; margin-bottom: 0.25rem; color: rgba(255,255,255,0.95);">
                    {{ $heroTitle }} @if($heroHighlight)<span>{{ $heroHighlight }}</span>@endif
                </p>
                @if($heroText)<p class="rj-hero__text">{{ $heroText }}</p>@endif
                <div class="rj-hero__buttons">
                    <a class="rj-button" href="{{ $heroButton['url'] }}">{{ $heroButton['label'] }}</a>
                    <a class="rj-button rj-button--ghost" href="{{ route('about') }}">Our story</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Bestselling carousel --}}
    <section class="rj-section" aria-labelledby="bestsellers-title">
        <div class="rj-container">
            <div class="rj-section-head">
                <div class="rj-section-head__copy">
                    <h2 class="rj-title" id="bestsellers-title">{{ theme_setting('sellers_title', 'Bestselling') }}</h2>
                </div>
                <a class="rj-linkbtn" href="{{ route('shop') }}">View all <svg class="icon" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m11.838.75 4.912 5m0 0-4.912 5m4.912-5h-16"/></svg></a>
            </div>
        </div>
        <div class="rj-carousel-wrap">
            <div class="rj-carousel" id="carousel-bestsellers" data-scroller>
                @foreach($bestsellers->take(10) as $product)
                    <div class="rj-carousel__slide"><x-product-card :product="$product" /></div>
                @endforeach
            </div>
            <button class="rj-carousel__arrow rj-carousel__arrow--prev" data-scroll-target="#carousel-bestsellers" data-scroll-step="-1" aria-label="Previous products"><svg class="icon" aria-hidden="true"><use href="#icon-chevron"/></svg></button>
            <button class="rj-carousel__arrow rj-carousel__arrow--next" data-scroll-target="#carousel-bestsellers" data-scroll-step="1" aria-label="Next products"><svg class="icon" aria-hidden="true"><use href="#icon-chevron"/></svg></button>
        </div>
    </section>

    {{-- Category CTA cards: three portrait tiles, then two wide ones — the
         reference pairs two card designs in one section. --}}
    @if($ctaCards->isNotEmpty())
        <section class="rj-section rj-cta-band" aria-label="Shop by category">
            <div class="rj-cta-row rj-cta-row--thirds">
                @foreach($ctaCards as $card)
                    @php
                        $category = $card['category'] ?? null;
                        $cardName = preg_replace('/^for\s+/i', '', $card['name']);
                        $cardPhoto = $photoFor('category', $card['name']) ?: ($card['slug'] ? $photoFor('category', $card['slug']) : null);
                    @endphp
                    <a class="rj-cta rj-cta--third" href="{{ $category ? route('category.show', $category->slug) : route('shop') }}">
                        <span class="rj-cta__media">
                            @if($cardPhoto)
                                <img src="{{ $cardPhoto }}" alt="{{ $cardName }}" loading="lazy">
                            @elseif($category && ($category->image_asset_id || $category->image))
                                <img src="{{ $category->image_url }}" srcset="{{ $category->image_srcset }}" alt="{{ $category->image_alt }}" loading="lazy">
                            @else
                                <span class="rj-media-frame" data-ratio="4/5"><span>{{ $cardName }}</span></span>
                            @endif
                            <span class="rj-cta__overlay"></span>
                            <span class="rj-cta__content">
                                <h2 class="rj-cta__title" @if($loop->first) id="cta-title" @endif>{{ $cardName }}</h2>
                                <span class="rj-cta__button">Shop now <svg class="icon" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m11.838.75 4.912 5m0 0-4.912 5m4.912-5h-16"/></svg></span>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
            @if($ctaSecondary->isNotEmpty())
                <div class="rj-cta-row rj-cta-row--halves">
                    @foreach($ctaSecondary as $card)
                        @php
                            $category = $card['category'] ?? null;
                            $cardName = preg_replace('/^for\s+/i', '', $card['name']);
                            $cardPhoto = $photoFor('category', $card['name']) ?: ($card['slug'] ? $photoFor('category', $card['slug']) : null);
                        @endphp
                        <a class="rj-cta rj-cta--half" href="{{ $category ? route('category.show', $category->slug) : route('shop') }}">
                            <span class="rj-cta__media">
                                @if($cardPhoto)
                                    <img src="{{ $cardPhoto }}" alt="{{ $cardName }}" loading="lazy">
                                @elseif($category && ($category->image_asset_id || $category->image))
                                    <img src="{{ $category->image_url }}" srcset="{{ $category->image_srcset }}" alt="{{ $category->image_alt }}" loading="lazy">
                                @else
                                    <span class="rj-media-frame" data-ratio="3/2"><span>{{ $cardName }}</span></span>
                                @endif
                                <span class="rj-cta__overlay"></span>
                                <span class="rj-cta__content">
                                    <h3 class="rj-cta__title">{{ $cardName }}</h3>
                                    <span class="rj-cta__button">Shop now <svg class="icon" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m11.838.75 4.912 5m0 0-4.912 5m4.912-5h-16"/></svg></span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Further catalogue carousels, one per group --}}
    @foreach($productRows as $row)
        @php
            $rowName = preg_replace('/^for\s+/i', '', $row['row']['name']);
            $rowCategory = $row['row']['category'] ?? null;
            $carouselId = 'carousel-'.str($rowName)->slug();
        @endphp
        <section class="rj-section" aria-labelledby="{{ $carouselId }}-title">
            <div class="rj-container">
                <div class="rj-section-head">
                    <div class="rj-section-head__copy">
                        <h2 class="rj-title" id="{{ $carouselId }}-title">{{ $rowName }}</h2>
                    </div>
                    <a class="rj-linkbtn" href="{{ $rowCategory ? route('category.show', $rowCategory->slug) : route('shop') }}">View all <svg class="icon" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m11.838.75 4.912 5m0 0-4.912 5m4.912-5h-16"/></svg></a>
                </div>
            </div>
            <div class="rj-carousel-wrap">
                <div class="rj-carousel" id="{{ $carouselId }}" data-scroller>
                    @foreach($row['products']->take(10) as $product)
                        <div class="rj-carousel__slide"><x-product-card :product="$product" /></div>
                    @endforeach
                </div>
                <button class="rj-carousel__arrow rj-carousel__arrow--prev" data-scroll-target="#{{ $carouselId }}" data-scroll-step="-1" aria-label="Previous products"><svg class="icon" aria-hidden="true"><use href="#icon-chevron"/></svg></button>
                <button class="rj-carousel__arrow rj-carousel__arrow--next" data-scroll-target="#{{ $carouselId }}" data-scroll-step="1" aria-label="Next products"><svg class="icon" aria-hidden="true"><use href="#icon-chevron"/></svg></button>
            </div>
        </section>
    @endforeach

    {{-- Ticker --}}
    <section class="rj-tickerband" aria-hidden="true">
        <div class="rj-ticker">
            <div class="rj-ticker__track">
                @for ($pass = 0; $pass < 2; $pass++)
                    <span class="rj-ticker__group">
                        @foreach($tickerWords as $word)
                            <span class="rj-ticker__item">{{ $word }} <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                        @endforeach
                    </span>
                @endfor
            </div>
        </div>
    </section>

    {{-- Community strip: title in the container, images bleeding edge-to-edge. --}}
    <section class="rj-section" aria-labelledby="community-title">
        <div class="rj-container">
            <div class="rj-section-head">
                <div class="rj-section-head__copy">
                    <h2 class="rj-title" id="community-title">{{ theme_setting('community_title', 'The workshop, worn in') }}</h2>
                </div>
            </div>
        </div>
        <div class="rj-social">
                @foreach($galleryMedia as $image)
                    <span class="rj-social__item">
                        <img src="{{ $image['src'] ?? '' }}" alt="{{ $image['alt'] ?? $brandName }}" loading="lazy">
                    </span>
                @endforeach
                @if($galleryMedia->count() < 4)
                    @for ($i = $galleryMedia->count(); $i < 4; $i++)
                        <span class="rj-social__item"><span class="rj-media-frame" data-ratio="1/1"><span>{{ $brandName }}</span></span></span>
                    @endfor
                @endif
        </div>
    </section>

    {{-- USP band --}}
    <section class="rj-usp" aria-label="Why shop with us">
        <div class="rj-container">
            <div class="rj-usp__items">
                @foreach($uspItems as $item)
                    <a class="rj-usp__item" href="{{ $item['href'] }}">
                        <span class="rj-usp__icon"><svg class="icon" aria-hidden="true"><use href="#icon-{{ $item['icon'] }}"/></svg></span>
                        <span class="rj-usp__copy">
                            <h3 class="rj-usp__title">{{ $item['title'] }}</h3>
                            <span class="rj-usp__text">{{ $item['text'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-app-layout>
