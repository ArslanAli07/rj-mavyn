<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ store_media_url('site_favicon', asset('assets/favicon-32x32.png')) }}">

        <title>{{ $title ?? 'Happy Tea — Premium Beverages' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-espresso-900 antialiased bg-cream-50 h-full">
        <div class="min-h-screen flex flex-col lg:flex-row">
            
            {{-- Left column: visual banner (desktop only) --}}
            <div class="hidden lg:flex lg:w-1/2 relative bg-espresso-950 items-center justify-center overflow-hidden">
                {{-- Background brand image --}}
                <div class="absolute inset-0 bg-cover bg-center opacity-70 mix-blend-luminosity hover:opacity-80 hover:scale-105 transition-all duration-10000"
                     style="background-image: url('{{ asset('assets/sliders/milk-tea.jpg') }}');">
                </div>
                {{-- Gradient luxury overlay --}}
                <div class="absolute inset-0 bg-gradient-to-tr from-espresso-950 via-espresso-900/60 to-espresso-850/40"></div>
                
                {{-- Branding overlay --}}
                <div class="relative z-10 max-w-md px-8 text-center text-cream-50 space-y-6">
                    <a href="/" class="inline-block group">
                        <span class="font-heading font-bold text-4xl text-white tracking-tight block">
                            Happy Tea
                        </span>
                        <span class="text-xs text-primary-200 tracking-widest uppercase font-light mt-1 block">Beverages · Sialkot</span>
                    </a>
                    
                    <div class="h-0.5 bg-gradient-to-r from-transparent via-primary-400 to-transparent w-32 mx-auto"></div>
                    
                    <p class="text-lg text-cream-200 font-light leading-relaxed italic">
                        "Crafted with love, served with soul"
                    </p>
                    
                </div>
            </div>
            
            {{-- Right column: auth form wrapper --}}
            <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-20 xl:px-24 bg-cream-50">
                <div class="mx-auto w-full max-w-sm sm:max-w-md">
                    {{-- Mini mobile logo --}}
                    <div class="text-center lg:hidden mb-8">
                        <a href="/" class="inline-flex items-center gap-2">
                            <img src="{{ store_media_url('site_logo', asset('assets/Logo_Happytea.svg')) }}" alt="Happy Tea Logo" class="h-10 w-auto">
                            <div class="flex flex-col leading-none text-left">
                                <span class="font-heading font-bold text-2xl text-espresso-850 tracking-tight">Happy Tea</span>
                                <span class="text-[10px] text-espresso-400 tracking-widest uppercase font-light mt-0.5 block">Beverages · Sialkot</span>
                            </div>
                        </a>
                    </div>
                    
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-10 shadow-luxury sm:shadow-luxury-lg">
                        {{ $slot }}
                    </div>
                </div>
            </div>

        </div>
    </body>
</html>
