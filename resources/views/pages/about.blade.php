<x-app-layout title="Our Story — Happy Tea" page-key="about">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['About Us' => '']" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        {{-- Hero Headline --}}
        <div class="text-center max-w-2xl mx-auto mb-16 animate-fade-up">
            <span class="text-primary-600 font-heading text-lg italic block mb-2">Our Journey & Philosophy</span>
            <h1 class="text-4xl sm:text-5xl font-heading font-bold text-espresso-800 leading-[1.15]">Crafted with love, served with soul in Sialkot</h1>
            <div class="gold-line w-20 mx-auto mt-4 mb-6"></div>
            <p class="text-base text-espresso-500 font-light leading-relaxed">
                At Happy Tea, every cup is an experience — from ethically sourced beans to handcrafted boba and fruit teas made fresh daily.
            </p>
        </div>

        {{-- Two Column Story --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-20">
            <div class="aspect-square sm:aspect-[4/3] lg:aspect-square rounded-3xl overflow-hidden shadow-luxury relative img-zoom">
                <img src="{{ asset('assets/happteaskt.jpeg') }}" alt="Happy Tea café" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-espresso-900/10"></div>
            </div>

            <div class="space-y-6">
                <h2 class="font-heading font-bold text-2xl sm:text-3xl text-espresso-800 leading-tight">How It All Started...</h2>
                <div class="h-0.5 bg-primary-200 w-16 mb-4"></div>
                <p class="text-sm text-espresso-700 leading-relaxed font-light">
                    Founded in Sialkot with a simple dream: to bring a premium beverage experience to the city. We saw a need for thoughtfully crafted coffee, fruit teas, milk teas, and ice cream — made fresh, never from shortcuts.
                </p>
                <p class="text-sm text-espresso-700 leading-relaxed font-light">
                    What started as a small corner café grew into a community favorite. Our secret? Zero compromises. We use quality tea leaves, freshly roasted beans, real fruit, and local dairy. No artificial preservatives, no dull drinks.
                </p>

            </div>
        </div>

        {{-- Mission & Vision Banner --}}
        <div class="bg-espresso-800 text-cream-100 rounded-3xl p-8 sm:p-12 mb-20 relative overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-10 mix-blend-overlay" style="background-image: url('{{ asset('assets/sliders/milk-tea.jpg') }}')"></div>
            <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-10">
                <div class="space-y-3">
                    <span class="text-primary-400 font-heading text-lg italic block">Our Mission</span>
                    <h3 class="text-xl sm:text-2xl font-heading font-bold text-white leading-tight">To Make Every Sip Memorable</h3>
                    <p class="text-xs sm:text-sm text-cream-200 leading-relaxed font-light">
                        We craft drinks that turn study sessions, date nights, and casual hangouts into moments worth savoring — from the first pour to the last sip.
                    </p>
                </div>
                <div class="space-y-3">
                    <span class="text-primary-400 font-heading text-lg italic block">Our Vision</span>
                    <h3 class="text-xl sm:text-2xl font-heading font-bold text-white leading-tight">Sialkot's Favorite Sip Spot</h3>
                    <p class="text-xs sm:text-sm text-cream-200 leading-relaxed font-light">
                        To continually raise the bar for coffee, tea, and frozen treats in Sialkot through better ingredients, warmer service, and drinks that feel special every time.
                    </p>
                </div>
            </div>
        </div>

        {{-- Call To Action --}}
        <div class="text-center max-w-lg mx-auto">
            <h3 class="font-heading font-bold text-2xl text-espresso-800 mb-4">Craving Something Fresh?</h3>
            <p class="text-sm text-espresso-500 mb-6 font-light">Explore our menu of fruit teas, milk teas, ice cream, and coffee — order online today.</p>
            <a href="{{ route('shop') }}" class="btn-gold px-8 py-3.5 text-sm">Browse the Menu</a>
        </div>

    </div>

</x-app-layout>
