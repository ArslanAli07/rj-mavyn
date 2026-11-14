<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $storeName = setting('site_name', config('site.name', 'RJ Motorbike'));
        $pageTitle = $title ?? ($seo->meta_title ?? $storeName);
        $description = $metaDescription ?? ($seo->meta_description ?? setting('site_tagline', ''));
        $locale = strtoupper((string) setting('store_locale', 'en'));
        $countryCode = (string) setting('store_country_code', config('site.country_code', 'PK'));
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $ogImage ?? $seo?->og_image_url ?? store_media_url('site_logo', theme_asset('images/hero.jpg')) }}">
    <meta property="og:site_name" content="{{ $storeName }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ store_media_url('site_favicon', theme_asset('images/favicon.svg')) }}">
    @foreach($theme['assets']['styles'] as $url)
        <link rel="stylesheet" href="{{ $url }}">
    @endforeach
    {{-- This layout owns one Alpine runtime; fallback styles still support inherited pages. --}}
    @foreach($theme['assets']['scripts'] as $url)
        @if(str_contains($url, '/theme-assets/rjleather.theme-storefront/'))
            <script type="module" src="{{ $url }}"></script>
        @endif
    @endforeach
    @stack('head')
    {{-- The accent var is forced to the brand ink so inherited pages stay neutral. --}}
    <style>[data-storefront-theme] { --yellow: #17120f; } [x-cloak] { display:none!important; }</style>
</head>
<body data-storefront-theme="{{ $theme['id'] }}" class="rj-storefront" data-customer-authenticated="{{ auth('web')->check() ? 'true' : 'false' }}" data-wishlist-count="{{ wishlist_count() }}" x-data="{}">
<a class="skip-link" href="#main-content">Skip to main content</a>
@php $logo = store_media_url('site_logo', ''); @endphp
{{-- The sprite contains presentation icons only. --}}
@include('components.breadcrumb', ['iconsOnly' => true])
<header class="rj-header">
    <a class="rj-announcement" href="{{ route('shop') }}">{{ $theme['settings']['announcement_text'] }} <svg class="icon icon-sm"><use href="#icon-arrow"/></svg></a>
    @unless(ordering_enabled())
        <p class="rj-ordering-notice" role="status">{{ !ordering_manually_enabled() ? 'Online ordering is paused. You can still browse our collection.' : 'Online ordering is available from '.ordering_hours_label().'.' }}</p>
    @endunless
    <div class="rj-container">
        <div class="rj-header__main">
            <a class="rj-logo" href="{{ route('home') }}" aria-label="{{ $storeName }} home">
                @if($logo)<img src="{{ $logo }}" alt="{{ $storeName }}">@else<span class="rj-monogram">RJ-MOTORBIKE</span>@endif
            </a>
            <nav class="rj-nav" aria-label="Main navigation">
                @php
                    /*
                     * The navigation is the category tree itself: the groups the
                     * merchant flagged for the landing page, each with the
                     * categories filed underneath it.
                     */
                    $navByParent = $navCategories->groupBy(fn ($category) => $category->parent_id ?? 0);
                    $navGroups = $navByParent->get(0, collect())->filter(fn ($category) => $category->show_on_homepage)->values();
                    $navChildren = fn ($category) => $navByParent->get($category->id, collect())->values();

                    /*
                     * Leather Collection navigation builder:
                     * Groups categories into clean, non-repetitive columns:
                     * 1. JACKETS (direct styles like Bomber, Biker's, etc.)
                     * 2. COATS (Blazers, Trench, etc.)
                     * 3. MORE (standalone categories like Gloves, Vests, Pants, Kilts)
                     * 4. MOVIES LEATHER JACKETS (top featured iconic titles + Shop all)
                     */
                    $buildLeatherNav = function ($group) use ($navChildren, $navCategories) {
                        $children = $navChildren($group);
                        $columns = collect();
                        $groupSlug = $group->slug;

                        // 1. Shop By Brands: the editorial collections plus the
                        //    real brand archives (MotoGP > Brands) so the menu
                        //    carries every manufacturer we stock.
                        if ($groupSlug === 'shop-by-brands') {
                            $columns->push([
                                'heading' => 'COLLECTIONS',
                                'url' => route('category.show', $group->slug),
                                'items' => $children,
                                'all_url' => route('category.show', $group->slug),
                                'all_label' => 'Shop All Collections',
                            ]);

                            $brandsParent = $navCategories->first(fn ($c) => $c->slug === 'motogp-brands');
                            $brandLeafs = $brandsParent ? $navChildren($brandsParent)->values() : collect();
                            if ($brandLeafs->isNotEmpty()) {
                                $columns->push([
                                    'heading' => 'MOTOGP BRANDS',
                                    'url' => route('category.show', $brandsParent->slug),
                                    'items' => $brandLeafs,
                                    'all_url' => route('category.show', $brandsParent->slug),
                                    'all_label' => 'Shop All Brands',
                                ]);
                            }

                            return $columns;
                        }

                        // 2. Customize: the customize categories plus service
                        //    pages, since the group itself holds few goods.
                        if ($groupSlug === 'customize') {
                            $columns->push([
                                'heading' => 'CUSTOMIZE',
                                'url' => route('category.show', $group->slug),
                                'items' => $children,
                                'all_url' => route('category.show', $group->slug),
                                'all_label' => 'Shop All Custom',
                            ]);
                            $columns->push([
                                'heading' => 'INFORMATION',
                                'links' => [
                                    ['label' => 'Made to Order', 'url' => route('about')],
                                    ['label' => 'Size Guide', 'url' => route('faq')],
                                    ['label' => 'Shipping & Delivery', 'url' => route('shipping')],
                                    ['label' => 'Returns & Exchanges', 'url' => route('returns')],
                                    ['label' => 'Contact Us', 'url' => route('contact')],
                                ],
                            ]);
                            return $columns;
                        }

                        // 3. MotoGP
                        if ($groupSlug === 'motogp') {
                            // Column 1: Jackets
                            $jacketsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'jacket'));
                            if ($jacketsCat) {
                                $leafs = $navChildren($jacketsCat);
                                $columns->push([
                                    'heading' => 'JACKETS',
                                    'url' => route('category.show', $jacketsCat->slug),
                                    'items' => $leafs->isNotEmpty() ? $leafs : collect([$jacketsCat]),
                                    'all_url' => route('category.show', $jacketsCat->slug),
                                    'all_label' => 'Shop All Jackets',
                                ]);
                            }

                            // Column 2: Brands
                            $brandsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'brand'));
                            if ($brandsCat) {
                                $leafs = $navChildren($brandsCat);
                                $columns->push([
                                    'heading' => 'BRANDS',
                                    'url' => route('category.show', $brandsCat->slug),
                                    'items' => $leafs->isNotEmpty() ? $leafs : collect([$brandsCat]),
                                    'all_url' => route('category.show', $brandsCat->slug),
                                    'all_label' => 'Shop All Brands',
                                ]);
                            }

                            // Column 3: Suits & Race
                            $raceSuitsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'race suit') || strtolower($c->name) === 'race suits');
                            $raceCat = $children->first(fn ($c) => strtolower(trim($c->name)) === 'race');
                            $raceItems = collect();
                            if ($raceSuitsCat) {
                                $raceItems = $raceItems->merge($navChildren($raceSuitsCat));
                            }
                            if ($raceCat) {
                                $raceItems = $raceItems->merge($navChildren($raceCat));
                            }
                            $suitUrl = $raceSuitsCat ? route('category.show', $raceSuitsCat->slug) : route('category.show', $group->slug);
                            $columns->push([
                                'heading' => 'SUITS & RACE',
                                'url' => $suitUrl,
                                'items' => collect([$raceSuitsCat, $raceCat])->filter()->merge($raceItems)->unique('id'),
                                'all_url' => $suitUrl,
                                'all_label' => 'Shop All Suits',
                            ]);

                            // Column 4: Rider Gear — each gear category followed
                            // by its year collections, so the sub-folders that
                            // show on the category page are reachable from the nav.
                            $gearCats = $children->filter(fn ($c) => in_array(strtolower($c->name), ['footwear', 'gloves', 'pants'], true));
                            $gearItems = $gearCats->flatMap(fn ($c) => collect([$c])->merge($navChildren($c)))->values();
                            $columns->push([
                                'heading' => 'RIDER GEAR',
                                'url' => route('category.show', $group->slug),
                                'items' => $gearItems,
                                'all_url' => route('category.show', $group->slug),
                                'all_label' => 'Shop All Gear',
                            ]);

                            return $columns;
                        }

                        // 4. Motorcycle
                        if ($groupSlug === 'motorcycle') {
                            // Column 1: Jackets
                            $jacketsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'jacket'));
                            if ($jacketsCat) {
                                $leafs = $navChildren($jacketsCat);
                                $columns->push([
                                    'heading' => 'JACKETS',
                                    'url' => route('category.show', $jacketsCat->slug),
                                    'items' => $leafs->isNotEmpty() ? $leafs : collect([$jacketsCat]),
                                    'all_url' => route('category.show', $jacketsCat->slug),
                                    'all_label' => 'Shop All Jackets',
                                ]);
                            }

                            // Column 2: Race Suits
                            $suitsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'suit'));
                            if ($suitsCat) {
                                $leafs = $navChildren($suitsCat);
                                $columns->push([
                                    'heading' => 'RACE SUITS',
                                    'url' => route('category.show', $suitsCat->slug),
                                    'items' => $leafs->isNotEmpty() ? $leafs : collect([$suitsCat]),
                                    'all_url' => route('category.show', $suitsCat->slug),
                                    'all_label' => 'Shop All Race Suits',
                                ]);
                            }

                            // Column 3: Riding Gear
                            $ridingGear = $children->reject(fn ($c) => str_contains(strtolower($c->name), 'jacket') || str_contains(strtolower($c->name), 'suit'));
                            if ($ridingGear->isNotEmpty()) {
                                $columns->push([
                                    'heading' => 'RIDING GEAR',
                                    'url' => route('category.show', $group->slug),
                                    'items' => $ridingGear->values(),
                                    'all_url' => route('category.show', $group->slug),
                                    'all_label' => 'Shop All Gear',
                                ]);
                            }

                            return $columns;
                        }

                        // 5. Outerwear & Apparel: For Men & For Women
                        $jacketsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'jacket') && !str_contains(strtolower($c->name), 'movie') && !str_contains(strtolower($c->name), 'film'));
                        if ($jacketsCat) {
                            $leafs = $navChildren($jacketsCat);
                            $columns->push([
                                'heading' => 'JACKETS',
                                'url' => route('category.show', $jacketsCat->slug),
                                'items' => $leafs->isNotEmpty() ? $leafs : collect([$jacketsCat]),
                                'all_url' => route('category.show', $jacketsCat->slug),
                                'all_label' => 'Shop All Jackets',
                            ]);
                        }

                        $coatsCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'coat'));
                        if ($coatsCat) {
                            $leafs = $navChildren($coatsCat);
                            $columns->push([
                                'heading' => 'COATS',
                                'url' => route('category.show', $coatsCat->slug),
                                'items' => $leafs->isNotEmpty() ? $leafs : collect([$coatsCat]),
                                'all_url' => route('category.show', $coatsCat->slug),
                                'all_label' => 'Shop All Coats',
                            ]);
                        }

                        // Apparel & Accessories (Chaps, Gloves, Pants, Vests, Kilts, Handbags, etc.)
                        $moviesCat = $children->first(fn ($c) => str_contains(strtolower($c->name), 'film leather jacket') || str_contains(strtolower($c->name), 'movies leather jacket'));
                        $apparelItems = $children->reject(function ($c) use ($jacketsCat, $coatsCat, $moviesCat) {
                            return in_array($c->id, array_filter([$jacketsCat?->id, $coatsCat?->id, $moviesCat?->id]), true);
                        })->values();

                        if ($apparelItems->isNotEmpty()) {
                            $columns->push([
                                'heading' => 'APPAREL & ACCESSORIES',
                                'url' => route('category.show', $group->slug),
                                'items' => $apparelItems,
                                'all_url' => route('category.show', $group->slug),
                                'all_label' => 'Shop All Apparel',
                            ]);
                        }

                        // Film / Movies Leather Jackets (Featured iconic titles + link to all)
                        if ($moviesCat) {
                            $allMovies = $navChildren($moviesCat);
                            $isWomen = str_contains(strtolower($group->name), 'women');
                            $curated = $isWomen
                                ? ['Kill Bill', 'The Matrix', 'Underworld', 'Black Widow', 'Catwoman', 'Grease', 'Cruella', 'Resident Evil']
                                : ['The Batman', 'Top Gun', 'Fight Club', 'James Bond', 'John Wick', 'Star Wars', 'Indiana Jones', 'Terminator'];

                            $sortedMovies = $allMovies->sortBy(function ($m) use ($curated) {
                                $idx = array_search($m->name, $curated, true);
                                return $idx !== false ? $idx : 99;
                            })->take(8)->values();

                            $totalFilms = $allMovies->count();
                            $columns->push([
                                'heading' => $isWomen ? 'FILM LEATHER JACKETS' : 'MOVIES LEATHER JACKETS',
                                'url' => route('category.show', $moviesCat->slug),
                                'items' => $sortedMovies->isNotEmpty() ? $sortedMovies : collect([$moviesCat]),
                                'all_url' => route('category.show', $moviesCat->slug),
                                'all_label' => $isWomen ? 'Shop All Film Jackets (' . $totalFilms . ')' : 'Shop All Movie Jackets (' . $totalFilms . ')',
                            ]);
                        }

                        return $columns;
                    };

                    $formatHeading = function ($heading) {
                        $words = preg_split('/\s+/', trim($heading));
                        if (count($words) > 3) {
                            $splitAt = count($words) === 5 ? 3 : (int) ceil(count($words) / 2);
                            $line1 = implode(' ', array_slice($words, 0, $splitAt));
                            $line2 = implode(' ', array_slice($words, $splitAt));
                            return $line1 . '<br>' . $line2;
                        }
                        return $heading;
                    };

                    /*
                     * The reference mega-menu opens on a portrait photograph of
                     * the group; the theme ships one bundled image per group
                     * and falls back silently when none is declared.
                     */
                    $navPromoPhoto = function ($group) {
                        foreach (['category/'.$group->slug.'.jpeg', 'showcase/'.$group->slug.'.jpeg'] as $path) {
                            try {
                                return theme_asset('images/'.$path);
                            } catch (\InvalidArgumentException) {
                                continue;
                            }
                        }
                        return null;
                    };
                @endphp
                <ul class="rj-nav__list">
                    @foreach($navGroups as $group)
                        @php
                            $groupChildren = $navChildren($group);
                            $navColumns = $buildLeatherNav($group);
                            $isCurrentNavGroup = request()->routeIs('category.show') && request()->route('slug') === $group->slug;
                            // Display-only: nav shows "Men"/"Women" while the DB category stays "For Men"/"For Women".
                            $navLabel = preg_replace('/^For\s+/i', '', $group->name);
                        @endphp
                        <li class="rj-nav__group {{ $isCurrentNavGroup ? 'is-open' : '' }}">
                            <span class="rj-nav__entry">
                                <a class="rj-nav__item" href="{{ route('category.show', $group->slug) }}">{{ $navLabel }}</a>
                                @if($groupChildren->isNotEmpty())
                                    <button class="rj-nav__caret" aria-expanded="{{ $isCurrentNavGroup ? 'true' : 'false' }}" aria-controls="nav-group-{{ $group->id }}" aria-label="Show {{ $navLabel }} categories"><svg class="icon"><use href="#icon-chevron"/></svg></button>
                                @endif
                            </span>
                            @if($groupChildren->isNotEmpty())
                                <div class="rj-nav__panel" id="nav-group-{{ $group->id }}">
                                    <div class="rj-nav__panel-inner">
                                        @php $navPromo = $navPromoPhoto($group); @endphp
                                        <div class="rj-nav__columns {{ $navPromo ? '' : 'rj-nav__columns--no-promo' }}">
                                            @if($navPromo)
                                                @php
                                                    // Avoid doubling the word "shop" for groups already named like actions.
                                                    $navPromoLabel = str_starts_with(strtolower($navLabel), 'shop') ? $navLabel : 'Shop '.$navLabel;
                                                @endphp
                                                <div class="rj-nav__promo">
                                                    <a class="rj-nav__promo-media" href="{{ route('category.show', $group->slug) }}" tabindex="-1" aria-hidden="true">
                                                        <img src="{{ $navPromo }}" alt="" loading="lazy">
                                                    </a>
                                                    <a class="rj-nav__promolink" href="{{ route('category.show', $group->slug) }}">{{ $navPromoLabel }} <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                                                </div>
                                            @endif
                                            @foreach($navColumns as $column)
                                                <div class="rj-nav__column">
                                                    <p class="rj-nav__heading">
                                                        @if(!empty($column['url']))
                                                            <a href="{{ $column['url'] }}">{!! $formatHeading($column['heading']) !!}</a>
                                                        @else
                                                            {!! $formatHeading($column['heading']) !!}
                                                        @endif
                                                    </p>
                                                    <ul>
                                                        @if(!empty($column['links']))
                                                            @foreach($column['links'] as $link)
                                                                <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                                                            @endforeach
                                                        @else
                                                            @foreach($column['items'] as $item)
                                                                <li>
                                                                    <a href="{{ route('category.show', $item->slug) }}">{{ $item->name }}</a>
                                                                    @if(in_array($item->nav_badge ?? null, (\App\Models\Category::NAV_BADGES), true))
                                                                        <span class="rj-nav__badge rj-nav__badge--{{ $item->nav_badge }}">{{ strtoupper($item->nav_badge) }}</span>
                                                                    @endif
                                                                </li>
                                                            @endforeach
                                                        @endif
                                                        @if(!empty($column['all_url']))
                                                            <li class="rj-nav__all"><a href="{{ $column['all_url'] }}">{{ $column['all_label'] ?? 'Shop All' }} <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a></li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                    <li><a class="rj-nav__item" href="{{ route('about') }}">Our Story</a></li>
                    <li><a class="rj-nav__item" href="{{ route('contact') }}">Contact</a></li>
                </ul>
        </nav>
            <div class="rj-header__tools">
                {{-- The pill search keeps a rotating placeholder and opens a
                     quick panel of the store's main categories on focus. --}}
                <div class="rj-searchwrap">
                    <form class="rj-search" action="{{ route('search') }}" role="search">
                        <svg class="icon" aria-hidden="true"><use href="#icon-search"/></svg>
                        <label class="sr-only" for="header-search-input">Search products</label>
                        <input id="header-search-input" type="search" name="q" placeholder="Search for... Jackets" value="{{ request('q') }}" maxlength="255" autocomplete="off"
                               data-rotating-placeholder data-placeholder-terms="Jackets,Motorcycle gear,MotoGP suits,Gloves,Coats,Chaps">
                    </form>
                    <div class="rj-search__panel" data-search-panel hidden>
                        <p class="rj-search__label">Popular categories</p>
                        <ul>
                            @foreach($navGroups as $quickGroup)
                                <li><a href="{{ route('category.show', $quickGroup->slug) }}">{{ preg_replace('/^For\s+/i', '', $quickGroup->name) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <span class="rj-tool rj-tool--country">{{ $countryCode }} | {{ $locale }}</span>
                <a class="rj-tool" href="{{ auth('web')->check() ? route('account.dashboard') : route('login') }}" aria-label="Your account"><svg class="icon"><use href="#icon-user"/></svg></a>
                <a class="rj-tool wishlist-toggle" data-wishlist-link href="{{ route('wishlist.index') }}" aria-label="Your wishlist"><svg class="icon"><use href="#icon-heart"/></svg><span class="rj-tool__count wishlist-count" hidden>0</span></a>
                <button class="rj-tool bag-toggle" data-dialog="bag-dialog" aria-label="Shopping bag"><svg class="icon"><use href="#icon-bag"/></svg><span class="rj-tool__count" data-cart-badge @if(cart_count() === 0) hidden @endif>{{ cart_count() }}</span></button>
                <button class="rj-header__toggle mobile-menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu"><svg class="icon"><use href="#icon-menu"/></svg></button>
            </div>
        </div>
    </div>
    <nav id="mobile-menu" class="rj-mobile-menu" aria-label="Mobile navigation" hidden>
        @foreach($navGroups as $group)
            @php
                $mobileChildren = $navChildren($group);
                $mobileLabel = preg_replace('/^For\s+/i', '', $group->name);
            @endphp
            @if($mobileChildren->isEmpty())
                <a href="{{ route('category.show', $group->slug) }}">{{ $mobileLabel }} <span aria-hidden="true">→</span></a>
            @else
                <div class="rj-mobile-menu__group">
                    <div class="rj-mobile-menu__group-head">
                        <a href="{{ route('category.show', $group->slug) }}">{{ $mobileLabel }}</a>
                        <button type="button" class="rj-mobile-menu__toggle" data-mobile-nav-toggle aria-expanded="false" aria-controls="mobile-nav-group-{{ $group->id }}" aria-label="Show {{ $mobileLabel }} categories">+</button>
                    </div>
                    <div id="mobile-nav-group-{{ $group->id }}" class="rj-mobile-menu__children" hidden>
                        @foreach($mobileChildren as $child)
                            <a class="rj-mobile-menu__child" href="{{ route('category.show', $child->slug) }}">{{ $child->name }}</a>
                            @foreach($navChildren($child) as $leaf)
                                <a class="rj-mobile-menu__child rj-mobile-menu__leaf" href="{{ route('category.show', $leaf->slug) }}">{{ $leaf->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
        <a href="{{ route('about') }}">Our Story <span aria-hidden="true">→</span></a>
        <a href="{{ route('contact') }}">Contact <span aria-hidden="true">→</span></a>
        <form action="{{ route('search') }}" role="search"><label for="mobile-search" class="sr-only">Search products</label><input type="search" name="q" id="mobile-search" placeholder="Search {{ $storeName }}" maxlength="255"><button aria-label="Search"><svg class="icon"><use href="#icon-search"/></svg></button></form>
    </nav>
</header>
@if(session('success'))<p class="rj-flash" role="status">{{ session('success') }}</p>@endif
@if(session('error'))<p class="rj-flash rj-error" role="alert">{{ session('error') }}</p>@endif
@if($errors->any())<div class="rj-flash rj-error" role="alert"><p>Please check the following:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<main id="main-content">{{ $slot }}</main>
@php
    $footerAddress = trim((string) setting('site_address'));
    $footerPhone = trim((string) setting('site_phone'));
    $footerEmail = trim((string) setting('site_email'));
    $socials = collect([
        ['url' => trim((string) setting('facebook_url')), 'icon' => 'icon-facebook', 'label' => 'Facebook'],
        ['url' => trim((string) setting('instagram_url')), 'icon' => 'icon-instagram', 'label' => 'Instagram'],
        ['url' => trim((string) setting('tiktok_url')), 'icon' => 'icon-tiktok', 'label' => 'TikTok'],
        ['url' => trim((string) setting('site_whatsapp')), 'icon' => 'icon-whatsapp', 'label' => 'WhatsApp'],
    ])->filter(fn ($item) => $item['url'] !== '');
    $footerColumns = [
        ['title' => 'Customer Service', 'links' => [
            ['label' => 'Contact Us', 'url' => route('contact')],
            ['label' => 'Help Centre', 'url' => route('faq')],
            ['label' => 'Delivery', 'url' => route('shipping')],
            ['label' => 'Returns', 'url' => route('returns')],
            ['label' => 'Track Your Order', 'url' => route('orders.index')],
        ]],
        ['title' => 'About Us', 'links' => [
            ['label' => 'Our Story', 'url' => route('about')],
            ['label' => 'My Account', 'url' => route('account.dashboard')],
            ['label' => 'Wishlist', 'url' => route('wishlist.index')],
            ['label' => 'Gallery', 'url' => route('gallery')],
        ]],
        ['title' => 'Shop', 'links' => [
            ['label' => 'Shop all', 'url' => route('shop')],
            ...$navGroups->map(fn ($group) => [
                'label' => preg_replace('/^For\s+/i', '', $group->name),
                'url' => route('category.show', $group->slug),
            ])->all(),
        ]],
    ];
    $footerLegal = [
        ['label' => 'Terms & Conditions', 'url' => route('terms')],
        ['label' => 'Privacy Policy', 'url' => route('privacy')],
        ['label' => 'Refund Policy', 'url' => route('returns')],
        ['label' => 'Shipping Policy', 'url' => route('shipping')],
    ];
@endphp
<footer class="rj-footer">
    <div class="rj-container">
        <div class="rj-footer__blocks">
            <div class="rj-footer__news">
                <h2 class="rj-footer__title">Sign up now</h2>
                <p class="rj-footer__pitch">Join the {{ $storeName }} community. Subscribe to our newsletter for access to exclusive offers, and inspiration for wherever you may be headed.</p>
                <form class="rj-newsletter-form rj-footer__form" action="{{ route('newsletter.subscribe') }}" method="POST">@csrf<x-honeypot />
                    <label class="sr-only" for="footer-email">Email address</label>
                    <input id="footer-email" type="email" name="email" autocomplete="email" placeholder="Enter your email" required maxlength="255">
                    <div class="rj-footer__dob">
                        <label for="footer-dob">Date of Birth (Optional)</label>
                        <input id="footer-dob" type="date" name="dob" autocomplete="bday">
                    </div>
                    <div class="rj-footer__meta">
                        <fieldset class="rj-footer__gender">
                            <legend class="sr-only">I am shopping for</legend>
                            <label><input type="radio" name="gender" value="men"> Men</label>
                            <label><input type="radio" name="gender" value="women"> Women</label>
                        </fieldset>
                        <a class="rj-footer__privacy" href="{{ route('privacy') }}">Privacy policy</a>
                    </div>
                    <button type="submit" class="rj-footer__submit">Submit</button>
                    <p class="rj-footer__status" data-form-status role="status"></p>
                </form>
                <p class="rj-footer__company">{{ $storeName }}@if($footerAddress) | {{ $footerAddress }}@endif</p>
            </div>
            <div class="rj-footer__nav">
                <div class="rj-footer__columns">
                    @foreach($footerColumns as $column)
                        <details class="rj-footer__col" open>
                            <summary class="rj-footer__coltitle">
                                {{ $column['title'] }}
                                <span class="rj-footer__colicons" aria-hidden="true">
                                    <svg class="icon rj-footer__colplus"><use href="#icon-plus"/></svg>
                                    <svg class="icon rj-footer__colminus"><use href="#icon-minus"/></svg>
                                </span>
                            </summary>
                            <div class="rj-footer__collinks">
                                @foreach($column['links'] as $link)
                                    <a class="rj-footer__link" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                    <div class="rj-footer__col rj-footer__col--static">
                        <p class="rj-footer__group-title">Follow us</p>
                        @if($socials->isNotEmpty())
                            <div class="rj-footer__social">
                                @foreach($socials as $social)<a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><svg class="icon"><use href="#{{ $social['icon'] }}"/></svg></a>@endforeach
                            </div>
                        @endif
                        <p class="rj-footer__group-title rj-footer__group-title--gap">Legal</p>
                        <div class="rj-footer__collinks">
                            @foreach($footerLegal as $link)
                                <a class="rj-footer__link" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="rj-footer__bottom">
            <span>© {{ date('Y') }} {{ $storeName }}. {{ setting('store_country', config('site.country')) }}</span>
            <div>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms &amp; Conditions</a>
                <a href="{{ route('contact') }}">Contact</a>
            </div>
        </div>
    </div>
    {{-- The wordmark stretches to the full container width whatever the brand length. --}}
    <div class="rj-container">
        <svg class="rj-footer__wordmark" viewBox="0 0 1000 104" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
            <text x="0" y="94" textLength="1000" lengthAdjust="spacingAndGlyphs">{{ strtoupper($brandName ?? $storeName) }}</text>
        </svg>
    </div>
</footer>
<dialog id="bag-dialog" class="dialog drawer-dialog" aria-labelledby="bag-title">
    <div class="dialog__panel">
        <button class="dialog-close" aria-label="Close shopping bag"><svg class="icon"><use href="#icon-close"/></svg></button>
        <div data-bag-content>
            <h2 id="bag-title">Your bag <span>({{ cart_count() }})</span></h2>
            @if($cartItems->isEmpty())
                <div class="rj-cart__empty">
                    <h3>Your bag is empty</h3>
                    <p>Find something to make your own.</p>
                    <a class="rj-button" href="{{ route('shop') }}">Start shopping <svg class="icon"><use href="#icon-arrow"/></svg></a>
                </div>
            @else
                <div class="rj-bag__items">@foreach($cartItems as $item)
                    <div class="rj-bag__line"><span class="rj-bag__thumb"><img src="{{ $item->product?->main_image_url ?? theme_asset('images/placeholder.svg') }}" alt="{{ $item->product?->title }}"></span><div><h3 class="rj-bag__title">{{ $item->product?->title }}</h3>@include('components.item-configuration', ['item' => $item, 'class' => 'rj-item-config', 'labelClass' => 'rj-item-config__row', 'optionClass' => 'rj-item-config__row', 'titleClass' => 'rj-item-config__label', 'adjustmentClass' => 'rj-item-config__row'])<p class="rj-bag__meta">Quantity: {{ $item->quantity }}</p><strong>{{ format_price($item->line_total) }}</strong><form action="{{ route('cart.remove', $item->id) }}" method="POST" data-bag-remove>@csrf @method('DELETE')<button type="submit" class="rj-bag__remove">Remove</button></form></div></div>
                @endforeach</div>
                <div class="rj-bag__total"><span>Subtotal</span><span class="bag-subtotal">{{ format_price($drawerTotals['subtotal']) }}</span></div>
                <div class="rj-bag__actions">
                    <a class="rj-button" href="{{ route('cart.index') }}">View bag <svg class="icon"><use href="#icon-arrow"/></svg></a>
                    <a class="rj-textlink" href="{{ route('checkout.index') }}">Checkout <svg class="icon"><use href="#icon-arrow"/></svg></a>
                </div>
            @endif
        </div>
    </div>
</dialog>
<dialog id="gallery-dialog" class="dialog lightbox-dialog" aria-label="Product image gallery">
    <div class="lightbox-shell dialog__panel">
        <div class="lightbox-rail" data-lightbox-thumbs hidden></div>
        <div class="lightbox-zoom" role="group" aria-label="Zoom controls">
            <button type="button" data-lightbox-zoom="1" aria-label="Zoom in">+</button>
            <button type="button" data-lightbox-zoom="-1" aria-label="Zoom out">&minus;</button>
        </div>
        <button class="dialog-close" aria-label="Close gallery"><svg class="icon"><use href="#icon-close"/></svg></button>
        <button type="button" class="lightbox-arrow lightbox-arrow--prev" data-lightbox-step="-1" aria-label="Previous image"><svg class="icon lightbox-chevron lightbox-chevron--prev" aria-hidden="true"><use href="#icon-chevron"/></svg></button>
        <div class="lightbox-stage">
            <img class="lightbox-image" alt="">
        </div>
        <button type="button" class="lightbox-arrow lightbox-arrow--next" data-lightbox-step="1" aria-label="Next image"><svg class="icon lightbox-chevron lightbox-chevron--next" aria-hidden="true"><use href="#icon-chevron"/></svg></button>
        <span class="lightbox-position"></span>
    </div>
</dialog>
<div class="toast" role="status" aria-live="polite" hidden></div>
@stack('scripts')
@if($theme['preview'])
    <aside role="status" class="fixed bottom-0 inset-x-0 z-[100] bg-white border-t p-3 text-center">Theme preview · Changes are not saved. Forms are disabled. <a href="{{ route('admin.themes.index') }}">Return to themes</a></aside>
    <script>
        const previewToken = @js(request()->query('_theme_preview'));
        const previewPaths = @js($themePreviewPaths);
        document.querySelectorAll('a[href]').forEach(link => {
            const url = new URL(link.href, location.href);
            if (url.origin === location.origin && (previewPaths.includes(url.pathname) || /^\/(products|category)\/[^/]+$/.test(url.pathname))) {
                url.searchParams.set('_theme_preview', previewToken);
                link.href = url.toString();
            }
        });
        const previewFetch = window.fetch.bind(window);
        window.fetch = (input, options = {}) => {
            const method = (options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
            if (!['GET', 'HEAD'].includes(method)) return Promise.reject(new Error('Forms are disabled during theme preview.'));
            return previewFetch(input, options);
        };
        document.querySelectorAll('form').forEach(form => {
            if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') {
                form.addEventListener('submit', event => { event.preventDefault(); event.stopImmediatePropagation(); }, true);
                form.querySelectorAll('button, input, select, textarea').forEach(control => control.disabled = true);
            } else {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = '_theme_preview'; input.value = previewToken;
                form.appendChild(input);
            }
        });
    </script>
@endif
</body>
</html>
