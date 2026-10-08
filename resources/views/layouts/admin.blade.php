<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-cream-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ store_media_url('site_favicon', asset('assets/favicon-32x32.png')) }}">

    <title>{{ $title ?? 'Admin Dashboard' }} — {{ setting('site_name', 'Store') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @stack('head')
</head>
<body class="h-full font-sans antialiased text-espresso-900" x-data="{ sidebarOpen: false }">

    {{-- Mobile Sidebar Backdrop --}}
    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-50 bg-espresso-800/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar Container --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-cream-200 flex flex-col transition-transform duration-300 ease-in-out">
        
        {{-- Sidebar Brand --}}
        <div class="px-6 py-4 border-b border-cream-100 flex items-center justify-between shrink-0">
            <a href="{{ route('admin.workspace') }}" class="flex items-center gap-2">
                @if($logoUrl = store_media_url('site_logo', ''))
                    <img src="{{ $logoUrl }}" alt="Store logo" class="h-8 w-auto">
                @endif
                <div class="flex flex-col leading-none">
                    <span class="font-heading font-bold text-base text-espresso-800">{{ setting('site_name', 'Store') }} <span class="text-primary-500">Admin</span></span>
                    <span class="text-[8px] text-espresso-400 tracking-widest uppercase font-light">Store Management</span>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden p-1 text-espresso-500 hover:bg-cream-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Sidebar Navigation --}}
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.dashboard'))
<a href="{{ route('admin.dashboard') }}" class="admin-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Dashboard
            </a>
@endif

            <div class="pt-4 pb-2 px-4 text-xs font-semibold text-espresso-400 uppercase tracking-wider">Catalog</div>
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.products.index'))
<a href="{{ route('admin.products.index') }}" class="admin-nav-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Products
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.categories.index'))
<a href="{{ route('admin.categories.index') }}" class="admin-nav-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Categories
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.occasions.index'))
<a href="{{ route('admin.occasions.index') }}" class="admin-nav-item {{ request()->routeIs('admin.occasions.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Occasions
            </a>
@endif

            <div class="pt-4 pb-2 px-4 text-xs font-semibold text-espresso-400 uppercase tracking-wider">Sales</div>
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.orders.index'))
<a href="{{ route('admin.orders.index') }}" class="admin-nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span class="flex-1">Orders</span>
                <span id="admin-pending-orders-count"
                      class="ml-auto min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-bold leading-5 text-center {{ request()->routeIs('admin.orders.*') ? 'bg-white/20 text-white' : 'bg-primary-500 text-white' }} {{ ($pendingOrdersCount ?? 0) > 0 ? '' : 'hidden' }}">
                    {{ $pendingOrdersCount ?? 0 }}
                </span>
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.customers.index'))
<a href="{{ route('admin.customers.index') }}" class="admin-nav-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Customers
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.coupons.index'))
<a href="{{ route('admin.coupons.index') }}" class="admin-nav-item {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                Coupons
            </a>
@endif

            <div class="pt-4 pb-2 px-4 text-xs font-semibold text-espresso-400 uppercase tracking-wider">Content</div>
            @if(auth('admin')->user()?->hasPermission('media.view'))
                @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.media.index'))
<a href="{{ route('admin.media.index') }}" class="admin-nav-item {{ request()->routeIs('admin.media.*') ? 'active' : '' }}">Media Library</a>
@endif
            @endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.home-slides.index'))
<a href="{{ route('admin.home-slides.index') }}" class="admin-nav-item {{ request()->routeIs('admin.home-slides.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Hero Slides
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.testimonials.index'))
<a href="{{ route('admin.testimonials.index') }}" class="admin-nav-item {{ request()->routeIs('admin.testimonials.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Testimonials
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.faqs.index'))
<a href="{{ route('admin.faqs.index') }}" class="admin-nav-item {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                FAQs
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.contact-messages.index'))
<a href="{{ route('admin.contact-messages.index') }}" class="admin-nav-item {{ request()->routeIs('admin.contact-messages.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Messages
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.newsletter.index'))
<a href="{{ route('admin.newsletter.index') }}" class="admin-nav-item {{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l8-5.333a2 2 0 012.22 0l8 5.333A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-2.25-1.5a2 2 0 00-2.22 0l-2.25 1.5"/></svg>
                Newsletter Subscribers
            </a>
