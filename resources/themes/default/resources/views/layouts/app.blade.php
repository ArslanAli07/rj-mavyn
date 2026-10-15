<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" sizes="32x32" href="{{ store_media_url('site_favicon', asset('favicon-32x32.png')) }}">
    <link rel="icon" sizes="16x16" href="{{ store_media_url('site_favicon', asset('favicon-16x16.png')) }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    {{-- SEO --}}
    @php
        
        $resolvedMetaTitle       = $seo?->meta_title ?? $title ?? setting('meta_title', setting('site_name', config('site.name', 'Store')));
        $resolvedMetaDescription = $metaDescription ?? $seo?->meta_description ?? setting('meta_description', setting('site_tagline', ''));
        $resolvedMetaKeywords    = $metaKeywords ?? $seo?->meta_keywords ?? '';
        $resolvedOgImage         = $ogImage ?? $seo?->og_image_url ?? asset('assets/android-chrome-512x512.png');
    @endphp

    <title>{{ $resolvedMetaTitle }}</title>
    <meta name="description" content="{{ $resolvedMetaDescription }}">
    <meta name="keywords" content="{{ $resolvedMetaKeywords }}">
    <meta name="robots" content="index, follow">
    <meta property="og:title"       content="{{ $resolvedMetaTitle }}">
    <meta property="og:description" content="{{ $resolvedMetaDescription }}">
    <meta property="og:image"       content="{{ $resolvedOgImage }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ url()->current() }}">
    <meta property="og:site_name"   content="{{ setting('site_name', config('site.name', 'Store')) }}">
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $resolvedMetaTitle }}">
    <meta name="twitter:description" content="{{ $resolvedMetaDescription }}">
    <meta name="twitter:image"       content="{{ $resolvedOgImage }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">

    <!-- Google Fonts: Outfit, Syne, Fraunces -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Syne:wght@700;800&family=Fraunces:ital,opsz,wght@0,9..144,300..900;1,9..144,300..900&display=swap" rel="stylesheet">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <!-- Swiper and GSAP JS CDNs -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>

    <style>[x-cloak] { display: none !important; }</style>


    @foreach($theme['assets']['styles'] as $url)<link rel="stylesheet" href="{{ $url }}">@endforeach
    <style>[data-storefront-theme] { --color-primary: {{ $theme['settings']['accent_color'] }}; }</style>
    @foreach($theme['assets']['scripts'] as $url)<script type="module" src="{{ $url }}"></script>@endforeach
    @stack('head')
