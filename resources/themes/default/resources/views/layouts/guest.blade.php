<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-cream-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ store_media_url('site_favicon', asset('assets/favicon-32x32.png')) }}">

    <title>{{ $title ?? 'Welcome' }} — {{ setting('site_name', config('site.name', 'Store')) }}</title>


    @stack('head')
@foreach($theme['assets']['styles'] as $url)<link rel="stylesheet" href="{{ $url }}">@endforeach
    <style>[data-storefront-theme] { --color-primary: {{ $theme['settings']['accent_color'] }}; }</style>
    @foreach($theme['assets']['scripts'] as $url)<script type="module" src="{{ $url }}"></script>@endforeach
</head>
<body data-storefront-theme="{{ $theme['id'] }}" class="h-full font-sans antialiased text-espresso-900 flex items-center justify-center p-4 bg-gradient-luxury relative overflow-hidden">

    {{-- Luxury Decorative Elements --}}
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-primary-100 rounded-full blur-3xl opacity-60"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-espresso-200 rounded-full blur-3xl opacity-30"></div>

    <div class="w-full max-w-md bg-white rounded-3xl shadow-luxury border border-cream-100 overflow-hidden relative z-10 animate-fade-up">
        
        {{-- Card Header --}}
        <div class="px-8 pt-8 pb-4 text-center shrink-0">
            <a href="{{ route('home') }}" class="inline-flex flex-col leading-none mb-4">
                <span class="font-heading font-bold text-2xl text-espresso-800 tracking-tight">{{ setting('site_name', config('site.name', 'Store')) }}</span>
                <span class="text-[10px] text-espresso-400 tracking-widest uppercase font-light mt-1">{{ setting('site_tagline', '') }}</span>
            </a>
            <div class="h-0.5 bg-gradient-to-r from-transparent via-primary-300 to-transparent w-24 mx-auto mb-2"></div>
        </div>

        {{-- Slot content --}}
        <div class="px-8 pb-8 pt-2">
            {{ $slot }}
        </div>
    </div>

</body>
</html>
