<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mavyn\Core\Currencies;
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
    public function resolve(Product $product, ?int $variantId = null, array $optionValueIds = [], ?string $specialRequest = null, ?array $customization = null): array
    {
        $product->loadMissing([
            'variants' => fn ($q) => $q->ordered(),
            'optionGroups.values',
        ]);

        $pricing = app(PricingService::class);
        $context = $pricing->assertReady();
        $variant = $this->resolveVariant($product, $variantId);
        $selectedValues = $this->resolveSelectedValues($product, $optionValueIds);
        $variantPrice = $pricing->price('variant', $variant->id);
        $optionPrices = $pricing->read('option', $selectedValues->pluck('id')->all(), $context->currency);
        if (! $variantPrice || $selectedValues->contains(fn ($v) => ! isset($optionPrices[$v->id]))) {
            throw ValidationException::withMessages(['price' => 'This configuration is unavailable in '.$context->currency.'.']);
        }
        $this->assertGroupRules($product, $selectedValues);
        $customization = app(ProductCustomizationService::class)->assertValid($product, $selectedValues, $customization);

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
                'price_adjustment' => Money::decimal($optionPrices[$value->id]['amount_minor']),
            ])
            ->all();

        $optionsTotal = collect($optionsSnapshot)->sum(fn ($option) => Money::minor($option['price_adjustment']));
        $unitMinor = ($variantPrice['sale_minor'] ?? $variantPrice['amount_minor']) + $optionsTotal;
        if ($unitMinor < 0) {
            throw ValidationException::withMessages(['option_value_ids' => 'These options would produce a negative price. Choose another configuration.']);
        }
        Currencies::amount($unitMinor);
        $unitPrice = Money::decimal($unitMinor);
        $specialRequest = $this->normalizeSpecialRequest($specialRequest);
        $variantName = $variant->display_name;

        return [
            'currency' => $context->currency, 'pricing_revision' => $context->revision,
            'variant' => $variant,
            'unit_price' => $unitPrice,
            'weight' => $variantName,
            'variant_name' => $variantName,
            'options_snapshot' => $optionsSnapshot,
            'special_request' => $specialRequest,
            'customization' => $customization,
            'configuration_signature' => $this->signature(
                $product->id,
                $variant->id,
                collect($optionsSnapshot)->pluck('value_id')->all(),
                $specialRequest,
                $customization
            ),
        ];
    }

    /**
     * @param  array<int, int|string>  $optionValueIds
     */
    public function signature(int $productId, ?int $variantId, array $optionValueIds = [], ?string $specialRequest = null, ?array $customization = null): string
    {
        // Customer details (design files, Pantone codes, colour order) must keep otherwise identical lines apart.
        // The fingerprint leads the text so the core's 255-character cut can never remove it; lines without
        // customization keep their original signature.
        $fingerprint = app(ProductCustomizationService::class)->fingerprint($customization);
        $text = $fingerprint === '' ? $specialRequest : 'c:'.$fingerprint.'|'.ProductConfiguration::normalizeSpecialRequest($specialRequest);

        return ProductConfiguration::signature($productId, $variantId, $optionValueIds, $text);
    }

    protected function resolveVariant(Product $product, ?int $variantId): ProductVariant
    {
        $variants = $product->variants->where('is_active', true)->filter(fn ($v) => app(PricingService::class)->effective('variant', $v->id) !== null)->sortBy(fn ($v) => [$v->sort_order, $v->id])->values();

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
        $selectedIds = $selectedValues->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($product->optionGroups->where('is_active', true) as $group) {
            $count = $grouped->get($group->id, collect())->count();

            // A dependent group (e.g. "Gloves size") only applies while the option it depends on is chosen.
            if ($group->depends_on_value_id !== null && ! in_array((int) $group->depends_on_value_id, $selectedIds, true)) {
                if ($count > 0) {
                    throw ValidationException::withMessages([
                        'option_value_ids' => "{$group->name} is only available with the option it belongs to.",
                    ]);
                }

                continue;
            }

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
