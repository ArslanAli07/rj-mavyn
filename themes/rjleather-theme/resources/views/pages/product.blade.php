<x-app-layout :title="$product->meta_title ?: $product->title.' — '.setting('site_name', config('site.name'))" :meta-description="$product->meta_description ?: $product->short_description" :og-image="$product->main_image_url" og-type="product">
@push('head')
    {!! seo_schema(app(\App\Services\Seo\StructuredDataService::class)->productGraph($product)) !!}
@endpush
@php
    $hasImage = (bool) ($product->main_image_asset_id || $product->main_image);
    $mainImage = $hasImage ? $product->detail_image : ['url' => theme_asset('images/placeholder.svg'), 'alt_text' => $product->main_image_alt];
    $gallery = collect([$mainImage])->merge($product->gallery_images)->unique('url')->values();

    // Sizing tables and gender setup
    $rawSizeChart = $product->size_chart ?? [];
    $sizeTables = $rawSizeChart['tables'] ?? [];
    $hasTables = !empty($sizeTables);
    $tableGenders = collect($sizeTables)->pluck('gender')->unique()->values()->all();
    $defaultGender = in_array('men', $tableGenders, true) ? 'men' : (in_array('women', $tableGenders, true) ? 'women' : ($tableGenders[0] ?? 'men'));

    // Sizing buckets for dropdown
    $groupedSizes = $rawSizeChart['grouped_sizes'] ?? [];
    $customVariant = $activeVariants->first(fn ($v) => str_contains($v->name, 'Custom Size'));
    $menVariants = collect();
    $womenVariants = collect();
    $standardVariants = collect();

    foreach ($activeVariants as $variant) {
        if ($customVariant && $variant->id === $customVariant->id) {
            continue;
        }
        $vName = $variant->display_name;
        if (!empty($groupedSizes['Men']) && in_array($vName, $groupedSizes['Men'], true)) {
            $menVariants->push($variant);
        } elseif (!empty($groupedSizes['Women']) && in_array($vName, $groupedSizes['Women'], true)) {
            $womenVariants->push($variant);
        } elseif (preg_match('/\b(women|womens|woman|ladies)\b/i', $vName)) {
            $womenVariants->push($variant);
        } elseif (preg_match('/\b(men|mens|man)\b/i', $vName)) {
            $menVariants->push($variant);
        } else {
            $standardVariants->push($variant);
        }
    }

    $initialVariant = $defaultVariant ?: ($menVariants->first() ?: ($standardVariants->first() ?: ($womenVariants->first() ?: $customVariant)));
