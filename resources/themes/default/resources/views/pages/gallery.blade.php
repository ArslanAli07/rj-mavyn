<x-app-layout title="Gallery | {{ setting('site_name', config('site.name', 'Store')) }} — Workshop & Craft Photography" page-key="gallery" meta-description="Behind the seams: photos from the {{ setting('site_name', config('site.name', 'RJ Motorbike')) }} workshop — hides, hand stitching, bespoke fittings and finished made-to-measure leather pieces.">

    <x-breadcrumb :items="['Gallery' => '']" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16"
         x-data="{
            lightboxOpen: false,
            activeIndex: 0,
            images: @js($galleryItems),
            open(index) {
                this.activeIndex = index;
                this.lightboxOpen = true;
            },
            next() {
                if (!this.images.length) return;
                this.activeIndex = (this.activeIndex + 1) % this.images.length;
            },
            prev() {
                if (!this.images.length) return;
                this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
            }
         }"
         @keydown.escape.window="lightboxOpen = false"
         @keydown.arrow-right.window="if (lightboxOpen) next()"
         @keydown.arrow-left.window="if (lightboxOpen) prev()">

        <div class="text-center max-w-2xl mx-auto mb-14 animate-fade-up">
            <span class="text-primary-600 font-heading text-lg italic block mb-2">From the bar</span>
            <h1 class="text-4xl sm:text-5xl font-heading font-bold text-espresso-800 leading-[1.15]">A peek inside our store</h1>
            <div class="gold-line w-20 mx-auto mt-4 mb-6"></div>
            <p class="text-base text-espresso-500 font-light leading-relaxed">
                Explore our products and store gallery.
            </p>
        </div>

        @if(empty($galleryItems))
            <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center">
                <h2 class="font-heading text-lg font-bold text-espresso-800">Gallery coming soon</h2>
                <p class="text-xs text-espresso-500 mt-2 mb-6">We're preparing new photos from the bar. Check back shortly.</p>
                <a href="{{ route('shop') }}" class="btn-primary">Browse the Catalog</a>
            </div>
        @else
            <div class="columns-1 sm:columns-2 lg:columns-3 gap-4">
                @foreach($galleryItems as $index => $image)
                    <button type="button"
                            @click="open({{ $index }})"
                            class="gallery-page-item group relative block w-full break-inside-avoid mb-4 overflow-hidden rounded-2xl bg-cream-100 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400">
                        <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] ?? '' }}" sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                             alt="{{ $image['alt'] }}"
                             loading="lazy"
                             class="block w-full h-auto transition-transform duration-700 ease-out group-hover:scale-105" />
                        <div class="absolute inset-0 bg-gradient-to-t from-espresso-900/60 via-espresso-900/0 to-espresso-900/0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                        <span class="absolute inset-x-0 bottom-0 translate-y-2 px-4 py-4 text-sm font-medium text-white opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                            {{ $image['alt'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="text-center mt-14">
                <a href="{{ route('shop') }}" class="btn-gold px-8 py-3.5 text-sm">Order from the Catalog</a>
            </div>
        @endif

        <div x-show="lightboxOpen"
             x-cloak
             class="fixed inset-0 z-[80] flex items-center justify-center bg-espresso-900/90 p-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.self="lightboxOpen = false">
            <button type="button"
                    @click="lightboxOpen = false"
                    class="absolute top-4 right-4 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition-colors"
                    aria-label="Close gallery">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
            <button type="button"
                    @click.stop="prev()"
                    class="absolute left-3 md:left-6 z-10 rounded-full bg-white/10 p-2.5 text-white hover:bg-white/20 transition-colors"
                    aria-label="Previous image">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </button>
            <button type="button"
                    @click.stop="next()"
                    class="absolute right-3 md:right-6 z-10 rounded-full bg-white/10 p-2.5 text-white hover:bg-white/20 transition-colors"
                    aria-label="Next image">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </button>
            <figure class="relative max-h-[85vh] max-w-5xl w-full" @click.stop>
                <img :src="images[activeIndex]?.src" :srcset="images[activeIndex]?.srcset || ''" sizes="100vw"
                     :alt="images[activeIndex]?.alt"
                     class="mx-auto max-h-[80vh] w-auto max-w-full rounded-xl object-contain shadow-luxury" />
                <figcaption class="mt-4 text-center text-sm text-cream-100" x-text="images[activeIndex]?.alt"></figcaption>
            </figure>
        </div>
    </div>
</x-app-layout>
