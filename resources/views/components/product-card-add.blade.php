@props(['product'])

@if (ordering_enabled() && $product->available_for_sale)
@php
    $activeVariants = $product->relationLoaded('variants')
        ? $product->variants->where('is_active', true)->values()
        : $product->variants()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

    $activeOptionGroups = $product->relationLoaded('optionGroups')
        ? $product->optionGroups->where('is_active', true)->values()
        : $product->optionGroups()->where('is_active', true)->with('values')->orderBy('sort_order')->orderBy('id')->get();

    $availableVariants = $activeVariants->filter(fn ($v) => app(\App\Services\InventoryService::class)->availableQuantity($product, $v) !== 0);
    $defaultVariant = $availableVariants->firstWhere('is_default', true) ?? $availableVariants->first();
    $needsConfiguration = $product->needsCardConfiguration();
    $showSizePicker = $activeVariants->count() > 1;

    $optionGroupsForUi = $activeOptionGroups->filter(function ($group) {
        $values = $group->relationLoaded('values')
            ? $group->values->where('is_active', true)
            : $group->values()->where('is_active', true)->get();

        return $values->count() > 1 || $group->effectiveMinSelections() > 0;
    })->values();

    $variantsPayload = $activeVariants->map(function ($v) use ($product) {
        return [
            'id' => (int) $v->id,
            'available_quantity' => app(\App\Services\InventoryService::class)->availableQuantity($product, $v),
            'name' => $v->display_name,
            'effective_price' => (float) $v->effective_price,
            'formatted_price' => format_price($v->effective_price),
        ];
    })->values()->all();

    $optionGroupsPayload = $optionGroupsForUi->map(function ($g) {
        $values = $g->relationLoaded('values')
            ? $g->values->where('is_active', true)
            : $g->values()->where('is_active', true)->get();

        return [
            'id' => (int) $g->id,
            'name' => $g->name,
            'selection_type' => $g->selection_type,
            'min_selections' => $g->effectiveMinSelections(),
            'max_selections' => $g->effectiveMaxSelections(),
            'is_required' => $g->effectiveMinSelections() > 0,
            'values' => $values->values()->map(function ($v) {
                $adjustment = (float) $v->price_adjustment;

                return [
                    'id' => (int) $v->id,
                    'name' => $v->name,
                    'price_adjustment' => $adjustment,
                    'formatted_adjustment' => $adjustment == 0
                        ? null
                        : (($adjustment > 0 ? '+' : '') . format_price($adjustment)),
                ];
            })->all(),
        ];
    })->values()->all();

    $cardConfig = [
        'open' => false,
        'variants' => $variantsPayload,
        'optionGroups' => $optionGroupsPayload,
        'selectedVariantId' => $defaultVariant?->id ? (int) $defaultVariant->id : null,
        'selectedOptionIds' => [],
        'showSizePicker' => $showSizePicker,
    ];
@endphp

@if (! $needsConfiguration)
    <form action="{{ route('cart.add') }}" method="POST">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <input type="hidden" name="product_variant_id" value="{{ $defaultVariant?->id }}">
        <input type="hidden" name="quantity" value="1">
        <button type="submit" class="product-reveal-strip hover-trigger" aria-label="Add {{ $product->title }} to cart">
            <span class="product-reveal-label">Add to Cart</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:12px;height:12px;color:var(--color-ink);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </button>
    </form>
@else
    <div
        class="product-card-config"
        x-data="productCardConfig(@js($cardConfig))"
        @keydown.escape.window="if (open) closePanel()"
    >
        <button
            type="button"
            class="product-reveal-strip hover-trigger"
            @click.prevent="openPanel()"
            aria-label="Choose options for {{ $product->title }}"
        >
            <span class="product-reveal-label">Add to Cart</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:12px;height:12px;color:var(--color-ink);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.200ms
            class="product-card-config-panel"
            @click.stop
        >
            <div class="product-card-config-panel-inner">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-text-muted font-semibold">Choose options</p>
                        <p class="text-xs font-semibold text-ink truncate">{{ $product->title }}</p>
                    </div>
                    <button type="button" @click="closePanel()" class="shrink-0 w-7 h-7 rounded-full border border-border flex items-center justify-center text-ink hover:bg-surface" aria-label="Close">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form
                    action="{{ route('cart.add') }}"
                    method="POST"
                    class="space-y-3"
                    @submit="
                        if (!configurationValid()) {
                            $event.preventDefault();
                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Please complete the required options.', type: 'error' } }));
                            return;
                        }
                        closePanel();
                    "
                >
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="product_variant_id" :value="selectedVariantId">
                    <input type="hidden" name="quantity" value="1">
                    <template x-for="optionId in selectedOptionIdList" :key="'card-opt-' + optionId">
                        <input type="hidden" name="option_value_ids[]" :value="optionId">
                    </template>

                    <template x-if="showSizePicker && variants.length > 1">
                        <div class="space-y-1.5">
                            <span class="block text-[10px] font-semibold text-ink uppercase tracking-wider">Size</span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="v in variants" :key="v.id">
                                    <button
                                        type="button"
                                        @click="selectedVariantId = v.id" :disabled="v.available_quantity === 0"
                                        :class="selectedVariantId === v.id
                                            ? 'border-primary bg-primary/15 text-ink'
                                            : 'border-border bg-bg text-text hover:border-ink/40'"
                                        class="px-2.5 py-1.5 text-[11px] font-semibold border rounded-lg transition-colors"
                                    >
                                        <span x-text="v.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-for="group in optionGroups" :key="group.id">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[10px] font-semibold text-ink uppercase tracking-wider" x-text="group.name"></span>
                                <span class="text-[9px] text-text-muted" x-text="group.is_required ? 'Required' : 'Optional'"></span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="value in group.values" :key="value.id">
                                    <button
                                        type="button"
                                        @click="group.selection_type === 'single' ? selectSingleOption(group, value.id) : toggleMultiOption(group, value.id)"
                                        :class="(group.selection_type === 'single' ? selectedOptionIds[group.id] === value.id : (selectedOptionIds[group.id] || []).includes(value.id))
                                            ? 'border-primary bg-primary/15 text-ink'
                                            : 'border-border bg-bg text-text hover:border-ink/40'"
                                        class="px-2.5 py-1.5 text-[11px] font-medium border rounded-lg transition-colors"
                                    >
                                        <span x-text="value.name"></span>
                                        <template x-if="value.formatted_adjustment">
                                            <span class="text-text-muted ml-0.5" x-text="value.formatted_adjustment"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button
                        type="submit"
                        class="w-full mt-1 py-2.5 rounded-full bg-primary text-ink text-xs font-semibold uppercase tracking-wide hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!configurationValid()"
                    >
                        Add to Cart
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
@endif
