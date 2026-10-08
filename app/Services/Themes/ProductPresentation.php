<?php

namespace App\Services\Themes;

use App\Models\Product;
use App\Services\InventoryService;

/** Legacy view-data adapter. Business-derived selections stay in the host. */
final class ProductPresentation
{
    public function card(Product $product): array
    {
        $activeVariants = $product->relationLoaded('variants')
            ? $product->variants->where('is_active', true)->values()
            : $product->variants()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $activeOptionGroups = $product->relationLoaded('optionGroups')
            ? $product->optionGroups->where('is_active', true)->values()
            : $product->optionGroups()->where('is_active', true)->with('values')->orderBy('sort_order')->orderBy('id')->get();

        $availableVariants = $activeVariants->filter(fn ($v) => app(InventoryService::class)->availableQuantity($product, $v) !== 0);
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
                'available_quantity' => app(InventoryService::class)->availableQuantity($product, $v),
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
                            : (($adjustment > 0 ? '+' : '').format_price($adjustment)),
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

        return compact('activeVariants', 'activeOptionGroups', 'availableVariants', 'defaultVariant', 'needsConfiguration', 'showSizePicker', 'variantsPayload', 'optionGroupsPayload', 'cardConfig');
    }
}
