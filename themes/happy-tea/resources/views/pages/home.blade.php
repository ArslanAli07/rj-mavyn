<x-app-layout title="Premium Beverage Experience — {{ setting('site_name', config('site.name', 'Store')) }}" page-key="home">

    <p class="max-w-7xl mx-auto px-4 py-3">{{ theme_setting('welcome_text') }}</p>
    {{-- HERO --}}
    <section id="heroBanner" class="w-full relative">
      <div class="swiper mySwiper w-full">
        <div class="swiper-wrapper">
          @forelse($slides as $index => $slide)
            <div class="swiper-slide w-full" data-swiper-slide-index="{{ $index }}">
              <picture class="block w-full">
                @if($slide->mobile_image_path || $slide->mobile_image_asset_id)
                  <source media="(max-width: 1023px)" srcset="{{ $slide->mobile_image_srcset ?: $slide->mobile_image_url }}" sizes="100vw">
                @endif
                <img
                  src="{{ $slide->image_url }}" srcset="{{ $slide->image_srcset }}" sizes="100vw"
                  alt="{{ $slide->image_alt }}"
                  class="hero-slide-image w-full h-auto block"
                  loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                />
              </picture>
            </div>
          @empty
            <div class="swiper-slide w-full">
              <img
                src="{{ theme_asset('assets/sliders/milk-tea.jpg') }}"
                alt="Happy Tea"
                class="hero-slide-image w-full h-auto block"
              />
            </div>
          @endforelse
        </div>
        <div class="swiper-button-next !hidden lg:!flex !text-ink"></div>
        <div class="swiper-button-prev !hidden lg:!flex !text-ink"></div>
        <div class="swiper-pagination"></div>
      </div>
    </section>

    {{-- Marquee --}}
    <div class="marquee-strip" aria-hidden="true">
      <div class="marquee-track">
        <span class="marquee-item"><span class="marquee-dot"></span>100% Organic</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Handcrafted Daily</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Premium Quality Ingredients</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Freshly Made Every Day</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Crafted with Care</span>
        <span class="marquee-item"><span class="marquee-dot"></span>100% Organic</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Handcrafted Daily</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Premium Quality Ingredients</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Freshly Made Every Day</span>
        <span class="marquee-item"><span class="marquee-dot"></span>Crafted with Care</span>
      </div>
    </div>

    {{-- Category grid --}}
    <section class="py-8 md:py-28">
      <div class="w-full px-4 md:px-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 md:gap-6 mb-8 md:mb-14 scroll-fade-in-up">
          <div>
            <div class="section-label-wrap">
              <span class="section-label-line"></span>
              <span class="text-xs uppercase tracking-[0.25em] text-primary font-semibold">What We Serve</span>
            </div>
            <h2 class="text-2xl md:text-5xl font-bold font-fraunces text-ink mt-3">Crafted for every craving</h2>
          </div>
          <a href="#menu" class="hover-trigger inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-ink border-b border-ink pb-1 w-fit">
            View Full Menu
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
          </a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
          @foreach($homeCategories as $category)
            <a href="#menu-{{ $category->slug }}" class="hover-trigger group tilt-card relative overflow-hidden rounded-2xl aspect-[3/4] cursor-pointer">
              <img src="{{ $category->image_url }}" srcset="{{ $category->image_srcset }}" sizes="(max-width: 640px) 50vw, 25vw" alt="{{ $category->image_alt }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"/>
            </a>
          @endforeach
        </div>
      </div>
    </section>

    {{-- Full menu --}}
    <section
      id="menu"
      class="bg-bg pb-8 md:pb-28"
      x-data="{
        activeCategory: @js($homeCategories->first(fn ($category) => $category->products->isNotEmpty())?->slug),
        categoryBarVisible: false,
        headerHeight: 72,
        categorySlugs: @js($homeCategories->filter(fn ($category) => $category->products->isNotEmpty())->pluck('slug')->values()),
        init() {
          this.$nextTick(() => {
            this.updateActive();
            window.addEventListener('resize', () => this.updateActive(), { passive: true });
          });
        },
        updateActive() {
          const header = document.getElementById('site-top') || document.querySelector('header');
          this.headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 72;

          const menuTop = this.$el.offsetTop;
          const menuBottom = menuTop + this.$el.offsetHeight;
          const viewportTop = window.scrollY + this.headerHeight;
          this.categoryBarVisible = viewportTop >= menuTop && viewportTop < menuBottom;

          const marker = window.scrollY + this.headerHeight + this.$refs.categoryBar.offsetHeight + 32;
          let current = this.categorySlugs[0] ?? null;

          this.categorySlugs.forEach((slug) => {
            const section = document.getElementById(`menu-${slug}`);
            if (section && section.offsetTop <= marker) current = slug;
          });

          if (current === this.activeCategory) return;
          this.activeCategory = current;
          this.$nextTick(() => {
            const activeLink = this.$refs.categoryBar.querySelector('[aria-current=&quot;true&quot;]');
            activeLink?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
          });
        },
        scrollToCategory(slug) {
          const section = document.getElementById(`menu-${slug}`);
          if (!section) return;

          this.activeCategory = slug;
          const offset = this.headerHeight + this.$refs.categoryBar.offsetHeight + 24;
          window.scrollTo({ top: section.offsetTop - offset, behavior: 'smooth' });
          history.replaceState(null, '', `#menu-${slug}`);
        }
      }"
      @scroll.window.throttle.100ms="updateActive()"
    >
      <nav
        x-cloak
        x-ref="categoryBar"
        :style="{ top: headerHeight + 'px' }"
        :class="categoryBarVisible
          ? 'translate-y-0 opacity-100 visible'
          : '-translate-y-full opacity-0 invisible pointer-events-none'"
        class="fixed inset-x-0 z-50 border-y border-border bg-bg/95 backdrop-blur-md shadow-sm transition-all duration-300"
        aria-label="Menu categories"
        @mouseenter="document.body.classList.add('hide-custom-cursor')"
        @mouseleave="document.body.classList.remove('hide-custom-cursor')"
      >
        <div class="max-w-7xl mx-auto px-4 md:px-12">
          <div class="flex items-center gap-2 overflow-x-auto py-3 scrollbar-hide">
            @foreach($homeCategories as $category)
              @continue($category->products->isEmpty())
              <button
                type="button"
                @click="scrollToCategory(@js($category->slug))"
                :aria-current="activeCategory === @js($category->slug) ? 'true' : null"
                :class="activeCategory === @js($category->slug)
                  ? 'bg-ink text-bg'
                  : 'bg-surface text-ink hover:bg-primary-tint'"
                class="shrink-0 rounded-full px-4 py-2 text-xs font-semibold tracking-wide transition-colors sm:px-5"
              >
                {{ $category->name }}
              </button>
            @endforeach
          </div>
        </div>
      </nav>

      <div class="max-w-7xl mx-auto px-4 md:px-12 pt-8 md:pt-20 pb-8 md:pb-20 scroll-fade-in-up">
        <div class="section-label-wrap"><span class="section-label-line"></span><span class="text-xs uppercase tracking-[0.25em] text-primary font-semibold">Our Menu</span></div>
        <h2 class="text-2xl md:text-5xl font-bold font-fraunces text-ink mt-3">The full menu</h2>
      </div>

      <div class="flex flex-col gap-10 md:gap-24">
        @foreach($homeCategories as $category)
          @continue($category->products->isEmpty())
          <div id="menu-{{ $category->slug }}">
            <div class="max-w-7xl mx-auto px-4 md:px-12">
              <h3 class="text-xl md:text-3xl font-semibold font-fraunces text-ink mb-5 md:mb-8 scroll-fade-in-up">{{ $category->name }}</h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 md:gap-8 scroll-fade-in-up">
                @foreach($category->products as $product)
                  @php
                    $badge = null;
                    if ($product->is_featured) $badge = 'Signature';
                    elseif ($product->is_bestseller) $badge = 'Popular';
                    elseif ($product->is_new_arrival) $badge = 'New';
                  @endphp
                  <article class="group">
                    <div class="product-card-inner overflow-hidden rounded-lg bg-bg aspect-[4/5] border border-border relative">
                      <a href="{{ route('product.show', $product->slug) }}" class="hover-trigger absolute inset-0 z-0 block">
                        <img
                          src="{{ $product->main_image_url }}"
                          alt="{{ $product->title }}"
                          class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                          loading="lazy"
                        />
                      </a>
                      @if($badge)
                        <span class="absolute top-4 left-4 z-10 text-[10px] uppercase tracking-widest bg-primary-tint text-ink px-3 py-1 rounded-full font-semibold pointer-events-none">{{ $badge }}</span>
                      @endif
                      <x-product-card-add :product="$product" />
                    </div>
                    <div class="pt-3 sm:pt-4 flex flex-col sm:flex-row items-start justify-between gap-1 sm:gap-4">
                      <div>
                        <a href="{{ route('product.show', $product->slug) }}" class="hover-trigger">
                          <h3 class="text-sm sm:text-base font-semibold text-ink hover:text-primary transition-colors">{{ $product->title }}</h3>
                        </a>
                        <p class="text-[11px] sm:text-xs text-text-muted mt-0.5 sm:mt-1">{{ $category->name }}</p>
                      </div>
                      <span class="product-price text-sm font-semibold text-ink flex-shrink-0">{{ format_price($product->effective_price) }}</span>
                    </div>
                  </article>
                @endforeach
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </section>

    {{-- Testimonials --}}
    @if($testimonials->isNotEmpty())
    <section class="py-8 md:py-28 bg-surface border-y border-border" aria-label="Customer reviews">
      <div class="max-w-7xl mx-auto px-4 md:px-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 md:gap-6 mb-6 md:mb-10 scroll-fade-in-up">
          <div>
            <div class="section-label-wrap">
              <span class="section-label-line"></span>
              <span class="text-xs uppercase tracking-[0.25em] text-primary font-semibold">Reviews</span>
            </div>
            <h2 class="text-2xl md:text-5xl font-bold font-fraunces text-ink mt-3">Loved by the community</h2>
          </div>
          <div class="flex gap-2">
            <button type="button" onclick="window.scrollReviews && window.scrollReviews(-1)" class="w-10 h-10 rounded-full border border-border flex items-center justify-center text-ink hover:bg-bg transition-colors" aria-label="Previous reviews">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" onclick="window.scrollReviews && window.scrollReviews(1)" class="w-10 h-10 rounded-full border border-border flex items-center justify-center text-ink hover:bg-bg transition-colors" aria-label="Next reviews">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
        <div id="reviewsScroll" class="reviews-scroll flex gap-6 overflow-x-auto pb-4 scroll-fade-in-up">
          @foreach($testimonials as $review)
            <article class="min-w-[280px] max-w-sm shrink-0 bg-bg border border-border rounded-2xl p-6">
              <div class="flex items-center gap-1 text-primary mb-3">
                @for($i = 0; $i < (int) $review->rating; $i++)
                  <span>★</span>
                @endfor
              </div>
              <p class="text-sm text-text leading-relaxed">“{{ $review->review }}”</p>
              <div class="mt-5 pt-4 border-t border-border">
                <p class="text-sm font-semibold text-ink">{{ $review->customer_name }}</p>
                <p class="text-xs text-text-muted mt-0.5">{{ $review->customer_location }}</p>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
    @endif

    {{-- Gallery --}}
    @if(!empty($galleryImages))
    <section id="gallery" class="py-8 md:py-28 overflow-hidden">
      <div class="max-w-7xl mx-auto px-4 md:px-12 mb-6 md:mb-10">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 scroll-fade-in-up">
          <div>
            <div class="section-label-wrap">
              <span class="section-label-line"></span>
              <span class="text-xs uppercase tracking-[0.25em] text-primary font-semibold">Gallery</span>
            </div>
            <h2 class="text-2xl md:text-5xl font-bold font-fraunces text-ink mt-3">A peek inside Happy Tea</h2>
          </div>
          <a href="{{ route('gallery') }}" class="hover-trigger inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-ink border-b border-ink pb-1 w-fit">View full gallery<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg></a>
        </div>
      </div>
      <div class="gallery-strip-wrapper scroll-fade-in">
        <div class="gallery-strip-track">
          @foreach([1, 2] as $loopPass)
            @foreach($galleryImages as $image)
              <div class="gallery-strip-item hover-trigger">
                <img src="{{ $image['src'] }}" srcset="{{ $image['srcset'] ?? '' }}" sizes="(max-width: 640px) 80vw, 30vw" alt="{{ $image['alt'] ?? 'Happy Tea gallery' }}" loading="lazy"/>
                <div class="gallery-overlay">
                  <span>{{ $image['alt'] ?? 'Happy Tea' }}</span>
                </div>
              </div>
            @endforeach
          @endforeach
        </div>
      </div>
    </section>
    @endif
</x-app-layout>
