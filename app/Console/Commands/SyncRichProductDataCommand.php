<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\Catalog\RjLeatherCatalogReader;
use App\Services\Catalog\SizeChartParser;
use App\Services\Pricing\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncRichProductDataCommand extends Command
{
    protected $signature = 'catalog:sync-rich-data
                            {--source=/home/arslan/Downloads/Products-cleaned : Path to supplier export}
                            {--sku= : Sync single product by SKU}';

    protected $description = 'Sync multi-table size charts, custom sizing, and lining options from the supplier catalog';

    public function handle(SizeChartParser $parser): int
    {
        $source = (string) $this->option('source');
        $targetSku = $this->option('sku') ? strtoupper(trim((string) $this->option('sku'))) : null;

        $reader = new RjLeatherCatalogReader($source);
        if (! $reader->exists()) {
            $this->error("Supplier catalog directory not found at: {$source}");
            return self::FAILURE;
        }

        $this->info("Scanning catalog at {$source}...");

        $productsUpdated = 0;
        $variantsAdded = 0;
        $optionsAdded = 0;

        foreach ($reader->products() as $entry) {
            $code = (string) ($entry['data']['code'] ?? $entry['code']);
            if ($targetSku && strtoupper($code) !== $targetSku) {
                continue;
            }

            $product = Product::withoutEvents(fn () => Product::where('sku', $code)->first());
            if (! $product) {
                continue;
            }

            $rawChart = $entry['data']['size_chart'] ?? null;
            $parsedChart = $parser->parse($rawChart);

            // 1. Update Product size_chart with full multi-table data
            if ($parsedChart !== null) {
                $currentChart = $product->size_chart ?? [];
                $updatedChart = [
                    'name' => $parsedChart['name'] ?? ($currentChart['name'] ?? null),
                    'diagram' => $parsedChart['diagram'] ?? ($currentChart['diagram'] ?? null),
                    'how_to_measure' => $parsedChart['how_to_measure'] ?? ($currentChart['how_to_measure'] ?? null),
                    'columns' => $parsedChart['columns'],
                    'rows' => $parsedChart['rows'],
                    'tables' => $parsedChart['tables'],
                    'sizes' => $parsedChart['sizes'],
                    'grouped_sizes' => $parsedChart['grouped_sizes'],
                ];

                $product->size_chart = $updatedChart;
                $product->saveQuietly();
                $productsUpdated++;
            }

            // 2. Sync Custom Size & any missing gender variants
            $existingVariants = $product->variants()->get()->keyBy('name');
            $customSizeLabel = 'Custom Size (+ $95.00)';

            // Check if product is outerwear/apparel that supports custom sizing
            $categoryName = strtolower($product->category?->name ?? '');
            $parentCategoryName = strtolower($product->category?->parent?->name ?? '');
            $titleLower = strtolower($product->title);
            $isApparel = ! (str_contains($categoryName, 'shoe') || str_contains($categoryName, 'boot') || str_contains($categoryName, 'glove') || str_contains($categoryName, 'bag') || str_contains($categoryName, 'chappal'));

            if ($isApparel && ! $existingVariants->has($customSizeLabel)) {
                $customPrice = number_format((float) $product->price + 95.00, 2, '.', '');
                $customDiscount = $product->discount_price !== null ? number_format((float) $product->discount_price + 95.00, 2, '.', '') : null;

                ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $customSizeLabel,
                    'weight' => 'Custom Size',
                    'price' => $customPrice,
                    'discount_price' => $customDiscount,
                    'price_currency' => $product->price_currency ?: 'USD',
                    'sort_order' => 0,
                    'is_default' => false,
                    'is_active' => true,
                    'in_stock' => true,
                    'track_inventory' => false,
                ]);
                $variantsAdded++;
            }

            // Also ensure all parsed sizes exist as variants
            if ($parsedChart !== null && ! empty($parsedChart['sizes'])) {
                foreach ($parsedChart['sizes'] as $idx => $sizeLabel) {
                    if (! $existingVariants->has($sizeLabel)) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $sizeLabel,
                            'weight' => $sizeLabel,
                            'price' => number_format((float) $product->price, 2, '.', ''),
                            'discount_price' => $product->discount_price !== null ? number_format((float) $product->discount_price, 2, '.', '') : null,
                            'price_currency' => $product->price_currency ?: 'USD',
                            'sort_order' => $idx + 1,
                            'is_default' => false,
                            'is_active' => true,
                            'in_stock' => true,
                            'track_inventory' => false,
                        ]);
                        $variantsAdded++;
                    }
                }
            }

            // 3. Attach Lining Options if product is outerwear/suit/vest/coat
            $isLiningApplicable = str_contains($titleLower, 'jacket')
                || str_contains($titleLower, 'coat')
                || str_contains($titleLower, 'suit')
                || str_contains($titleLower, 'vest')
                || str_contains($categoryName, 'jacket')
                || str_contains($categoryName, 'coat')
                || str_contains($categoryName, 'suit')
                || str_contains($categoryName, 'vest')
                || str_contains($parentCategoryName, 'jacket')
                || str_contains($parentCategoryName, 'coat');

            if ($isLiningApplicable) {
                $hasLiningGroup = ProductOptionGroup::where('product_id', $product->id)
                    ->where('name', 'Lining Options')
                    ->exists();

                if (! $hasLiningGroup) {
                    $liningGroup = ProductOptionGroup::create([
                        'product_id' => $product->id,
                        'name' => 'Lining Options',
                        'selection_type' => 'single',
                        'is_required' => false,
                        'is_active' => true,
                        'sort_order' => 1,
                    ]);

                    ProductOptionValue::create([
                        'product_option_group_id' => $liningGroup->id,
                        'name' => 'Regular Lining (100% Viscose)',
                        'price_adjustment' => '0.00',
                        'price_currency' => $product->price_currency ?: 'USD',
                        'is_active' => true,
                        'sort_order' => 1,
                    ]);

                    ProductOptionValue::create([
                        'product_option_group_id' => $liningGroup->id,
                        'name' => 'No Lining',
                        'price_adjustment' => '0.00',
                        'price_currency' => $product->price_currency ?: 'USD',
                        'is_active' => true,
                        'sort_order' => 2,
                    ]);

                    ProductOptionValue::create([
                        'product_option_group_id' => $liningGroup->id,
                        'name' => 'Sublimated Print Lining',
                        'price_adjustment' => '0.00',
                        'price_currency' => $product->price_currency ?: 'USD',
                        'is_active' => true,
                        'sort_order' => 3,
                    ]);

                    $optionsAdded++;
                }
            }
        }

        app(PricingService::class)->bump();
        Cache::flush();

        $this->info("Completed sync!");
        $this->line("- Products updated with rich size charts: {$productsUpdated}");
        $this->line("- Variants added (Custom Size / gender sizes): {$variantsAdded}");
        $this->line("- Lining Option groups created: {$optionsAdded}");

        return self::SUCCESS;
    }
}

