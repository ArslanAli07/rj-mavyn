@props(['product', 'luxury' => false])
@php
    $hasImage = (bool) ($product->main_image_asset_id || $product->main_image);
    $image = $hasImage ? $product->main_image_url : theme_asset('images/placeholder.svg');
    $gallery = $product->gallery_images ?? [];
    $hoverImage = (!empty($gallery) && isset($gallery[0]['url']) && $gallery[0]['url'] !== $image) ? $gallery[0]['url'] : null;
    $categoryName = $product->category?->name ? preg_replace('/^for\s+/i', '', $product->category->name) : null;
    $material = null;
    if (!empty($product->specifications) && is_array($product->specifications)) {
        $specMaterial = collect($product->specifications)->firstWhere('label', 'Material')['value'] ?? null;
        if ($specMaterial) {
            $cleaned = preg_replace('/\s*[\(\[]?\d+(\.\d+)?[\s\-–—]*\d*(\.\d+)?\s*mm[\)\]]?/i', '', $specMaterial);
            $cleaned = preg_replace('/\s+with\s+.*$/i', '', $cleaned);
            $cleaned = trim(explode(',', explode('(', $cleaned)[0])[0]);
            if (strlen($cleaned) > 2) {
                $material = ucwords(strtolower($cleaned));
            }
        }
    }
    if (!$material && !empty($product->short_description)) {
        $mMatches = [];
        if (preg_match('/(sheepskin|cowhide|lambskin|goatskin|calfskin|suede|buffalo)/i', $product->short_description, $mMatches)) {
            $material = ucwords(strtolower($mMatches[1])) . ' Leather';
        }
    }
@endphp
{{-- Reference product card: grey media tile with a hover view, rectangular
     badge, circular product button, uppercase title and a text/price row. --}}
<article class="rj-card" data-product="{{ $product->id }}" data-price="{{ $product->effective_price }}"
    data-new="{{ $product->is_new_arrival ? 1 : 0 }}">
    <div class="rj-card__media">
        <a class="rj-card__link" href="{{ route('product.show', $product->slug) }}" aria-label="{{ $product->title }}">
            <img src="{{ $image }}" srcset="{{ $product->main_image_srcset }}"
                sizes="(max-width: 640px) 80vw, 25vw" alt="{{ $product->main_image_alt }}" loading="lazy"
                width="600" height="750">
        </a>
        @if($hoverImage)
            <a class="rj-card__link rj-card__link--hover" href="{{ route('product.show', $product->slug) }}" tabindex="-1" aria-hidden="true">
                <img src="{{ $hoverImage }}" alt="" loading="lazy" width="600" height="750">
            </a>
        @endif

        @if (!$product->available_for_sale)
            <span class="rj-card__badge">Sold out</span>
        @elseif($product->is_new_arrival)
            <span class="rj-card__badge">New</span>
        @elseif($product->is_bestseller)
            <span class="rj-card__badge">Popular</span>
        @endif

        <div class="rj-card__buttons">
            <a class="rj-card__cta" href="{{ route('product.show', $product->slug) }}" aria-label="View {{ $product->title }}">
                <svg class="icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.875 5.625H3.125a.625.625 0 0 0-.625.625v9.375c0 .345.28.625.625.625h13.75c.345 0 .625-.28.625-.625V6.25a.625.625 0 0 0-.625-.625"/>
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.875 7.5V5a3.125 3.125 0 0 1 6.25 0v2.5"/>
                </svg>
            </a>
            <button class="rj-card__fav" data-favorite="{{ $product->id }}"
                @auth data-wishlist-url="{{ route('wishlist.toggle', $product->id) }}" @endauth
                aria-label="Save {{ $product->title }} to wishlist"
                aria-pressed="{{ auth('web')->check() && in_array($product->id, $storeWishlistedIds ?? []) ? 'true' : 'false' }}">
                <svg class="icon" aria-hidden="true"><use href="#icon-heart"/></svg>
            </button>
        </div>
    </div>

    <div class="rj-card__body">
        <h3 class="rj-card__title">
            <a href="{{ route('product.show', $product->slug) }}">{{ $product->title }}</a>
        </h3>
        <div class="rj-card__row">
            <p class="rj-card__meta">{{ $material ?? ($product->short_description ? \Illuminate\Support\Str::limit($product->short_description, 42) : ($categoryName ? $categoryName.' leather' : '')) }}</p>
            <div class="rj-card__price">
                @if ($product->is_on_sale)
                    <span class="rj-card__price-sale">{{ format_price($product->effective_price) }}</span>
                    <del class="rj-card__price-original">{{ format_price($product->price) }}</del>
                @else
                    <span class="rj-card__price-regular">{{ format_price($product->effective_price) }}</span>
                @endif
            </div>
        </div>
    </div>
</article>