</head>
<body data-storefront-theme="{{ $theme['id'] }}" class="font-sans bg-bg text-text antialiased storefront-theme pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-0"
      x-data="{ mobileOpen: false, searchOpen: false, cartOpen: false, scrolled: false, _scrollY: 0, orderingPausedNotice: {{ ordering_manually_enabled() ? 'false' : 'true' }} }"
      x-effect="
          if (cartOpen || (searchOpen && window.innerWidth < 1024) || mobileOpen || orderingPausedNotice) {
              _scrollY = window.scrollY;
              document.documentElement.style.scrollBehavior = 'auto';
              document.body.classList.add('scroll-locked');
              document.body.style.top = '-' + _scrollY + 'px';
              setTimeout(() => {
                  document.documentElement.style.scrollBehavior = '';
              }, 50);
          } else {
              document.documentElement.style.scrollBehavior = 'auto';
              document.body.classList.remove('scroll-locked');
              document.body.style.top = '';
              window.scrollTo(0, _scrollY);
              setTimeout(() => {
                  document.documentElement.style.scrollBehavior = '';
              }, 50);
          }
      "
      @scroll.window="scrolled = window.scrollY > 60">

    @php
        $orderingEnabled = ordering_enabled();
        $orderingManuallyEnabled = ordering_manually_enabled();
    @endphp

    <!-- Custom Cursor -->
    <div class="custom-cursor hidden md:block" id="customCursor"></div>
    <div class="custom-cursor-follower hidden md:block" id="customCursorFollower"></div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="toast-success max-w-sm !top-4 !bottom-auto !z-[60]">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
            <button @click="show = false" class="ml-auto text-white/85 hover:text-white transition-colors p-1 rounded-md focus:outline-none" aria-label="Close alert">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)"
             class="toast-error max-w-sm !top-4 !bottom-auto !z-[60]">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ session('error') }}</span>
            <button @click="show = false" class="ml-auto text-white/85 hover:text-white transition-colors p-1 rounded-md focus:outline-none" aria-label="Close alert">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Dynamic Toasts Container --}}
    <div x-data="{ 
            toasts: [],
            addToast(message, type = 'success') {
                const id = Date.now();
                this.toasts.push({ id, message, type });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 4000);
            }
         }"
         @toast.window="addToast($event.detail.message, $event.detail.type)"
         class="fixed top-20 right-4 z-[100] flex flex-col gap-2 max-w-sm pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition
                 :class="t.type === 'success' ? 'toast-success' : (t.type === 'error' ? 'toast-error' : 'toast-info')"
                 class="!relative !top-auto !bottom-auto !right-auto pointer-events-auto shadow-luxury">
                <template x-if="t.type === 'success'">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </template>
                <template x-if="t.type === 'error'">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </template>
                <template x-if="t.type === 'info'">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </template>
                <span x-text="t.message"></span>
                <button @click="toasts = toasts.filter(item => item.id !== t.id)" class="ml-auto text-white/85 hover:text-white transition-colors p-1 rounded-md focus:outline-none" aria-label="Close alert">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <div id="site-top" class="fixed top-0 inset-x-0 z-[60]">
        @unless($orderingEnabled)
        <div class="bg-espresso-800 text-cream-50 text-center text-xs sm:text-sm px-3 sm:px-6 py-2.5 leading-snug">
            <p class="mx-auto">
                @if(!$orderingManuallyEnabled)
                    Online ordering is paused. You can still browse our catalog. Please contact us for assistance.
                @else
                    The shop is currently closed. Online ordering is available from {{ ordering_hours_label() }}.
                @endif
            </p>
        </div>
        @endunless

    {{-- Navbar --}}
    <header x-data="{ 
                query: '', 
                suggestions: [], 
                loading: false, 
                activeSuggestionIndex: -1,
                fetchSuggestions() {
                    if (this.query.trim().length < 2) {
                        this.suggestions = [];
                        this.activeSuggestionIndex = -1;
                        return;
                    }
                    this.loading = true;
                    fetch('{{ route('search.suggestions') }}?q=' + encodeURIComponent(this.query))
                        .then(res => res.json())
                        .then(data => {
                            this.suggestions = data;
                            this.loading = false;
                            this.activeSuggestionIndex = -1;
                        })
                        .catch(() => {
                            this.loading = false;
                        });
                },
                clearSearch() {
                    this.query = '';
                    this.suggestions = [];
                    this.activeSuggestionIndex = -1;
                    searchOpen = false;
                }
            }"
            :class="scrolled ? 'bg-bg/95 shadow-sm py-2 md:py-3' : 'bg-bg/80 py-2 md:py-5'"
            class="w-full transition-all duration-300 backdrop-blur-md">
        <div class="w-full px-4 md:px-12">
            <div class="flex items-center justify-center gap-4 md:justify-between">

                <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0 hover-trigger group tracking-tight md:gap-3">
                    <div class="w-8 h-8 md:w-10 md:h-10 rounded-full overflow-hidden transition-all duration-300 group-hover:scale-105 shadow-sm">
                        <img src="{{ store_media_url('site_logo', asset('images/placeholder.svg')) }}" alt="our store" class="w-full h-full object-cover" width="40" height="40">
                    </div>
                    <span class="text-base font-bold font-fraunces text-ink leading-none md:text-lg">{{ setting('site_name', config('site.name', 'Store')) }}</span>
                </a>

                <nav class="hidden md:flex items-center gap-10">
                    <a href="{{ route('home') }}" class="text-sm font-medium tracking-wide transition-colors hover-trigger relative py-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:h-[1px] after:bg-ink after:transition-all hover:after:w-full {{ request()->routeIs('home') ? 'text-ink after:w-full' : 'text-ink/75 hover:text-ink after:w-0' }}">Home</a>
                    <a href="{{ route('shop') }}" class="text-sm font-medium tracking-wide transition-colors hover-trigger relative py-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:h-[1px] after:bg-ink after:transition-all hover:after:w-full {{ request()->routeIs('shop', 'category.*', 'product.*') ? 'text-ink after:w-full' : 'text-ink/75 hover:text-ink after:w-0' }}">Catalog</a>
                    <a href="{{ route('about') }}" class="text-sm font-medium tracking-wide transition-colors hover-trigger relative py-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:h-[1px] after:bg-ink after:transition-all hover:after:w-full {{ request()->routeIs('about') ? 'text-ink after:w-full' : 'text-ink/75 hover:text-ink after:w-0' }}">About Us</a>
                    <a href="{{ route('gallery') }}" class="text-sm font-medium tracking-wide transition-colors hover-trigger relative py-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:h-[1px] after:bg-ink after:transition-all hover:after:w-full {{ request()->routeIs('gallery') ? 'text-ink after:w-full' : 'text-ink/75 hover:text-ink after:w-0' }}">Gallery</a>
                </nav>

                <div class="hidden items-center gap-4 md:flex">
                    <div class="hidden md:flex items-center relative transition-all duration-300" :class="searchOpen ? 'w-48 xl:w-64' : 'w-auto'" @click.outside="if (searchOpen) clearSearch()">
                        <form id="desktop-search-form" action="{{ route('search') }}" method="GET"
                              x-show="searchOpen"
                              x-cloak
                              x-transition:enter="transition ease-out duration-300"
                              x-transition:enter-start="opacity-0 scale-95"
                              x-transition:enter-end="opacity-100 scale-100"
                              class="w-full flex items-center gap-2 bg-surface rounded-full px-4 py-2 border border-border focus-within:ring-2 focus-within:ring-primary/30 focus-within:border-primary transition-all">
                            <svg class="w-4 h-4 text-ink/60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="q"
                                   x-model.debounce.300ms="query"
                                   @input="fetchSuggestions"
                                   @keydown.arrow-down.prevent="activeSuggestionIndex = Math.min(activeSuggestionIndex + 1, suggestions.length - 1)"
                                   @keydown.arrow-up.prevent="activeSuggestionIndex = Math.max(activeSuggestionIndex - 1, -1)"
                                   @keydown.enter="if(activeSuggestionIndex >= 0) { window.location.href = suggestions[activeSuggestionIndex].url; $event.preventDefault(); }"
                                   placeholder="Search catalog..."
                                   class="w-full bg-transparent border-0 outline-none text-sm text-ink placeholder-text-muted focus:ring-0 p-0"
                                   id="desktop-search-input">
                            <button type="button" @click="clearSearch()" class="text-ink/40 hover:text-ink transition-colors p-0.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>

                        <button x-show="!searchOpen" @click="searchOpen = true; $nextTick(() => document.getElementById('desktop-search-input').focus())"
                                class="text-ink/75 hover:text-ink transition-colors hover-trigger p-1" title="Search" aria-label="Search catalog">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z"/></svg>
                        </button>

                        <div x-show="searchOpen" x-cloak
                             class="absolute top-full left-0 right-0 mt-2 bg-bg rounded-2xl shadow-lg border border-border overflow-hidden z-50">
                             <div x-show="query.trim().length < 2" class="px-5 py-4">
                                 <p class="text-[10px] font-bold text-text-muted uppercase tracking-widest mb-3">Popular searches</p>
                                 <div class="flex flex-wrap gap-2">
                                     @foreach(['New arrivals', 'Bestsellers'] as $s)
                                         <a href="{{ route('search', ['q' => $s]) }}" class="px-3.5 py-1.5 bg-surface text-ink text-xs font-semibold rounded-full hover:bg-primary-tint transition-colors">{{ $s }}</a>
                                     @endforeach
                                 </div>
                             </div>
                             <div x-show="loading && query.trim().length >= 2" class="px-4 py-3.5 text-xs text-text-muted flex items-center gap-2">
                                 <svg class="animate-spin h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24">
                                     <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                     <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                 </svg>
                                 Searching...
                             </div>
                             <div x-show="!loading && suggestions.length > 0 && query.trim().length >= 2" class="divide-y divide-border">
                                 <template x-for="(item, index) in suggestions" :key="item.id">
                                     <a :href="item.url"
                                        :class="index === activeSuggestionIndex ? 'bg-surface text-primary' : 'text-ink hover:bg-surface'"
                                        class="flex items-center gap-3 px-4 py-3 transition-colors">
                                         <img :src="item.image" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0">
                                         <div class="flex-1 min-w-0">
                                             <p class="text-sm font-semibold truncate" x-text="item.title"></p>
                                             <p class="text-xs text-primary font-bold" x-text="item.price"></p>
                                         </div>
                                     </a>
                                 </template>
                             </div>
                             <div x-show="!loading && suggestions.length === 0 && query.trim().length >= 2" class="px-4 py-5 text-center text-sm text-text-muted">
                                  No products found for "<span x-text="query" class="font-medium text-ink"></span>"
                             </div>
                             <div x-show="suggestions.length > 0 && query.trim().length >= 2" class="bg-surface px-4 py-2.5 border-t border-border flex justify-between items-center">
                                 <span class="text-[10px] text-text-muted">Press Enter to search all</span>
                                 <button type="submit" form="desktop-search-form" class="text-xs text-primary font-bold hover:text-primary-hover">View All →</button>
                             </div>
                        </div>
                    </div>

                    <button @click="searchOpen = true; mobileOpen = false; $nextTick(() => document.getElementById('mobile-search-input').focus())"
                            class="md:hidden text-ink/75 hover:text-ink transition-colors hover-trigger p-1" title="Search" aria-label="Search catalog">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z"/></svg>
                    </button>
                    <a href="{{ route('wishlist.index') }}"
                       @click.prevent="
                            let ids = JSON.parse(localStorage.getItem('wishlist') || '[]');
                            @auth
                                window.location.href = '{{ route('wishlist.index') }}';
                            @else
                                window.location.href = '{{ route('wishlist.index') }}?ids=' + ids.join(',');
                            @endauth
                       "
                       class="hidden md:flex text-ink/75 hover:text-ink transition-colors hover-trigger p-1 relative" title="Wishlist" aria-label="Wishlist">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                    </a>
                    @if($orderingEnabled)
                    <button @click="cartOpen = true" class="text-ink/75 hover:text-ink transition-colors hover-trigger p-1 relative" title="Cart" id="cart-btn" aria-label="Cart">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span id="cart-badge" data-cart-badge class="{{ cart_count() > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-primary text-ink text-[9px] font-bold flex items-center justify-center">{{ cart_count() }}</span>
                    </button>
                    @endif
                    @auth
                    <div x-data="{ open: false }" class="relative hidden md:block">
                        <button @click="open = !open" @click.outside="open = false" class="text-ink/75 hover:text-ink transition-colors hover-trigger p-1" aria-label="Account">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="absolute top-full right-0 mt-2 w-48 bg-bg rounded-2xl shadow-lg py-2 border border-border z-10">
                            <a href="{{ route('account.dashboard') }}" class="block px-4 py-2.5 text-sm text-ink hover:bg-surface">My Account</a>
                            <a href="{{ route('orders.index') }}" class="block px-4 py-2.5 text-sm text-ink hover:bg-surface">My Orders</a>
                            <a href="{{ route('wishlist.index') }}" class="block px-4 py-2.5 text-sm text-ink hover:bg-surface">Wishlist</a>
                            <hr class="my-1 border-border">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">Log Out</button>
                            </form>
                        </div>
                    </div>
                    @else
                    <a href="{{ route('login') }}" class="hidden md:flex text-ink/75 hover:text-ink transition-colors hover-trigger p-1" aria-label="Account" title="Sign In">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    </a>
                    @endauth
                    <button @click="mobileOpen = !mobileOpen" class="md:hidden text-ink/75 hover:text-ink transition-colors hover-trigger p-1" aria-label="Open catalog">
                        <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/></svg>
                        <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div x-show="mobileOpen" x-cloak x-transition class="md:hidden mt-4 pb-4 border-t border-border">
                <nav class="flex flex-col gap-1 mt-4">
                    <a href="{{ route('home') }}" @click="mobileOpen = false" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Home</a>
                    <a href="{{ route('shop') }}" @click="mobileOpen = false" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Catalog / Shop</a>
                    <a href="{{ route('about') }}" @click="mobileOpen = false" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">About Us</a>
                    <a href="{{ route('gallery') }}" @click="mobileOpen = false" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Gallery</a>
                    <button type="button" @click="searchOpen = true; mobileOpen = false; $nextTick(() => document.getElementById('mobile-search-input').focus())" class="text-left px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Search Catalog</button>
                    @if($orderingEnabled)
                    <button type="button" @click="cartOpen = true; mobileOpen = false" class="text-left px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Cart ({{ cart_count() }})</button>
                    @endif
                    <a href="{{ route('wishlist.index') }}" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Wishlist</a>
                    <hr class="my-2 border-border">
                    @auth
                    <a href="{{ route('account.dashboard') }}" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">My Account</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-3 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50">Log Out</button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="px-4 py-3 rounded-lg text-sm font-medium text-ink/80 hover:text-ink hover:bg-surface transition-all">Login / Register</a>
                    @endauth
                    <div class="p-2 pt-3">
                        <a href="{{ route('shop') }}" @click="mobileOpen = false" class="block w-full text-center px-6 py-3 rounded-full bg-primary text-ink text-sm font-semibold hover:bg-primary-hover transition-colors">{{ $orderingEnabled ? 'Order Now' : 'View Catalog' }}</a>
                    </div>
                </nav>
            </div>

            <div x-show="searchOpen" x-cloak
                 class="fixed inset-0 z-[80] flex h-dvh flex-col bg-bg pt-[env(safe-area-inset-top)] md:hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <div class="flex h-16 shrink-0 items-center gap-3 border-b border-border px-4">
                    <button @click="clearSearch()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-ink transition-colors hover:bg-surface" aria-label="Close search">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                    <form id="mobile-search-form" action="{{ route('search') }}" method="GET" class="flex h-11 min-w-0 flex-1 items-center gap-2 rounded-full border border-border bg-surface px-4">
                        <svg class="w-4 h-4 text-ink/60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" name="q" x-model.debounce.300ms="query" @input="fetchSuggestions"
                               placeholder="Search catalog..."
                               class="w-full bg-transparent border-0 outline-none text-sm text-ink placeholder-text-muted focus:ring-0 p-0"
                               id="mobile-search-input"
                               @keydown.enter="if(activeSuggestionIndex >= 0) { window.location.href = suggestions[activeSuggestionIndex].url; $event.preventDefault(); }">
                        <button type="button" x-show="query.length > 0" @click="query = ''; suggestions = []" class="text-ink/40 p-0.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </form>
                </div>

                <div x-show="searchOpen" class="min-h-0 flex-1 overflow-y-auto bg-bg pb-[calc(5rem+env(safe-area-inset-bottom))]">
                     <div x-show="query.trim().length < 2" class="px-5 py-5">
                         <p class="text-[10px] font-bold text-text-muted uppercase tracking-widest mb-3">Popular searches</p>
                         <div class="flex flex-wrap gap-2">
                             @foreach(['New arrivals', 'Bestsellers'] as $s)
                                 <a href="{{ route('search', ['q' => $s]) }}" class="px-3.5 py-1.5 bg-surface text-ink text-xs font-semibold rounded-full hover:bg-primary-tint transition-colors">{{ $s }}</a>
                             @endforeach
                         </div>
                     </div>
                     <div x-show="loading && query.trim().length >= 2" class="px-4 py-4 text-sm text-text-muted flex items-center gap-2">
                         <svg class="animate-spin h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24">
                             <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                             <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                         </svg>
                         Searching...
                     </div>
                     <div x-show="!loading && suggestions.length > 0 && query.trim().length >= 2" class="divide-y divide-border">
                         <template x-for="item in suggestions" :key="item.id">
                             <a :href="item.url" class="flex items-center gap-3 px-4 py-3.5 hover:bg-surface transition-colors">
                                 <img :src="item.image" alt="" class="w-12 h-12 rounded-xl object-cover shrink-0">
                                 <div class="flex-1 min-w-0">
                                     <p class="text-sm font-semibold text-ink truncate" x-text="item.title"></p>
                                     <p class="text-xs text-primary font-bold" x-text="item.price"></p>
                                 </div>
                             </a>
                         </template>
                     </div>
                     <div x-show="!loading && suggestions.length === 0 && query.trim().length >= 2" class="px-4 py-8 text-center text-sm text-text-muted">
                          No products found for "<span x-text="query" class="font-medium text-ink"></span>"
                     </div>
                     <div x-show="suggestions.length > 0 && query.trim().length >= 2" class="bg-surface px-4 py-3.5 flex justify-between items-center sticky bottom-0 border-t border-border">
                         <span class="text-xs text-text-muted">View all search results</span>
                         <button type="submit" form="mobile-search-form" class="text-sm text-primary font-bold">View All →</button>
                     </div>
                </div>
            </div>
        </div>

    </header>
    </div>
    <div id="site-top-spacer" class="shrink-0" aria-hidden="true"
         style="height: {{ $orderingEnabled ? '81px' : '130px' }}"></div>

    {{-- Mobile App Navigation --}}
    <nav class="fixed inset-x-0 bottom-0 z-[60] border-t border-border bg-bg/95 px-5 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_30px_rgba(74,46,34,0.08)] backdrop-blur-xl md:hidden" aria-label="Mobile navigation">
        <div class="mx-auto grid h-[4.5rem] max-w-md {{ $orderingEnabled ? 'grid-cols-5' : 'grid-cols-4' }}">
            <a href="{{ route('home') }}"
               class="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold transition-colors {{ request()->routeIs('home') ? 'text-primary' : 'text-text-muted' }}"
               @if(request()->routeIs('home')) aria-current="page" @endif>
                <svg class="h-5 w-5" fill="{{ request()->routeIs('home') ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875C9.75 15.504 10.254 15 10.875 15h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
                </svg>
                <span>Home</span>
            </a>

            <a href="{{ route('shop') }}"
               class="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold transition-colors {{ request()->routeIs('shop', 'category.*', 'product.*') ? 'text-primary' : 'text-text-muted' }}"
               @if(request()->routeIs('shop', 'category.*', 'product.*')) aria-current="page" @endif>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5M4.5 4.5l1.125 15h12.75l1.125-15M8.25 8.25v.008m3.75-.008v.008m3.75-.008v.008M8.25 12h7.5"/>
                </svg>
                <span>Shop</span>
            </a>

            <button type="button"
                    @click="mobileOpen = false; searchOpen = true; $nextTick(() => document.getElementById('mobile-search-input')?.focus())"
                    :class="searchOpen ? 'text-primary' : 'text-text-muted'"
                    class="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold transition-colors"
                    aria-label="Search catalog">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z"/>
                </svg>
                <span>Search</span>
            </button>

            @if($orderingEnabled)
            <button type="button"
                    @click="mobileOpen = false; searchOpen = false; cartOpen = true"
                    :class="cartOpen ? 'text-primary' : 'text-text-muted'"
                    class="relative flex flex-col items-center justify-center gap-1 text-[10px] font-semibold transition-colors"
                    aria-label="Open cart">
                <span class="relative">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4ZM3 6h18M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <span data-cart-badge class="{{ cart_count() > 0 ? '' : 'hidden' }} absolute -right-2.5 -top-2 min-w-4 h-4 px-1 rounded-full bg-primary text-ink text-[9px] font-bold flex items-center justify-center">{{ cart_count() }}</span>
                </span>
                <span>Cart</span>
            </button>
            @endif

            <button type="button"
                    @click="searchOpen = false; mobileOpen = !mobileOpen"
                    :class="mobileOpen ? 'text-primary' : 'text-text-muted'"
                    :aria-expanded="mobileOpen"
                    class="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold transition-colors"
                    aria-label="Toggle catalog">
                <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5"/>
                </svg>
                <svg x-show="mobileOpen" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
                <span>Catalog</span>
            </button>
        </div>
    </nav>

    {{-- Main Content --}}
    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="bg-ink text-bg mt-20">
        <div class="max-w-7xl mx-auto px-6 md:px-12 py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-bg">
                            <img src="{{ store_media_url('site_logo', asset('images/placeholder.svg')) }}" alt="our store" class="w-full h-full object-cover">
                        </div>
                        <span class="font-fraunces font-bold text-xl text-bg">{{ setting('site_name', config('site.name', 'Store')) }}</span>
                    </div>
                    <p class="text-bg/70 text-sm leading-relaxed mb-6">{{ setting('site_tagline', '') }}</p>
                    @php
                        $facebookUrl = trim((string) setting('facebook_url'));
                        $instagramUrl = trim((string) setting('instagram_url'));
                        $tiktokUrl = trim((string) setting('tiktok_url'));
                        $whatsappNumber = trim((string) setting('site_whatsapp'));
                        $whatsappDigits = preg_replace('/[^0-9]/', '', $whatsappNumber);
                    @endphp
                    @if($facebookUrl || $instagramUrl || $tiktokUrl || $whatsappDigits)
                    <div class="flex gap-3">
                        @if($facebookUrl)
                        <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" aria-label="Facebook" class="w-9 h-9 rounded-full bg-bg/10 hover:bg-primary flex items-center justify-center transition-colors duration-200 text-bg">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.03H7.9v-2.9h2.4V9.41c0-2.37 1.41-3.68 3.56-3.68 1.03 0 2.11.18 2.11.18v2.32h-1.19c-1.17 0-1.54.73-1.54 1.48v1.78h2.62l-.42 2.9h-2.2V22c4.78-.75 8.44-4.91 8.44-9.93z"/></svg>
                        </a>
                        @endif
                        @if($instagramUrl)
                        <a href="{{ $instagramUrl }}" target="_blank" rel="noopener" aria-label="Instagram" class="w-9 h-9 rounded-full bg-bg/10 hover:bg-primary flex items-center justify-center transition-colors duration-200 text-bg">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zm0 1.8c-3.15 0-3.52.01-4.76.07-2.25.1-3.29 1.14-3.4 3.4-.05 1.24-.07 1.61-.07 4.76s.02 3.52.07 4.76c.1 2.25 1.15 3.29 3.4 3.4 1.24.05 1.61.07 4.76.07s3.52-.02 4.76-.07c2.25-.1 3.29-1.15 3.4-3.4.05-1.24.07-1.61.07-4.76s-.02-3.52-.07-4.76c-.1-2.25-1.15-3.29-3.4-3.4-1.24-.05-1.61-.07-4.76-.07zm0 3.06a5.18 5.18 0 1 1 0 10.36 5.18 5.18 0 0 1 0-10.36zm0 8.55a3.37 3.37 0 1 0 0-6.74 3.37 3.37 0 0 0 0 6.74zm6.41-8.79a1.21 1.21 0 1 1-2.42 0 1.21 1.21 0 0 1 2.42 0z"/></svg>
                        </a>
                        @endif
                        @if($tiktokUrl)
                        <a href="{{ $tiktokUrl }}" target="_blank" rel="noopener" aria-label="TikTok" class="w-9 h-9 rounded-full bg-bg/10 hover:bg-primary flex items-center justify-center transition-colors duration-200 text-bg">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 0 0-.79-.05A6.34 6.34 0 0 0 3.15 15.3a6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.34-6.34V8.73a8.18 8.18 0 0 0 4.76 1.52V6.84a4.85 4.85 0 0 1-1-.15z"/></svg>
                        </a>
                        @endif
                        @if($whatsappDigits)
                        <a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener" aria-label="WhatsApp" class="w-9 h-9 rounded-full bg-bg/10 hover:bg-secondary flex items-center justify-center transition-colors duration-200 text-bg">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
                <div>
                    <h4 class="font-fraunces font-semibold text-bg mb-5">Catalog</h4>
                    <ul class="space-y-2.5">
                        <li><a href="{{ route('shop') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">Full Catalog</a></li>
                        @foreach($navCategories as $cat)
                            <li><a href="{{ route('category.show', $cat->slug) }}" class="text-sm text-bg/70 hover:text-bg transition-colors">{{ $cat->name }}</a></li>
                        @endforeach
                        <li><a href="{{ route('gallery') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">Gallery</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-fraunces font-semibold text-bg mb-5">Account</h4>
                    <ul class="space-y-2.5">
                        @auth
                            <li><a href="{{ route('account.dashboard') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">My Account</a></li>
                            <li><a href="{{ route('orders.index') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">My Orders</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">Login</a></li>
                            <li><a href="{{ route('register') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">Register</a></li>
                        @endauth
                        <li><a href="{{ route('wishlist.index') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">Wishlist</a></li>
                        @if($orderingEnabled)
                        <li><a href="{{ route('cart.index') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">Cart</a></li>
                        @endif
                        <li><a href="{{ route('faq') }}" class="text-sm text-bg/70 hover:text-bg transition-colors">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-fraunces font-semibold text-bg mb-5">Contact</h4>
                    @php
                        $siteAddress = trim((string) setting('site_address'));
                        $sitePhone = trim((string) setting('site_phone'));
                        $siteEmail = trim((string) setting('site_email'));
                    @endphp
                    <ul class="space-y-3 text-sm text-bg/70">
                        @if($siteAddress)
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-primary mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $siteAddress }}
                        </li>
                        @endif
                        @if($sitePhone)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ $sitePhone }}
                        </li>
                        @endif
                        @if($siteEmail)
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            {{ $siteEmail }}
                        </li>
                        @endif
                    </ul>
                    <ul class="mt-5 space-y-2">
                        <li><a href="{{ route('privacy') }}" class="text-xs text-bg/50 hover:text-bg transition-colors">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" class="text-xs text-bg/50 hover:text-bg transition-colors">Terms & Conditions</a></li>
                        <li><a href="{{ route('returns') }}" class="text-xs text-bg/50 hover:text-bg transition-colors">Refund Policy</a></li>
                        <li><a href="{{ route('shipping') }}" class="text-xs text-bg/50 hover:text-bg transition-colors">Shipping Policy</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="border-t border-bg/10">
            <div class="max-w-7xl mx-auto px-6 md:px-12 py-5 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-bg/50">
                <span>© {{ date('Y') }} {{ setting('site_name', config('site.name', 'Store')) }}. All rights reserved.</span>
                
            </div>
        </div>
    </footer>

    @unless($orderingManuallyEnabled)
    <div x-show="orderingPausedNotice" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-6" role="dialog" aria-modal="true" aria-label="Ordering paused">
        <section class="bg-white rounded-xl p-6 max-w-md"><h2 class="text-xl font-bold">Ordering is paused</h2><p class="mt-3">You can still browse our catalog. Please contact us for assistance.</p><button class="btn-primary mt-4" @click="orderingPausedNotice = false">Continue browsing</button></section>
    </div>
    @endunless

    @if($orderingEnabled)
    {{-- Cart Drawer --}}
    <div x-show="cartOpen" x-cloak @keydown.escape.window="cartOpen = false"
         class="fixed inset-0 w-screen h-dvh z-[70] flex justify-end"
         x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 w-screen h-dvh bg-espresso-800/40 z-0" @click="cartOpen = false"></div>
        <div class="relative z-10 w-full max-w-md bg-white h-full flex flex-col shadow-luxury-lg animate-slide-in" style="background-color: #ffffff !important;" x-data="{ editingItemId: null }">
            <div class="flex items-center justify-between px-6 py-5 border-b border-cream-100">
                <h2 class="font-heading text-xl font-semibold text-espresso-800">Your Cart</h2>
                <button @click="cartOpen = false" class="btn-ghost p-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4" id="cart-items-container">

                @forelse($cartItems as $item)
                <div class="border-b border-cream-100 last:border-0 py-3">
                    <div class="flex gap-3">
                        <img src="{{ $item->product?->main_image_url ?? asset('images/placeholder.svg') }}" alt="{{ $item->product?->title }}"
                             class="w-16 h-16 rounded-xl object-cover shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-espresso-800 truncate">{{ $item->product?->title ?? 'Product' }}</p>
                            @include('components.item-configuration', ['item' => $item, 'class' => 'mt-0.5 space-y-0.5', 'labelClass' => 'text-[11px] text-espresso-500', 'optionClass' => 'text-[11px] text-espresso-500'])
                            <p class="text-xs text-espresso-500 mt-0.5">{{ format_price($item->price) }} each</p>
                            <p class="text-sm font-semibold text-primary-600 mt-1">{{ format_price($item->line_total) }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <span class="text-xs bg-cream-100 text-espresso-600 px-2 py-0.5 rounded-full">x{{ $item->quantity }}</span>
                            <div class="flex items-center gap-2">
                                <!-- Edit Item Button -->
                                <button @click="editingItemId === {{ $item->id }} ? editingItemId = null : editingItemId = {{ $item->id }}" 
                                        class="text-espresso-400 hover:text-primary-600 transition-colors p-1" title="Edit Item">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <!-- Remove Item Button -->
                                <a href="{{ route('cart.remove', $item->id) }}"
                                   onclick="event.preventDefault(); document.getElementById('remove-form-{{ $item->id }}').submit();"
                                   class="text-red-400 hover:text-red-600 transition-colors p-1" title="Remove Item">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </a>
                            </div>
                            <form id="remove-form-{{ $item->id }}" action="{{ route('cart.remove', $item->id) }}" method="POST" class="hidden">
                                @csrf @method('DELETE')
                            </form>
                        </div>
                    </div>

                    @php
                        $editSelectedOptionIds = collect($item->options_snapshot ?? [])->pluck('value_id')->values()->all();
                        $editOptionGroups = ($item->product?->optionGroups ?? collect())
                            ->where('is_active', true)
                            ->values()
                            ->map(fn ($g) => [
                                'id' => $g->id,
                                'name' => $g->name,
                                'selection_type' => $g->selection_type,
                                'min_selections' => $g->effectiveMinSelections(),
                                'max_selections' => $g->effectiveMaxSelections(),
                                'values' => $g->values->where('is_active', true)->values()->map(fn ($v) => [
                                    'id' => $v->id,
                                    'name' => $v->name,
                                    'price_adjustment' => (float) $v->price_adjustment,
                                ])->values()->all(),
                            ])->values()->all();
                    @endphp
                    <!-- Expandable Edit Panel -->
                    <div x-show="editingItemId === {{ $item->id }}" x-cloak x-transition
                         class="mt-3 p-3 bg-cream-50 rounded-xl border border-cream-100 space-y-3"
                         x-data="cartItemEditor({
                            selectedVariantId: {{ (int) ($item->product_variant_id ?? 0) }} || null,
                            selectedOptionIds: {{ json_encode($editSelectedOptionIds) }},
                            optionGroups: {{ json_encode($editOptionGroups) }}
                         })">
                         
                         @if($item->product && $item->product->variants->where('is_active', true)->count() > 0)
                         <div>
                             <label class="block text-[10px] font-bold text-espresso-500 uppercase tracking-wider mb-1">Select Variant:</label>
                             <select x-model.number="selectedVariantId" class="w-full text-xs bg-white border border-cream-200 rounded-lg p-2 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                 @foreach($item->product->variants->where('is_active', true) as $variant)
                                     <option value="{{ $variant->id }}">
                                         {{ $variant->display_name }} - {{ format_price($variant->effective_price) }}
                                     </option>
                                 @endforeach
                             </select>
                         </div>
                         @endif

                         <template x-for="group in optionGroups" :key="group.id">
                             <div>
                                 <label class="block text-[10px] font-bold text-espresso-500 uppercase tracking-wider mb-1" x-text="group.name"></label>
                                 <div class="flex flex-wrap gap-1.5">
                                     <template x-for="value in group.values" :key="value.id">
                                         <button type="button"
                                                 @click="toggleOption(group, value.id)"
                                                 :class="isSelected(value.id) ? 'border-primary-500 bg-primary-50 text-primary-800' : 'border-cream-200 bg-white text-espresso-700'"
                                                 class="px-2 py-1 text-[11px] border rounded-lg"
                                                 x-text="value.name + (value.price_adjustment ? (' (' + (value.price_adjustment > 0 ? '+' : '') + value.price_adjustment + ')') : '')">
                                         </button>
                                     </template>
                                 </div>
                             </div>
                         </template>

                         <div class="flex items-center justify-between gap-2 pt-1">
                             <div>
                                 <label class="block text-[10px] font-bold text-espresso-500 uppercase tracking-wider mb-1">Quantity:</label>
                                 <input type="number" id="edit-qty-{{ $item->id }}" min="1" max="20" value="{{ $item->quantity }}"
                                        class="w-16 text-xs bg-white border border-cream-200 rounded-lg p-1.5 focus:outline-none">
                             </div>
                             
                             <div class="flex gap-2">
                                 <button @click="editingItemId = null" class="px-3 py-1.5 text-xs font-semibold text-espresso-600 hover:bg-cream-100 rounded-lg transition-colors">Cancel</button>
                                 <button @click="
                                         let qInput = document.getElementById('edit-qty-{{ $item->id }}');
                                         let payload = {
                                             product_variant_id: selectedVariantId,
                                             option_value_ids: selectedOptionIds,
                                             quantity: qInput ? qInput.value : {{ $item->quantity }}
                                         };
                                         
                                         fetch('{{ route('cart.update', $item->id) }}', {
                                             method: 'PATCH',
                                             headers: {
                                                 'Content-Type': 'application/json',
                                                 'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                 'Accept': 'application/json'
                                             },
                                             body: JSON.stringify(payload)
                                         })
                                         .then(res => res.json())
                                         .then(data => {
                                             if (data.success) {
                                                 window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Cart item updated!', type: 'success' } }));
                                                 editingItemId = null;
                                                 window.location.reload();
                                             } else {
                                                 window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Error updating item', type: 'error' } }));
                                             }
                                         });
                                     " 
                                     class="px-3.5 py-1.5 bg-primary-500 text-white text-xs font-bold rounded-lg hover:bg-primary-600 transition-colors">Save</button>
                             </div>
                         </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-12">
                    <div class="text-5xl mb-4">🛒</div>
                    <p class="text-espresso-500 mb-4">Your cart is empty</p>
                    <a href="{{ route('shop') }}" @click="cartOpen = false" class="btn-primary">Start Shopping</a>
                </div>
                @endforelse
            </div>
            <div id="cart-totals-container">
                @if(cart_count() > 0)
                @php $totals = $drawerTotals; @endphp
                <div class="border-t border-cream-100 px-6 pt-5 pb-[calc(2.5rem+env(safe-area-inset-bottom))] sm:pb-5 space-y-3">
                    <div class="flex justify-between text-sm text-espresso-600">
                        <span>Subtotal</span><span class="font-medium">{{ format_price($totals['subtotal']) }}</span>
                    </div>
                    @if($totals['discount'] > 0)
                    <div class="flex justify-between text-sm text-green-600">
                        <span>Discount</span><span>-{{ format_price($totals['discount']) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm text-espresso-600">
                        <span>Delivery</span>
                        <span class="font-medium">
                            @if(is_null($totals['delivery_fee']))
                                Depends on Location
                            @elseif($totals['delivery_fee'] > 0)
                                {{ format_price($totals['delivery_fee']) }}
                            @else
                                Free
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between font-semibold text-espresso-800 pt-2 border-t border-cream-100">
                        <span>Total</span><span>{{ format_price($totals['grand_total']) }}</span>
                    </div>
                    <a href="{{ route('cart.index') }}" class="btn-secondary w-full justify-center mt-3">View Cart</a>
                    <a href="{{ route('checkout.index') }}" class="btn-gold w-full justify-center">Checkout →</a>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <script>
        window.cartItemEditor = function (config) {
            return {
                selectedVariantId: config.selectedVariantId,
                selectedOptionIds: Array.isArray(config.selectedOptionIds) ? config.selectedOptionIds : [],
                optionGroups: Array.isArray(config.optionGroups) ? config.optionGroups : [],
                toggleOption(group, valueId) {
                    const groupValueIds = group.values.map(v => v.id);
                    if (group.selection_type === 'single') {
                        this.selectedOptionIds = this.selectedOptionIds.filter(id => !groupValueIds.includes(id));
                        this.selectedOptionIds.push(valueId);
                        return;
                    }
                    const idx = this.selectedOptionIds.indexOf(valueId);
                    if (idx >= 0) {
                        this.selectedOptionIds.splice(idx, 1);
                        return;
                    }
                    const currentInGroup = this.selectedOptionIds.filter(id => groupValueIds.includes(id));
                    if (!group.max_selections || currentInGroup.length < group.max_selections) {
                        this.selectedOptionIds.push(valueId);
                    }
                },
                isSelected(valueId) {
                    return this.selectedOptionIds.includes(valueId);
                }
            };
        };

        window.productCardConfig = function (config) {
            return {
                open: false,
                variants: Array.isArray(config.variants) ? config.variants : [],
                optionGroups: Array.isArray(config.optionGroups) ? config.optionGroups : [],
                selectedVariantId: config.selectedVariantId ?? null,
                selectedOptionIds: {},
                showSizePicker: !!config.showSizePicker,
                init() {
                    this.optionGroups.forEach(group => {
                        this.selectedOptionIds[group.id] = group.selection_type === 'single' ? null : [];
                    });
                },
                get selectedOptionIdList() {
                    return this.optionGroups.flatMap(group => {
                        const selected = this.selectedOptionIds[group.id];
                        if (group.selection_type === 'single') {
                            return selected ? [selected] : [];
                        }
                        return Array.isArray(selected) ? selected : [];
                    });
                },
                selectSingleOption(group, valueId) {
                    if (this.selectedOptionIds[group.id] === valueId && group.min_selections < 1) {
                        this.selectedOptionIds[group.id] = null;
                        return;
                    }
                    this.selectedOptionIds[group.id] = valueId;
                },
                toggleMultiOption(group, valueId) {
                    const current = Array.isArray(this.selectedOptionIds[group.id])
                        ? [...this.selectedOptionIds[group.id]]
                        : [];
                    const index = current.indexOf(valueId);
                    if (index >= 0) {
                        if (current.length <= group.min_selections) return;
                        current.splice(index, 1);
                    } else {
                        if (group.max_selections && current.length >= group.max_selections) return;
                        current.push(valueId);
                    }
                    this.selectedOptionIds[group.id] = current;
                },
                configurationValid() {
                    if (!this.selectedVariantId || this.variants.find(v => v.id === this.selectedVariantId)?.available_quantity === 0) return false;
                    return this.optionGroups.every(group => {
                        const selected = this.selectedOptionIds[group.id];
                        const count = group.selection_type === 'single'
                            ? (selected ? 1 : 0)
                            : (Array.isArray(selected) ? selected.length : 0);
                        if (count < group.min_selections) return false;
                        if (group.max_selections && count > group.max_selections) return false;
                        return true;
                    });
                },
                openPanel() {
                    this.open = true;
                    document.body.classList.add('hide-custom-cursor');
                },
                closePanel() {
                    this.open = false;
                    document.body.classList.remove('hide-custom-cursor');
                }
            };
        };

        (function syncSiteTopSpacer() {
            const top = document.getElementById('site-top');
            const spacer = document.getElementById('site-top-spacer');
            if (!top || !spacer) return;

            const apply = () => {
                spacer.style.height = Math.ceil(top.getBoundingClientRect().height) + 'px';
            };

            apply();
            window.addEventListener('resize', apply, { passive: true });

            if (typeof ResizeObserver !== 'undefined') {
                new ResizeObserver(apply).observe(top);
            } else {
                window.addEventListener('scroll', apply, { passive: true });
            }

            if (document.fonts?.ready) {
                document.fonts.ready.then(apply).catch(() => {});
            }
        })();
    </script>
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