@endif

            <div class="pt-4 pb-2 px-4 text-xs font-semibold text-espresso-400 uppercase tracking-wider">Configuration</div>
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.delivery-zones.index') && plugin_active('mavyn.local-delivery'))
<a href="{{ route('admin.delivery-zones.index') }}" class="admin-nav-item {{ request()->routeIs('admin.delivery-zones.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Delivery Zones
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.seo.index'))
<a href="{{ route('admin.seo.index') }}" class="admin-nav-item {{ request()->routeIs('admin.seo.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                SEO Settings
            </a>
@endif
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.settings.index'))
<a href="{{ route('admin.settings.index') }}" class="admin-nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                General Settings
            </a>
@endif
        @if(auth('admin')->user()->hasPermission('extensions.view'))<a class="admin-nav-item" href="{{ route('admin.extensions.index') }}">Plugins</a>@endif
@if(auth('admin')->user()->hasPermission('themes.view'))<a class="admin-nav-item" href="{{ route('admin.themes.index') }}">Themes</a>@endif
        @if(auth('admin')->user()->hasPermission('staff.manage'))<a class="admin-nav-item" href="{{ route('admin.staff.index') }}">Staff access</a>@endif
@if(auth('admin')->user()->hasPermission('audit.view'))<a class="admin-nav-item" href="{{ route('admin.audit.index') }}">Audit history</a>@endif
</nav>

        {{-- Sidebar Footer --}}
        <div class="p-4 border-t border-cream-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center font-bold text-primary-700">
                    {{ substr(auth('admin')->user()->name ?? 'A', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-espresso-850 truncate">{{ auth('admin')->user()->name ?? 'Administrator' }}</p>
                    <p class="text-[10px] text-espresso-400 truncate">{{ auth('admin')->user()->email ?? '' }}</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg" title="Log Out">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main Admin Panel Area --}}
    <div class="lg:pl-64 flex flex-col min-h-screen">
        
        {{-- Top Header --}}
        <header class="bg-white border-b border-cream-200 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = true" class="lg:hidden p-2 text-espresso-500 hover:bg-cream-100 rounded-xl">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="hidden sm:flex items-center gap-1.5 text-xs text-espresso-400">
                    <span>Admin Panel</span>
                    <span>/</span>
                    <span class="text-espresso-700 font-medium">{{ $title ?? 'Dashboard' }}</span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <a href="{{ route('home') }}" target="_blank" class="btn-ghost py-1.5 px-3 flex items-center gap-1.5 text-xs border border-cream-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View Website
                </a>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
            
            {{-- Flush Message --}}
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl flex items-center gap-3 text-sm">
                    <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            <div id="admin-notification-permission-banner" class="hidden mb-6 p-4 bg-primary-50 border border-primary-200 text-espresso-800 rounded-2xl flex flex-col sm:flex-row sm:items-center gap-3 text-sm">
                <div class="flex-1">
                    <p class="font-semibold text-espresso-850">Enable alerts for new orders</p>
                    <p class="text-xs text-espresso-500 mt-0.5">Tap Allow so this device can play a sound and show a notification when a customer orders. Keep this tab open, and turn the ringer / volume up.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="admin-enable-notifications" class="btn-gold py-1.5 px-4 text-xs font-semibold">Allow</button>
                    <button type="button" id="admin-dismiss-notifications" class="btn-ghost py-1.5 px-3 text-xs">Not now</button>
                </div>
            </div>
            <div id="admin-order-sound-banner" class="hidden mb-6 p-4 bg-primary-50 border border-primary-200 text-espresso-800 rounded-2xl flex flex-col sm:flex-row sm:items-center gap-3 text-sm">
                <div class="flex-1">
                    <p class="font-semibold text-espresso-850">Tap to enable order sounds</p>
                    <p class="text-xs text-espresso-500 mt-0.5">Phones and tablets block sound until you interact with this page. Tap Enable, turn the ringer on, and keep this tab open.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="admin-enable-order-sound" class="btn-gold py-1.5 px-4 text-xs font-semibold">Enable</button>
                    <button type="button" id="admin-dismiss-order-sound" class="btn-ghost py-1.5 px-3 text-xs">Not now</button>
                </div>
            </div>
            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-center gap-3 text-sm">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    @stack('scripts')

    {{-- Admin table bulk actions: select-all, inject ids, confirm deletes --}}
    <script>
        (function () {
            function rootOf(el) {
                return el.closest('[data-bulk-root]');
            }

            function sync(root) {
                if (!root) return;
                const boxes = root.querySelectorAll('[data-bulk-row]');
                const selected = root.querySelectorAll('[data-bulk-row]:checked');
                const toolbar = root.querySelector('[data-bulk-toolbar]');
                const count = root.querySelector('[data-bulk-count]');
                const selectAll = root.querySelector('[data-bulk-select-all]');
                const n = selected.length;

                if (count) count.textContent = n + ' selected';
                if (toolbar) toolbar.classList.toggle('hidden', n === 0);
                if (selectAll) {
                    selectAll.checked = boxes.length > 0 && n === boxes.length;
                    selectAll.indeterminate = n > 0 && n < boxes.length;
                }
                boxes.forEach(function (cb) {
                    const row = cb.closest('tr');
                    if (row) row.classList.toggle('admin-bulk-selected', cb.checked);
                });
            }

            document.addEventListener('change', function (e) {
                const target = e.target;
                if (!(target instanceof HTMLInputElement)) return;

                if (target.matches('[data-bulk-select-all]')) {
                    const root = rootOf(target);
                    if (!root) return;
                    root.querySelectorAll('[data-bulk-row]').forEach(function (cb) {
                        cb.checked = target.checked;
                    });
                    sync(root);
                    return;
                }

                if (target.matches('[data-bulk-row]')) {
                    sync(rootOf(target));
                }
            });

            document.addEventListener('submit', function (e) {
                const form = e.target.closest('[data-bulk-form]');
                if (!form) return;

                const root = rootOf(form);
                if (!root) return;

                const selected = Array.from(root.querySelectorAll('[data-bulk-row]:checked'));
                if (selected.length === 0) {
                    e.preventDefault();
                    return;
                }

                form.querySelectorAll('input[name="ids[]"]').forEach(function (el) { el.remove(); });
                selected.forEach(function (cb) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = cb.value;
                    form.appendChild(input);
                });

                const submitter = e.submitter;
                if (submitter && submitter.getAttribute('data-bulk-confirm')) {
                    const message = submitter.getAttribute('data-bulk-confirm').replace(':count', String(selected.length));
                    if (!window.confirm(message)) {
                        e.preventDefault();
                        return;
                    }
                }

                if (submitter && submitter.getAttribute('data-bulk-requires')) {
                    const field = form.querySelector('[name="' + submitter.getAttribute('data-bulk-requires') + '"]');
                    if (!field || !String(field.value || '').trim()) {
                        e.preventDefault();
                        if (field && typeof field.focus === 'function') field.focus();
                    }
                }
            });
        })();
    </script>

    @if(auth('admin')->user()->hasPermission('orders.view'))
    {{-- New-order poller: toast + sound + desktop notification --}}
    <audio id="admin-order-sound" preload="auto" playsinline webkit-playsinline>
        <source src="{{ asset('order-notification-sound.mp3') }}" type="audio/mpeg">
    </audio>
    <div id="admin-new-order-toast" class="fixed bottom-0 inset-x-0 lg:left-64 z-[100] hidden" role="status" aria-live="polite"></div>
    <script>
        (function () {
            const pollUrl = @json(route('admin.orders.poll'));
            const notificationIcon = @json(store_media_url('site_favicon', asset('assets/favicon-32x32.png')));
            const soundUrl = @json(asset('order-notification-sound.mp3'));
            const askPermissionOnLoad = @json((bool) session('ask_order_notifications'));
            const POLL_MS = 2000;
            let lastId = null;
            let inFlight = false;
            let timer = null;
            let abortController = null;
            let lastPendingCount = null;
            let stopped = false;
            let audioUnlocked = false;
            let unlockPromise = null;
            let pendingSound = false;
            let soundBannerDismissed = false;
            let audioCtx = null;
            let audioBuffer = null;
            let rawSoundBytes = null;
            const soundEl = document.getElementById('admin-order-sound');
            const gestureEvents = ['pointerdown', 'touchstart', 'click', 'keydown'];

            if (soundEl) {
                soundEl.playsInline = true;
                try { soundEl.load(); } catch (e) { /* ignore */ }
            }

            fetch(soundUrl, { cache: 'force-cache' })
                .then(function (res) { return res.ok ? res.arrayBuffer() : Promise.reject(); })
                .then(function (bytes) { rawSoundBytes = bytes; })
                .catch(function () { /* HTML audio element still preloads as fallback */ });

            function notificationsSupported() {
                return typeof window.Notification === 'function';
            }

            function hidePermissionBanner() {
                const banner = document.getElementById('admin-notification-permission-banner');
                if (banner) banner.classList.add('hidden');
            }

            function showPermissionBanner() {
                const banner = document.getElementById('admin-notification-permission-banner');
                if (banner) banner.classList.remove('hidden');
            }

            function hideSoundBanner() {
                const banner = document.getElementById('admin-order-sound-banner');
                if (banner) banner.classList.add('hidden');
            }

            function showSoundBanner() {
                if (soundBannerDismissed || audioUnlocked) return;
                const banner = document.getElementById('admin-order-sound-banner');
                if (banner) banner.classList.remove('hidden');
            }

            function getAudioContext(create) {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return null;
                if (!audioCtx && create) {
                    try { audioCtx = new AC(); } catch (e) { return null; }
                }
                return audioCtx;
            }

            function resumeAudioContext() {
                const ctx = getAudioContext(true);
                if (!ctx) return Promise.resolve(null);
                if (ctx.state !== 'suspended') return Promise.resolve(ctx);
                return ctx.resume().then(function () { return ctx; }).catch(function () { return ctx; });
            }

            function decodeSoundBuffer(ctx) {
                if (!ctx || audioBuffer) return Promise.resolve(audioBuffer);
                const source = rawSoundBytes || null;
                const start = source
                    ? Promise.resolve(source)
                    : fetch(soundUrl, { cache: 'force-cache' }).then(function (res) {
                        return res.ok ? res.arrayBuffer() : Promise.reject();
                    });
                return start
                    .then(function (raw) {
                        rawSoundBytes = raw;
                        return ctx.decodeAudioData(raw.slice(0));
                    })
                    .then(function (decoded) { audioBuffer = decoded; return decoded; })
                    .catch(function () { return null; });
            }

            function unlockHtmlAudio() {
                if (!soundEl) return Promise.resolve(false);
                soundEl.muted = true;
                soundEl.volume = 0;
                try { soundEl.currentTime = 0; } catch (e) { /* ignore */ }
                const playResult = soundEl.play();
                const afterPlay = function () {
                    soundEl.pause();
                    try { soundEl.currentTime = 0; } catch (e) { /* ignore */ }
                    soundEl.muted = false;
                    soundEl.volume = 1;
                    return true;
                };
                if (!playResult || typeof playResult.then !== 'function') {
                    afterPlay();
                    return Promise.resolve(true);
                }
                return playResult.then(afterPlay).catch(function () {
                    soundEl.muted = false;
                    soundEl.volume = 1;
                    return false;
                });
            }

            function markUnlocked() {
                audioUnlocked = true;
                hideSoundBanner();
                if (pendingSound) {
                    pendingSound = false;
                    playOrderSound();
                }
            }

            function unlockAudio() {
                if (audioUnlocked) return Promise.resolve(true);
                if (unlockPromise) return unlockPromise;

                // play() and AudioContext.resume() must start inside the tap/click.
                const htmlReady = unlockHtmlAudio();
                const ctxReady = resumeAudioContext();

                unlockPromise = Promise.all([htmlReady, ctxReady])
                    .then(function (results) {
                        const htmlOk = results[0];
                        const ctx = results[1];
                        return decodeSoundBuffer(ctx).then(function () {
                            const ctxRunning = !!(ctx && ctx.state === 'running' && audioBuffer);
                            audioUnlocked = !!(htmlOk || ctxRunning);
                            unlockPromise = null;
                            if (audioUnlocked) markUnlocked();
                            return audioUnlocked;
                        });
                    })
                    .catch(function () {
                        unlockPromise = null;
                        return false;
                    });
                return unlockPromise;
            }

            function playViaWebAudio() {
                const ctx = getAudioContext(false);
                if (!ctx || !audioBuffer || ctx.state === 'suspended') return false;
                try {
                    const src = ctx.createBufferSource();
                    src.buffer = audioBuffer;
                    src.connect(ctx.destination);
                    src.start(0);
                    return true;
                } catch (e) {
                    return false;
                }
            }

            function playViaHtmlAudio() {
                if (!soundEl) return Promise.reject();
                soundEl.muted = false;
                soundEl.volume = 1;
                try { soundEl.pause(); } catch (e) { /* ignore */ }
                try { soundEl.currentTime = 0; } catch (e) { /* ignore */ }
                const playResult = soundEl.play();
                if (!playResult || typeof playResult.then !== 'function') return Promise.resolve();
                return playResult;
            }

            function playOrderSound() {
                const fail = function () {
                    pendingSound = true;
                    showSoundBanner();
                };

                if (playViaWebAudio()) {
                    audioUnlocked = true;
                    hideSoundBanner();
                    return;
                }

                playViaHtmlAudio().then(function () {
                    audioUnlocked = true;
                    hideSoundBanner();
                }).catch(fail);
            }

            function setupSoundUnlock() {
                const enableBtn = document.getElementById('admin-enable-order-sound');
                const dismissBtn = document.getElementById('admin-dismiss-order-sound');
                if (enableBtn) {
                    enableBtn.addEventListener('click', function () {
                        unlockAudio();
                    });
                }
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', function () {
                        soundBannerDismissed = true;
                        hideSoundBanner();
                    });
                }

                function onGesture() {
                    unlockAudio().then(function (ok) {
                        if (!ok) return;
                        gestureEvents.forEach(function (evt) {
                            document.removeEventListener(evt, onGesture, true);
                        });
                    });
                }
                gestureEvents.forEach(function (evt) {
                    document.addEventListener(evt, onGesture, true);
                });

                document.addEventListener('visibilitychange', function () {
                    if (document.hidden || !pendingSound) return;
                    pendingSound = false;
                    playOrderSound();
                });
            }

            function requestNotificationPermission() {
                if (!notificationsSupported() || Notification.permission !== 'default') {
                    return Promise.resolve(notificationsSupported() ? Notification.permission : 'denied');
                }
                return Notification.requestPermission();
            }

            function setupNotificationPermission() {
                const allowBtn = document.getElementById('admin-enable-notifications');
                const dismissBtn = document.getElementById('admin-dismiss-notifications');
                if (allowBtn) {
                    allowBtn.addEventListener('click', function () {
                        unlockAudio().then(hidePermissionBanner);
                        requestNotificationPermission();
                    });
                }
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', hidePermissionBanner);
                }

                if (!askPermissionOnLoad) return;

                showPermissionBanner();
                if (notificationsSupported() && Notification.permission === 'default') {
                    requestNotificationPermission().then(function (permission) {
                        if (permission !== 'default' && audioUnlocked) hidePermissionBanner();
                    });
                }
            }

            function showDesktopNotification(order, extraCount) {
                if (!notificationsSupported() || Notification.permission !== 'granted' || !order) return;

                const name = String(order.customer_name || 'Customer');
                const number = String(order.order_number || '');
                const total = Number(order.grand_total || 0);
                const url = String(order.url || '');
                const extra = extraCount > 1
                    ? ' (+' + (extraCount - 1) + ' more)'
                    : '';

                try {
                    const notification = new Notification('New order ' + number, {
                        body: name + ' · Rs ' + total.toLocaleString() + extra,
                        icon: notificationIcon,
                        tag: 'store-new-order',
                        renotify: true,
                    });
                    notification.onclick = function () {
                        window.focus();
                        if (url) window.location.href = url;
                        notification.close();
                    };
                } catch (e) {
                    // Ignore unsupported Notification options
                }
            }

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function (ch) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
                });
            }

            function updatePendingBadge(count) {
                const badge = document.getElementById('admin-pending-orders-count');
                if (!badge || typeof count !== 'number') return;
                if (lastPendingCount === count) return;
                lastPendingCount = count;
                badge.textContent = String(count);
                badge.classList.toggle('hidden', count <= 0);
            }

            function clearToast() {
                const el = document.getElementById('admin-new-order-toast');
                if (!el) return;
                clearTimeout(el._hideTimer);
                el._hideTimer = null;
                el.classList.add('hidden');
                el.innerHTML = '';
            }

            function showToast(order, extraCount) {
                const el = document.getElementById('admin-new-order-toast');
                if (!el || !order) return;

                const name = String(order.customer_name || '');
                const number = String(order.order_number || '');
                const total = Number(order.grand_total || 0);
                const url = String(order.url || '#');
                const extra = extraCount > 1
                    ? '<span class="text-cream-300">+' + (extraCount - 1) + ' more new order' + (extraCount > 2 ? 's' : '') + '</span>'
                    : '';

                el.innerHTML =
                    '<div class="w-full bg-espresso-800 text-white border-t border-espresso-700 shadow-lg">' +
                        '<div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 sm:px-6 py-3.5">' +
                            '<div class="w-10 h-10 rounded-full bg-primary-500/20 flex items-center justify-center shrink-0">' +
                                '<svg class="w-5 h-5 text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>' +
                            '</div>' +
                            '<div class="flex-1 min-w-0">' +
                                '<p class="text-xs uppercase tracking-wider text-primary-300 font-semibold">New order received</p>' +
                                '<p class="font-heading font-bold text-base truncate">' + escapeHtml(name) +
                                    ' <span class="font-sans font-medium text-sm text-cream-200">· ' + escapeHtml(number) + ' · Rs ' + total.toLocaleString() + '</span></p>' +
                                extra +
                            '</div>' +
                            '<div class="flex items-center gap-2 shrink-0">' +
                                '<a href="' + escapeHtml(url) + '" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 transition-colors">' +
                                    'View Order' +
                                    '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>' +
                                '</a>' +
                                '<button type="button" class="admin-new-order-dismiss p-2 text-cream-300 hover:text-white rounded-lg" aria-label="Dismiss">' +
                                    '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';

                el.classList.remove('hidden');
                clearTimeout(el._hideTimer);
                el._hideTimer = setTimeout(clearToast, 12000);

                const dismissBtn = el.querySelector('.admin-new-order-dismiss');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', clearToast);
                }
            }

            function scheduleNext() {
                if (stopped) return;
                clearTimeout(timer);
                timer = setTimeout(poll, POLL_MS);
            }

            function stopPolling() {
                stopped = true;
                clearTimeout(timer);
                timer = null;
                if (abortController) {
                    abortController.abort();
                    abortController = null;
                }
                inFlight = false;
            }

            function refreshOrdersTable() {
                const target = document.getElementById('admin-orders-live');
                if (!target) return;

                fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                }).then(function (res) {
                    if (!res.ok) return null;
                    return res.text();
                }).then(function (html) {
                    if (!html) return;
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const next = doc.getElementById('admin-orders-live');
                    const current = document.getElementById('admin-orders-live');
                    if (next && current) current.replaceWith(next);
                }).catch(function () {
                    // Keep the current table if the refresh fails
                });
            }

            async function poll() {
                if (stopped) return;
                if (inFlight) {
                    scheduleNext();
                    return;
                }

                inFlight = true;
                abortController = new AbortController();
                const timeoutId = setTimeout(function () {
                    if (abortController) abortController.abort();
                }, 8000);

                try {
                    const url = lastId === null ? pollUrl : (pollUrl + '?after_id=' + encodeURIComponent(lastId));
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache',
                        },
                        credentials: 'same-origin',
                        signal: abortController.signal,
                        cache: 'no-store',
                    });
                    if (res.status === 401 || res.status === 403) {
                        stopPolling();
                        return;
                    }
                    if (!res.ok) return;

                    const payload = await res.json();
                    const latestId = Number(payload.latest_id) || 0;
                    const pendingCount = Number(payload.pending_count) || 0;
                    const newCount = Number(payload.new_count) || 0;
                    const order = payload.order || null;

                    updatePendingBadge(pendingCount);

                    if (lastId === null) {
                        lastId = latestId;
                        return;
                    }

                    if (latestId > lastId) {
                        playOrderSound();
                        if (order) {
                            showToast(order, newCount);
                            showDesktopNotification(order, newCount);
                        }
                        lastId = latestId;
                        refreshOrdersTable();
                    }
                } catch (e) {
                    // Network / abort blips are fine; next tick retries
                } finally {
                    clearTimeout(timeoutId);
                    abortController = null;
                    inFlight = false;
                    if (!stopped) {
                        scheduleNext();
                    }
                }
            }

            window.addEventListener('pagehide', stopPolling);
            window.addEventListener('beforeunload', stopPolling);

            setupSoundUnlock();
            setupNotificationPermission();
            poll();
        })();
    </script>
    @endif
</body>
</html>