@endphp
@foreach($display->notices as $notice)<p class="rj-flash" role="status">{{ $notice }}</p>@endforeach
<div class="rj-product"
     x-data="{
            activeImage: @js($product->detail_image['url']),
            activeSrcset: @js($product->detail_image['srcset'] ?? ''),
            activeAlt: @js($product->main_image_alt),
            quantity: {{ max(1, min(20, (int) old('quantity', 1))) }},
            wishlisted: false,
            currencySymbol: @js($currencySymbol),
            variants: @js($productVariantsPayload),
            optionGroups: @js($productOptionGroupsPayload),
            selectedVariantId: {{ old('product_variant_id') ? (int) old('product_variant_id') : ($initialVariant?->id ? (int) $initialVariant->id : 'null') }},
            selectedOptionIds: {},
            openSizeModal: false,
            sizeGender: '{{ $defaultGender }}',
            sizeUnit: 'CENTIMETER',
            activeTab: 'overview',
            openFaq: null,
            init() {
                this.optionGroups.forEach(group => {
                    const previous = @js(array_map('intval', old('option_value_ids', [])));
                    const ids = group.values.filter(value => previous.includes(value.id)).map(value => value.id);
                    this.selectedOptionIds[group.id] = group.selection_type === 'single' ? (ids[0] ?? (group.values[0]?.id ?? null)) : ids;
                    if (!this.variants.some(variant => variant.id === this.selectedVariantId)) {
                        this.selectedVariantId = this.variants.find(variant => variant.available_quantity !== 0)?.id ?? null;
                    }
                });
                @auth
                    this.wishlisted = {{ in_array($product->id, $storeWishlistedIds) ? 'true' : 'false' }};
                @else
                    let localWishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                    this.wishlisted = localWishlist.includes({{ $product->id }});
                @endauth
            },
            get selectedVariant() {
                return this.variants.find(v => v.id === this.selectedVariantId) || null;
            },
            get selectedOptionIdList() {
                return this.optionGroups.flatMap(group => {
                    const selected = this.selectedOptionIds[group.id];
                    if (group.selection_type === 'single') {
                        return selected ? [Number(selected)] : [];
                    }
                    return Array.isArray(selected) ? selected.map(Number) : [];
                });
            },
            get optionsTotal() {
                let total = 0;
                this.optionGroups.forEach(group => {
                    const selected = this.selectedOptionIds[group.id];
                    const ids = group.selection_type === 'single'
                        ? (selected ? [Number(selected)] : [])
                        : (Array.isArray(selected) ? selected.map(Number) : []);
                    group.values.forEach(value => {
                        if (ids.includes(value.id)) {
                            total += Math.round(Number(value.price_adjustment || 0) * 100);
                        }
                    });
                });
                return total / 100;
            },
            get unitPrice() {
                const base = this.selectedVariant ? Number(this.selectedVariant.effective_price) : {{ (float) $product->effective_price }};
                return (Math.round(base * 100) + Math.round(this.optionsTotal * 100)) / 100;
            },
            formatMoney(amount) {
                return this.currencySymbol + ' ' + Number(amount).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            },
            savingsTag() {
                const v = this.selectedVariant;
                if (!v || !v.is_on_sale) return '';
                const saved = Math.max(0, Number(v.price) - Number(v.effective_price));
                const pct = Number(v.price) > 0 ? Math.round((saved / Number(v.price)) * 100) : 0;
                const amount = saved.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return 'Save ' + this.currencySymbol + amount + (pct ? ' (' + pct + '% off)' : '');
            },
            isSelected(groupId, valueId) {
                const group = this.optionGroups.find(g => Number(g.id) === Number(groupId));
                const selected = this.selectedOptionIds[groupId];
                if (group && group.selection_type === 'single') {
                    return Number(selected) === Number(valueId);
                }
                return Array.isArray(selected) ? selected.some(id => Number(id) === Number(valueId)) : false;
            },
            optionValueLabel(groupId) {
                const group = this.optionGroups.find(g => Number(g.id) === Number(groupId));
                const selected = this.selectedOptionIds[groupId];
                const value = group?.values?.find(v => Number(v.id) === Number(selected));
                if (!value) return '-- Please Select --';
                const price = Number(value.price_adjustment || 0);
                if (!price) return value.name;
                const amount = price.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                return value.name + ' + ' + this.currencySymbol + ' ' + amount;
            },
            optionSummary(groupId) {
                const group = this.optionGroups.find(g => Number(g.id) === Number(groupId));
                const selected = this.selectedOptionIds[groupId];
                const ids = Array.isArray(selected) ? selected.map(Number) : [];
                if (!ids.length) return 'Select';
                const names = (group?.values || []).filter(v => ids.includes(Number(v.id))).map(v => v.name);
                if (names.length <= 2) return names.join(', ');
                return names.slice(0, 2).join(', ') + '… +' + (names.length - 2);
            },
            shortName(name) {
                return (String(name || '').replace(/\s*\([^)]*\)\s*$/, '').trim()) || name;
            },
            optionSummaryNames(groupId) {
                const group = this.optionGroups.find(g => Number(g.id) === Number(groupId));
                const selected = this.selectedOptionIds[groupId];
                const ids = Array.isArray(selected) ? selected.map(Number) : [];
                return (group?.values || []).filter(v => ids.includes(Number(v.id))).map(v => this.shortName(v.name));
            },
            optionSummaryExtra(groupId) {
                const names = this.optionSummaryNames(groupId);
                return names.length > 2 ? '+' + (names.length - 2) + ' more' : '';
            },
            optionCount(groupId) {
                const selected = this.selectedOptionIds[groupId];
                return Array.isArray(selected) ? selected.length : 0;
            },
            optionGroupTotal(groupId) {
                const group = this.optionGroups.find(g => Number(g.id) === Number(groupId));
                const selected = this.selectedOptionIds[groupId];
                const ids = Array.isArray(selected) ? selected.map(Number) : [];
                return (group?.values || []).filter(v => ids.includes(Number(v.id))).reduce((sum, v) => sum + Number(v.price_adjustment || 0), 0);
            },
            toggleOption(groupId, valueId) {
                const group = this.optionGroups.find(g => Number(g.id) === Number(groupId));
                if (group && group.selection_type === 'single') {
                    this.selectedOptionIds[groupId] = Number(valueId);
                    return;
                }
                const selected = Array.isArray(this.selectedOptionIds[groupId]) ? [...this.selectedOptionIds[groupId]] : [];
                const index = selected.findIndex(id => Number(id) === Number(valueId));
                if (index === -1) {
                    const max = group ? (group.max_selections || 0) : 0;
                    if (max && selected.length >= max) {
                        // Re-assign so :checked re-evaluates and the native click's
                        // visual toggle is reverted for the blocked checkbox.
                        this.selectedOptionIds[groupId] = selected;
                        return;
                    }
                    selected.push(Number(valueId));
                } else {
                    const min = group ? (group.min_selections || 0) : 0;
                    if (min && selected.length <= min) {
                        this.selectedOptionIds[groupId] = selected;
                        return;
                    }
                    selected.splice(index, 1);
                }
                this.selectedOptionIds[groupId] = selected;
            },
            configurationValid() {
                if (!this.selectedVariant || this.selectedVariant.available_quantity === 0) return false;
                if (!Number.isInteger(Number(this.quantity)) || Number(this.quantity) < 1 || Number(this.quantity) > Math.min(20, this.selectedVariant.available_quantity ?? 20)) return false;
                return this.optionGroups.every(group => {
                    const selected = this.selectedOptionIds[group.id];
                    const count = group.selection_type === 'single'
                        ? (selected ? 1 : 0)
                        : (Array.isArray(selected) ? selected.length : 0);
                    if (count < group.min_selections) return false;
                    if (group.max_selections && count > group.max_selections) return false;
                    return true;
                });
            }
         }"
     x-on:keydown.escape.window="openSizeModal = false">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span>
            @if($product->category)
                @foreach(app(\App\Services\Catalog\CategoryTree::class)->trail($product->category) as $step)
                    <a href="{{ route('category.show', $step->slug) }}">{{ $step->name }}</a><span>/</span>
                @endforeach
            @endif
            <span aria-current="page">{{ $product->title }}</span>
        </nav>

        <div class="rj-product__layout">
            {{-- Gallery Section --}}
            <section class="rj-product__gallery" aria-label="{{ $product->title }} images">
                <div class="rj-gallery @if($gallery->count() > 1) rj-gallery--multi @endif" data-gallery>
                    <div class="rj-gallery__frame">
                        <button type="button" class="rj-gallery__stage" data-gallery-stage aria-label="Enlarge {{ $product->title }}">
                            <img src="{{ $mainImage['url'] }}" srcset="{{ $mainImage['srcset'] ?? '' }}" sizes="(max-width: 767px) 100vw, (max-width: 1199px) 55vw, 620px" alt="{{ $mainImage['alt_text'] ?? $product->title }}" data-gallery-main-image fetchpriority="high">
                        </button>
                        @if($gallery->count() > 1)
                            <button type="button" class="rj-gallery__nav rj-gallery__nav--prev" data-gallery-scroll="-1" aria-label="Previous image"><svg class="icon icon-back" aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                            <button type="button" class="rj-gallery__nav rj-gallery__nav--next" data-gallery-scroll="1" aria-label="Next image"><svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                        @endif
                    </div>
                    @if($gallery->count() > 1)
                        <div class="gallery-grid rj-gallery__thumbs" data-gallery-thumbs>
                            @foreach($gallery as $image)
                                <button type="button" class="gallery-image @if($loop->first) is-active @endif" data-gallery-index="{{ $loop->index }}"
                                        data-gallery-src="{{ $image['url'] }}" data-gallery-srcset="{{ $image['srcset'] ?? '' }}"
                                        aria-label="View {{ $product->title }}, image {{ $loop->iteration }}" @if($loop->first) aria-current="true" @endif>
                                    <img src="{{ $image['url'] }}" srcset="{{ $image['srcset'] ?? '' }}" alt="{{ $image['alt_text'] ?? ($product->title . ' — View ' . $loop->iteration . ' (Custom Leather Detail)') }}" loading="lazy" width="180" height="216">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <nav class="rj-product__anchors" aria-label="Product information">
                    <a href="#product-tabs" @click.prevent="activeTab = 'overview'; document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' })">Overview</a>
                    <a href="#product-tabs" @click.prevent="activeTab = 'details'; document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' })">Details</a>
                    @if(!empty($product->specifications))<a href="#product-tabs" @click.prevent="activeTab = 'specifications'; document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' })">Specifications</a>@endif
                    @if($hasTables || !empty($product->size_chart['rows']))<a href="#product-tabs" @click.prevent="activeTab = 'sizing'; document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' })">Size Guide</a>@endif
                    <a href="#product-tabs" @click.prevent="activeTab = 'faqs'; document.getElementById('product-tabs')?.scrollIntoView({ behavior: 'smooth' })">FAQs</a>
                </nav>
            </section>

            {{-- Purchase / Sticky Config Aside --}}
            <aside class="purchase-sticky rj-product__purchase" aria-label="Product options">
                {{-- Sticky Head: SKU, title & price pin under the header while the options scroll --}}
                <div class="rj-product__head">
                    <div class="rj-product__meta-bar">
                        @if($product->sku)
                            <div class="rj-sku-tag">SKU {{ $product->sku }}</div>
                        @endif
                    </div>

                    <h1 class="rj-display rj-title rj-product__title" x-ref="productTitle">{{ $product->title }}</h1>

                    {{-- Price Row: the comparison price and the SAVE tag show together --
                         only while the selected variant is on sale and no priced option
                         adds to the unit price (options are never discounted, so a bare
                         "SAVE 20%" beside option-loaded totals would be misleading). --}}
                    <div class="rj-product__price">
                        <span class="rj-price-current" x-text="formatMoney(unitPrice)">{{ format_price($product->effective_price) }}</span>
                        <template x-if="selectedVariant && selectedVariant.is_on_sale && optionsTotal === 0">
                            <del class="rj-price-original" x-text="selectedVariant.formatted_price"></del>
                        </template>
                        @if($product->is_on_sale)
                            <span class="rj-discount-tag" x-cloak x-show="selectedVariant && selectedVariant.is_on_sale && optionsTotal === 0" x-text="savingsTag()"></span>
                        @endif
                    </div>
                </div>

                <p class="rj-product__helper">Select your preferred options below to personalize your order.</p>

                @if(!ordering_manually_enabled())
                    <p class="rj-product-notice">Online ordering is paused. You can still browse our collection.</p>
                @elseif(!shop_is_open())
                    <p class="rj-product-notice">Online ordering is available from {{ ordering_hours_label() }}.</p>
                @elseif($product->available_for_sale)
                    <form action="{{ route('cart.add') }}" method="POST" class="rj-product-form" @submit="if (!configurationValid()) { $event.preventDefault(); $event.stopPropagation(); window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Please complete the required options and check the quantity.', type: 'error' } })); }">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="product_variant_id" :value="selectedVariantId" value="{{ $defaultVariant?->id }}">
                        <template x-for="optionId in selectedOptionIdList" :key="optionId">
                            <input type="hidden" name="option_value_ids[]" :value="optionId">
                        </template>

                        <div class="rj-configurations">
                            {{-- Size Selector --}}
                            @if($activeVariants->isNotEmpty())
                                <div class="rj-form-group">
                                    <div class="size-heading">
                                        <label class="rj-form-label" for="product-size-select">SIZE <span class="rj-req-mark">*</span></label>
                                    </div>
                                    <div class="rj-select-wrapper">
                                        <select id="product-size-select" class="rj-custom-select" x-model.number="selectedVariantId" @change="quantity = Math.min(quantity, selectedVariant?.available_quantity ?? 20)">
                                            <option value="" disabled>--Please Select--</option>
                                            @if($customVariant)
                                                <option value="{{ $customVariant->id }}">{{ $customVariant->display_name }}</option>
                                            @endif

                                            @if($menVariants->isNotEmpty())
                                                <optgroup label="Men">
                                                    @foreach($menVariants as $variant)
                                                        <option value="{{ $variant->id }}">{{ $variant->display_name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif

                                            @if($womenVariants->isNotEmpty())
                                                <optgroup label="Women">
                                                    @foreach($womenVariants as $variant)
                                                        <option value="{{ $variant->id }}">{{ $variant->display_name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif

                                            @if($standardVariants->isNotEmpty())
                                                @if($menVariants->isNotEmpty() || $womenVariants->isNotEmpty())
                                                    <optgroup label="Standard Sizes">
                                                        @foreach($standardVariants as $variant)
                                                            <option value="{{ $variant->id }}">{{ $variant->display_name }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @else
                                                    @foreach($standardVariants as $variant)
                                                        <option value="{{ $variant->id }}">{{ $variant->display_name }}</option>
                                                    @endforeach
                                                @endif
                                            @endif
                                        </select>
                                        <svg class="icon rj-select-arrow" aria-hidden="true"><use href="#icon-chevron"/></svg>
                                    </div>
                                    @if($hasTables || !empty($product->size_chart['rows']))
                                        <div class="rj-size-guide-link">
                                            Check our <a href="#size-modal" @click.prevent="openSizeModal = true">Size Chart</a> to find your right size.
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Option Groups: single-choice renders as a dropdown,
                                 multi-choice "extras" render as priced checkboxes --}}
                            @foreach($activeOptionGroups as $group)
                                <div class="rj-form-group">
                                    <span class="rj-form-label">
                                        {{ strtoupper($group->name) }}
                                        @if($group->effectiveMinSelections() > 0)
                                            <span class="rj-req-mark">*</span>
                                        @endif
                                    </span>
                                    @if($group->selection_type === 'multiple')
                                        {{-- Multi-choice extras open as a dropdown panel of priced checkboxes --}}
                                        <div class="rj-option-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                            <button type="button" class="rj-option-dropdown__toggle" @click="open = !open" :aria-expanded="open" aria-controls="opt-group-{{ $group->id }}" :title="optionSummary({{ $group->id }})">
                                                <span class="rj-option-dropdown__chips">
                                                    <span class="rj-option-dropdown__count" x-show="optionCount({{ $group->id }}) > 0" x-text="optionCount({{ $group->id }}) + ' selected'"></span>
                                                    <template x-for="(name, idx) in optionSummaryNames({{ $group->id }})" :key="name">
                                                        <span class="rj-option-chip" x-show="idx < 2" x-text="name"></span>
                                                    </template>
                                                    <template x-if="optionSummaryExtra({{ $group->id }})">
                                                        <span class="rj-option-chip rj-option-chip--more" x-text="optionSummaryExtra({{ $group->id }})"></span>
                                                    </template>
                                                    <span class="rj-option-dropdown__placeholder" x-show="optionCount({{ $group->id }}) === 0">Select</span>
                                                </span>
                                                <span class="rj-option-dropdown__total" x-text="optionGroupTotal({{ $group->id }}) > 0 ? '+ ' + formatMoney(optionGroupTotal({{ $group->id }})) : ''"></span>
                                                <svg class="icon" :class="{ 'is-flipped': open }" aria-hidden="true"><use href="#icon-chevron"/></svg>
                                            </button>
                                            <div class="rj-option-dropdown__menu" id="opt-group-{{ $group->id }}" x-show="open" x-cloak x-transition.opacity.duration.150ms role="group" aria-label="{{ $group->name }}">
                                                <div class="rj-option-dropdown__list">
                                                    @foreach($group->values->where('is_active', true) as $val)
                                                        <label class="rj-option-check">
                                                            <input type="checkbox"
                                                                   :checked="isSelected({{ $group->id }}, {{ $val->id }})"
                                                                   @change="toggleOption({{ $group->id }}, {{ $val->id }})">
                                                            <span class="rj-option-check__name">{{ $val->name }}</span>
                                                            <span class="rj-option-check__price">+ {{ format_price($val->price_adjustment) }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <div class="rj-option-dropdown__footer">
                                                    <span class="rj-option-dropdown__footer-count" x-text="optionCount({{ $group->id }}) + ' selected'"></span>
                                                    <span class="rj-option-dropdown__footer-total" x-text="optionGroupTotal({{ $group->id }}) > 0 ? '+ ' + formatMoney(optionGroupTotal({{ $group->id }})) : ''"></span>
                                                    <button type="button" class="rj-option-dropdown__done" @click="open = false">DONE</button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Single-choice groups open as a dropdown panel of priced radio rows (matches LC) --}}
                                        <div class="rj-option-dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                            <button type="button" class="rj-option-dropdown__toggle" @click="open = !open" :aria-expanded="open" aria-controls="opt-group-{{ $group->id }}">
                                                <span class="rj-option-dropdown__value" x-text="optionValueLabel({{ $group->id }})"></span>
                                                <svg class="icon" :class="{ 'is-flipped': open }" aria-hidden="true"><use href="#icon-chevron"/></svg>
                                            </button>
                                            <div class="rj-option-dropdown__menu" id="opt-group-{{ $group->id }}" x-show="open" x-cloak x-transition.opacity.duration.150ms role="radiogroup" aria-label="{{ $group->name }}">
                                                @foreach($group->values->where('is_active', true) as $val)
                                                    <label class="rj-option-radio">
                                                        <input type="radio"
                                                               :checked="isSelected({{ $group->id }}, {{ $val->id }})"
                                                               @change="toggleOption({{ $group->id }}, {{ $val->id }}); open = false">
                                                        <span class="rj-option-check__name">{{ $val->name }}</span>
                                                        <span class="rj-option-check__price">+ {{ format_price($val->price_adjustment) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Order Instructions (Textarea matching Image 3) --}}
                            <div class="rj-form-group">
                                <label class="rj-form-label" for="special-request">ORDER INSTRUCTIONS</label>
                                <textarea id="special-request"
                                          name="special_request"
                                          rows="3"
                                          class="rj-custom-textarea"
                                          placeholder="You can request minor changes in Design, change of Color and Accessories.">{{ old('special_request') }}</textarea>
                            </div>
                        </div>

                        {{-- Quantity Row with Made to Order indicator --}}
                        <div class="rj-quantity-row">
                            <div class="rj-quantity-stepper">
                                <button type="button" @click="if(quantity > 1) quantity--" aria-label="Decrease quantity">&minus;</button>
                                <input id="product-quantity" name="quantity" type="number" min="1" max="20" x-model.number="quantity" value="{{ old('quantity', 1) }}">
                                <button type="button" @click="if(quantity < Math.min(20, selectedVariant?.available_quantity ?? 20)) quantity++" aria-label="Increase quantity">+</button>
                            </div>
                            <span class="rj-badge-made-to-order">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Made to order
                            </span>
                        </div>

                        {{-- Add to Bag / Purchase Action --}}
                        <div class="purchase-actions">
                            <button type="submit" class="rj-btn-add-to-bag" :disabled="!configurationValid()">ADD TO CART</button>
                            <button type="button" class="purchase-favorite" data-favorite="{{ $product->id }}" @auth data-wishlist-url="{{ route('wishlist.toggle', $product->id) }}" @endauth aria-label="Save {{ $product->title }} to wishlist" aria-pressed="{{ auth('web')->check() && in_array($product->id, $storeWishlistedIds) ? 'true' : 'false' }}"><svg class="icon"><use href="#icon-heart"/></svg></button>
                        </div>
                        <p class="rj-checkout-secure-caption">Continue to secure checkout</p>

                        {{-- Compact 4-Card Feature Bar: directly under the purchase actions --}}
                        <div class="rj-product-features">
                            <div class="rj-feature-card">
                                <span class="rj-feature-card__icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                                </span>
                                <span class="rj-feature-card__title">Made To Order</span>
                                <span class="rj-feature-card__sub">Made After You Order</span>
                            </div>
                            <a href="{{ route('shipping') }}" class="rj-feature-card">
                                <span class="rj-feature-card__icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                </span>
                                <span class="rj-feature-card__title">Free Worldwide Shipping</span>
                                <span class="rj-feature-card__sub">On Orders Over $699.00 USD</span>
                            </a>
                            <a href="{{ route('returns') }}" class="rj-feature-card">
                                <span class="rj-feature-card__icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </span>
                                <span class="rj-feature-card__title">Delivery &amp; Returns</span>
                                <span class="rj-feature-card__sub">View Timelines &amp; Policy</span>
                            </a>
                            <button type="button" class="rj-feature-card" @click="openSizeModal = true">
                                <span class="rj-feature-card__icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.3 8.7 8.7 21.3a2.4 2.4 0 0 1-3.4 0l-2.6-2.6a2.4 2.4 0 0 1 0-3.4L15.3 2.7a2.4 2.4 0 0 1 3.4 0l2.6 2.6a2.4 2.4 0 0 1 0 3.4Z"/><path d="m7.5 10.5 2 2"/><path d="m10.5 7.5 2 2"/><path d="m13.5 4.5 2 2"/></svg>
                                </span>
                                <span class="rj-feature-card__title">Size Guide</span>
                                <span class="rj-feature-card__sub">Find Your Size</span>
                            </button>
                        </div>
                    </form>
                @else
                    <p class="rj-product-notice">This product is currently unavailable. <a class="rj-textlink" href="{{ route('contact') }}">Contact us</a></p>
                @endif
            </aside>
        </div>

        {{-- Rich Tabs Below The Fold --}}
        <div class="rj-product-tabs" id="product-tabs">
            <div class="rj-product-tabs__nav" role="tablist" aria-label="Product Information">
                <button type="button" role="tab" :aria-selected="activeTab === 'overview'" :class="{ 'is-active': activeTab === 'overview' }" @click="activeTab = 'overview'">OVERVIEW</button>
                <button type="button" role="tab" :aria-selected="activeTab === 'details'" :class="{ 'is-active': activeTab === 'details' }" @click="activeTab = 'details'">DETAILS</button>
                @if(!empty($product->specifications))
                    <button type="button" role="tab" :aria-selected="activeTab === 'specifications'" :class="{ 'is-active': activeTab === 'specifications' }" @click="activeTab = 'specifications'">SPECIFICATIONS</button>
                @endif
                @if($hasTables || !empty($product->size_chart['rows']))
                    <button type="button" role="tab" :aria-selected="activeTab === 'sizing'" :class="{ 'is-active': activeTab === 'sizing' }" @click="activeTab = 'sizing'">SIZE GUIDE</button>
                @endif
                <button type="button" role="tab" :aria-selected="activeTab === 'faqs'" :class="{ 'is-active': activeTab === 'faqs' }" @click="activeTab = 'faqs'">FAQS</button>
            </div>

            <div class="rj-product-tabs__content">
                {{-- Overview Tab --}}
                <div class="rj-product-tabs__panel" x-show="activeTab === 'overview'" role="tabpanel">
                    <div class="rj-prose">{!! $product->description ?: e($product->short_description) !!}</div>
                </div>

                {{-- Details Tab --}}
                <div class="rj-product-tabs__panel" x-show="activeTab === 'details'" role="tabpanel" x-cloak>
                    <div class="rj-details-grid">
                        @if($product->sku)
                            <div class="rj-detail-card">
                                <span class="rj-detail-label">Product Code</span>
                                <span class="rj-detail-value">{{ $product->sku }}</span>
                            </div>
                        @endif
                        @if($product->category)
                            <div class="rj-detail-card">
                                <span class="rj-detail-label">Collection</span>
                                <span class="rj-detail-value">{{ preg_replace('/^for\s+/i', '', $product->category->name) }}</span>
                            </div>
                        @endif
                        <div class="rj-detail-card">
                            <span class="rj-detail-label">Availability</span>
                            <span class="rj-detail-value">{{ $product->available_for_sale ? 'In stock, handcrafted to order' : 'Currently unavailable' }}</span>
                        </div>
                        <div class="rj-detail-card">
                            <span class="rj-detail-label">Craftsmanship</span>
                            <span class="rj-detail-value">Handmade &amp; tailored leather gear since 2009</span>
                        </div>
                        @if($showIngredients && $product->ingredients)
                            <div class="rj-detail-card rj-detail-card--full">
                                <span class="rj-detail-label">Materials</span>
                                <span class="rj-detail-value">{{ $product->ingredients }}</span>
                            </div>
                        @endif
                        @if($showAllergens && $product->allergens)
                            <div class="rj-detail-card rj-detail-card--full">
                                <span class="rj-detail-label">Care information</span>
                                <span class="rj-detail-value">{{ $product->allergens }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Specifications Tab --}}
                @if(!empty($product->specifications))
                    <div class="rj-product-tabs__panel" x-show="activeTab === 'specifications'" role="tabpanel" x-cloak>
                        <div class="rj-table-wrap">
                            <table class="rj-table rj-table--specs">
                                <tbody>
                                    @foreach($product->specifications as $specification)
                                        <tr>
                                            <th scope="row">{{ $specification['label'] }}</th>
                                            <td>{{ $specification['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Size Guide Tab (In-page tab) --}}
                @if($hasTables || !empty($product->size_chart['rows']))
                    <div class="rj-product-tabs__panel" x-show="activeTab === 'sizing'" role="tabpanel" x-cloak>
                        {{-- Controls Bar: Gender tabs + Unit toggle --}}
                        <div class="rj-size-controls">
                            @if(count($tableGenders) > 1)
                                <div class="rj-gender-tabs">
                                    @foreach($tableGenders as $g)
                                        <button type="button" class="rj-gender-btn" :class="{ 'is-active': sizeGender === '{{ $g }}' }" @click="sizeGender = '{{ $g }}'">{{ ucfirst($g) }}</button>
                                    @endforeach
                                </div>
                            @endif

                            <div class="rj-unit-toggle">
                                <button type="button" class="rj-unit-btn" :class="{ 'is-active': sizeUnit === 'CENTIMETER' }" @click="sizeUnit = 'CENTIMETER'">CM</button>
                                <button type="button" class="rj-unit-btn" :class="{ 'is-active': sizeUnit === 'INCHES' }" @click="sizeUnit = 'INCHES'">INCHES</button>
                            </div>
                        </div>

                        {{-- Multi-table display --}}
                        @if($hasTables)
                            @foreach($sizeTables as $idx => $tbl)
                                <div class="rj-size-table-container" x-show="sizeGender === '{{ $tbl['gender'] }}' && sizeUnit === '{{ $tbl['unit'] }}'">
                                    <div class="rj-table-caption">{{ $tbl['chart'] }} ({{ $tbl['unit'] }})</div>
                                    <div class="rj-table-wrap">
                                        <table class="rj-table rj-table--sizes">
                                            <thead>
                                                <tr>
                                                    @foreach($tbl['columns'] as $col)
                                                        <th scope="col">{{ $col ?: 'Size' }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($tbl['rows'] as $r)
                                                    <tr>
                                                        @foreach($r as $cell)
                                                            <td>{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            {{-- Fallback single table --}}
                            <div class="rj-table-wrap">
                                <table class="rj-table rj-table--sizes">
                                    <thead>
                                        <tr>
                                            @foreach($product->size_chart['columns'] ?? [] as $col)
                                                <th scope="col">{{ $col ?: 'Size' }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($product->size_chart['rows'] ?? [] as $r)
                                            <tr>
                                                @foreach($r as $cell)
                                                    <td>{{ $cell }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if(!empty($product->size_chart['diagram']))
                            <div class="rj-size-diagram-box">
                                <img src="{{ $product->size_chart['diagram'] }}" alt="Size Guide Diagram" class="rj-size-diagram-img" loading="lazy" referrerpolicy="no-referrer">
                                <div class="rj-size-instructions">
                                    <h4>How to Measure for a Perfect Fit:</h4>
                                    <ul>
                                        <li><strong>Neck:</strong> Measure around the base of your neck where a collared shirt would fit.</li>
                                        <li><strong>Sleeve:</strong> Measure from the center back of your neck, over your shoulder, down to your wrist bone.</li>
                                        <li><strong>Chest:</strong> Measure around the fullest part of your chest, keeping the tape level under your arms.</li>
                                        <li><strong>Waist:</strong> Measure around your natural waistline, where you typically wear trousers.</li>
                                        <li><strong>Height:</strong> Stand straight against a flat wall without shoes and measure from floor to crown.</li>
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- FAQs Tab --}}
                <div class="rj-product-tabs__panel" x-show="activeTab === 'faqs'" role="tabpanel" x-cloak>
                    <div class="rj-faq-list">
                        <div class="rj-faq-item" :class="{ 'is-open': openFaq === 1 }">
                            <button type="button" class="rj-faq-question" @click="openFaq = (openFaq === 1 ? null : 1)">
                                <span>How do Custom Sizing and Made-to-Measure orders work?</span>
                                <span class="rj-faq-icon" x-text="openFaq === 1 ? '−' : '+'">+</span>
                            </button>
                            <div class="rj-faq-answer" x-show="openFaq === 1" x-collapse>
                                <p>When you select "Custom Size (+ $95.00)" from the size dropdown, our master patternmakers hand-cut each leather panel to your exact body proportions. After checkout, our team contacts you to verify all 12 key measurements (chest, shoulders, sleeve length, waist, torso length, etc.) before production begins.</p>
                            </div>
                        </div>

                        <div class="rj-faq-item" :class="{ 'is-open': openFaq === 2 }">
                            <button type="button" class="rj-faq-question" @click="openFaq = (openFaq === 2 ? null : 2)">
                                <span>What is the standard production and delivery timeline?</span>
                                <span class="rj-faq-icon" x-text="openFaq === 2 ? '−' : '+'">+</span>
                            </button>
                            <div class="rj-faq-answer" x-show="openFaq === 2" x-collapse>
                                <p>Standard handcrafted production takes 10 to 15 working days. Once tailored and quality-inspected, orders are dispatched via express tracked courier (DHL / FedEx Express), arriving within 3 to 6 business days worldwide.</p>
                            </div>
                        </div>

                        <div class="rj-faq-item" :class="{ 'is-open': openFaq === 3 }">
                            <button type="button" class="rj-faq-question" @click="openFaq = (openFaq === 3 ? null : 3)">
                                <span>What is the difference between the Lining Options?</span>
                                <span class="rj-faq-icon" x-text="openFaq === 3 ? '−' : '+'">+</span>
                            </button>
                            <div class="rj-faq-answer" x-show="openFaq === 3" x-collapse>
                                <p><strong>Regular Viscose Lining:</strong> Our standard 100% breathable silk-soft viscose lining, offering smooth comfort and easy slip-on wear.<br>
                                   <strong>No Lining:</strong> Exposes the raw, unlined leather interior for authentic vintage motorcycle jackets.<br>
                                   <strong>Sublimated Print Lining:</strong> High-definition full-color inner artwork custom printed directly into the lining fabric.</p>
                            </div>
                        </div>

                        <div class="rj-faq-item" :class="{ 'is-open': openFaq === 4 }">
                            <button type="button" class="rj-faq-question" @click="openFaq = (openFaq === 4 ? null : 4)">
                                <span>How should I care for my genuine leather apparel?</span>
                                <span class="rj-faq-icon" x-text="openFaq === 4 ? '−' : '+'">+</span>
                            </button>
                            <div class="rj-faq-answer" x-show="openFaq === 4" x-collapse>
                                <p>Hang your jacket on a wide contoured wooden hanger to preserve shoulder shape. Keep away from direct sunlight and radiators. Condition every 6 to 12 months with natural leather cream or balm to maintain supple texture and moisture barrier.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Related Products --}}
        @if($related->isNotEmpty())
            <section class="rj-related">
                <div class="rj-section-head">
                    <div class="rj-section-head__copy">
                        <span class="rj-eyebrow">Complete the look</span>
                        <h2 class="rj-display rj-title">You might also like</h2>
                    </div>
                    <a class="rj-textlink" href="{{ route('shop') }}">Shop all <svg class="icon"><use href="#icon-arrow"/></svg></a>
                </div>
                <div class="rj-grid">
                    @foreach($related as $product)<x-product-card :product="$product" />@endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Interactive Size Guide Modal (Alpine Dialog) --}}
    <div class="rj-size-modal-backdrop" x-show="openSizeModal" x-cloak @click.self="openSizeModal = false">
        <div class="rj-size-modal-dialog">
            <div class="rj-size-modal-header">
                <div>
                    <h3 class="rj-size-modal-title">SIZE GUIDE</h3>
                    <p class="rj-size-modal-subtitle">{{ $product->title }}</p>
                </div>
                <button type="button" class="rj-size-modal-close" @click="openSizeModal = false" aria-label="Close size guide">&times;</button>
            </div>

            <div class="rj-size-modal-body">
                {{-- Controls: Demographics + Units --}}
                <div class="rj-size-controls" style="margin-bottom: 16px;">
                    @if(count($tableGenders) > 1)
                        <div class="rj-gender-tabs">
                            @foreach($tableGenders as $g)
                                <button type="button" class="rj-gender-btn" :class="{ 'is-active': sizeGender === '{{ $g }}' }" @click="sizeGender = '{{ $g }}'">{{ ucfirst($g) }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="rj-unit-toggle">
                        <button type="button" class="rj-unit-btn" :class="{ 'is-active': sizeUnit === 'CENTIMETER' }" @click="sizeUnit = 'CENTIMETER'">CM</button>
                        <button type="button" class="rj-unit-btn" :class="{ 'is-active': sizeUnit === 'INCHES' }" @click="sizeUnit = 'INCHES'">INCHES</button>
                    </div>
                </div>

                {{-- Tables --}}
                @if($hasTables)
                    @foreach($sizeTables as $idx => $tbl)
                        <div class="rj-size-table-container" x-show="sizeGender === '{{ $tbl['gender'] }}' && sizeUnit === '{{ $tbl['unit'] }}'">
                            <div class="rj-table-caption">{{ $tbl['chart'] }} ({{ $tbl['unit'] }})</div>
                            <div class="rj-table-wrap">
                                <table class="rj-table rj-table--sizes">
                                    <thead>
                                        <tr>
                                            @foreach($tbl['columns'] as $col)
                                                <th scope="col">{{ $col ?: 'Size' }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tbl['rows'] as $r)
                                            <tr>
                                                @foreach($r as $cell)
                                                    <td>{{ $cell }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="rj-table-wrap">
                        <table class="rj-table rj-table--sizes">
                            <thead>
                                <tr>
                                    @foreach($product->size_chart['columns'] ?? [] as $col)
                                        <th scope="col">{{ $col ?: 'Size' }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($product->size_chart['rows'] ?? [] as $r)
                                    <tr>
                                        @foreach($r as $cell)
                                            <td>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Measurement Guide --}}
                @if(!empty($product->size_chart['diagram']))
                    <div class="rj-size-diagram-box">
                        <img src="{{ $product->size_chart['diagram'] }}" alt="Size Guide Diagram" class="rj-size-diagram-img" loading="lazy" referrerpolicy="no-referrer">
                        <div class="rj-size-instructions">
                            <h4>How to Measure for a Perfect Fit:</h4>
                            <ul>
                                <li><strong>Neck:</strong> Measure around the base of your neck where a collared shirt fits.</li>
                                <li><strong>Sleeve:</strong> Measure from the center back of your neck down to your wrist.</li>
                                <li><strong>Chest:</strong> Measure around the fullest part of your chest under arms.</li>
                                <li><strong>Waist:</strong> Measure around your natural waistline.</li>
                                <li><strong>Height:</strong> Measure from head to floor in socks.</li>
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <div class="rj-size-modal-footer">
                <button type="button" class="rj-button rj-button--accent" @click="openSizeModal = false">CLOSE SIZE GUIDE</button>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
