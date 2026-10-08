<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mavyn\Core\ProductConfiguration;

class ProductConfigurationService
{
    /**
     * Resolve, validate, and price a product configuration.
     *
     * @param  array<int, int|string>  $optionValueIds
     * @return array{
     *     variant: ProductVariant,
     *     unit_price: string,
     *     weight: string|null,
     *     variant_name: string,
     *     options_snapshot: array<int, array{value_id:int,group_id:int,group_name:string,value_name:string,price_adjustment:string}>,
     *     special_request: string|null,
     *     configuration_signature: string
     * }
     */
    public function resolve(Product $product, ?int $variantId = null, array $optionValueIds = [], ?string $specialRequest = null): array
    {
        $product->loadMissing([
            'variants' => fn ($q) => $q->ordered(),
            'optionGroups.values',
        ]);

        $variant = $this->resolveVariant($product, $variantId);
        $selectedValues = $this->resolveSelectedValues($product, $optionValueIds);
        $this->assertGroupRules($product, $selectedValues);

        $optionsSnapshot = $selectedValues
            ->sortBy(fn (ProductOptionValue $value) => [
                $value->group->sort_order,
                $value->group->id,
                $value->sort_order,
                $value->id,
            ])
            ->values()
            ->map(fn (ProductOptionValue $value) => [
                'value_id' => $value->id,
                'group_id' => $value->product_option_group_id,
                'group_name' => $value->group->name,
                'value_name' => $value->name,
                'price_adjustment' => Money::decimal(Money::minor($value->price_adjustment)),
            ])
            ->all();

        $optionsTotal = collect($optionsSnapshot)->sum(fn ($option) => Money::minor($option['price_adjustment']));
        $unitPrice = Money::decimal(Money::minor($variant->discount_price ?? $variant->price) + $optionsTotal);
        $specialRequest = $this->normalizeSpecialRequest($specialRequest);
        $variantName = $variant->display_name;

        return [
            'variant' => $variant,
            'unit_price' => $unitPrice,
            'weight' => $variantName,
            'variant_name' => $variantName,
            'options_snapshot' => $optionsSnapshot,
            'special_request' => $specialRequest,
            'configuration_signature' => $this->signature(
                $product->id,
                $variant->id,
                collect($optionsSnapshot)->pluck('value_id')->all(),
                $specialRequest
            ),
        ];
    }

    /**
     * @param  array<int, int|string>  $optionValueIds
     */
    public function signature(int $productId, ?int $variantId, array $optionValueIds = [], ?string $specialRequest = null): string
    {
        return ProductConfiguration::signature($productId, $variantId, $optionValueIds, $specialRequest);
    }

    protected function resolveVariant(Product $product, ?int $variantId): ProductVariant
    {
        $variants = $product->variants->where('is_active', true)->values();

        if ($variants->isEmpty()) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product has no available variants.',
            ]);
        }

        if ($variantId) {
            $variant = $variants->firstWhere('id', $variantId);
            if (! $variant) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected variant is invalid for this product.',
                ]);
            }

            return $variant;
        }

        return $variants->firstWhere('is_default', true) ?? $variants->first();
    }

    /**
     * @param  array<int, int|string>  $optionValueIds
     * @return Collection<int, ProductOptionValue>
     */
    protected function resolveSelectedValues(Product $product, array $optionValueIds): Collection
    {
        $ids = collect($optionValueIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $allowedValues = $product->optionGroups
            ->where('is_active', true)
            ->flatMap(fn (ProductOptionGroup $group) => $group->values->where('is_active', true)->map(function (ProductOptionValue $value) use ($group) {
                $value->setRelation('group', $group);

                return $value;
            }));

        $selected = $allowedValues->whereIn('id', $ids->all())->values();

        if ($selected->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'One or more selected options are invalid for this product.',
            ]);
        }

        return $selected;
    }

    /**
     * @param  Collection<int, ProductOptionValue>  $selectedValues
     */
    protected function assertGroupRules(Product $product, Collection $selectedValues): void
    {
        $grouped = $selectedValues->groupBy('product_option_group_id');

        foreach ($product->optionGroups->where('is_active', true) as $group) {
            $count = $grouped->get($group->id, collect())->count();
            $min = $group->effectiveMinSelections();
            $max = $group->effectiveMaxSelections();

            if ($count < $min) {
                throw ValidationException::withMessages([
                    'option_value_ids' => "Please select at least {$min} option(s) for {$group->name}.",
                ]);
            }

            if ($max !== null && $count > $max) {
                throw ValidationException::withMessages([
                    'option_value_ids' => "You may select at most {$max} option(s) for {$group->name}.",
                ]);
            }
        }
    }

    protected function normalizeSpecialRequest(?string $specialRequest): ?string
    {
        return ProductConfiguration::normalizeSpecialRequest($specialRequest);
    }
}
