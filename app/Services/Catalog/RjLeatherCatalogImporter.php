<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mavyn\Core\Currencies;

/**
 * Loads the supplier export into the storefront catalogue.
 *
 * The importer is idempotent: categories are matched by slug or by name inside
 * their parent, products are matched by their supplier code and sizes are
 * matched by their label. Running it again refreshes the catalogue instead of
 * duplicating it, so it is safe to use as a deployment step.
 */
final class RjLeatherCatalogImporter
{
    public function __construct(
        private readonly SizeChartParser $sizes,
        private readonly ProductDescriptionFormatter $formatter,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function import(string $sourceRoot, array $options = []): array
    {
        $copyImages = (bool) ($options['copy_images'] ?? true);
        $currency = (string) ($options['currency'] ?? 'USD');

        $reader = new RjLeatherCatalogReader($sourceRoot);
        if (! $reader->exists()) {
            throw new \RuntimeException('The RJ Leather catalogue export was not found at '.$sourceRoot.'.');
        }

        $this->switchStoreCurrency($currency);

        $summary = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_skipped' => 0,
            'variants_created' => 0,
            'images_copied' => 0,
            'skipped_codes' => [],
        ];

        $categories = $this->syncCategories($reader->categories(), $summary);

        foreach ($reader->products() as $entry) {
            $result = $this->importProduct($entry, $categories, $currency, $copyImages, $summary);
            if ($result === false) {
                $summary['products_skipped']++;
                $summary['skipped_codes'][] = $entry['code'];
            }
        }

        app(PricingService::class)->bump();
        Cache::forget('active_categories');
        Cache::forget('nav_categories');
        Cache::forget('home_categories');

        return $summary;
    }

    /**
     * Denominate the store in the currency the supplier quoted.
     *
     * The catalogue ships USD prices, so the selling currency follows. Prices
     * are always read from the currency price books, so the switch only needs
     * to move the runtime context and record why.
     */
    public function switchStoreCurrency(string $currency): void
    {
        Currencies::symbol($currency);

        DB::transaction(function () use ($currency) {
            foreach (['store_currency' => $currency, 'store_currency_symbol' => Currencies::symbol($currency)] as $key => $value) {
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'type' => 'text', 'group' => 'store']);
            }
            DB::table('currency_runtime')->where('id', 1)->update([
                'currency' => $currency,
                'enabled' => json_encode([$currency], JSON_THROW_ON_ERROR),
            ]);
            foreach (['product', 'variant', 'option', 'zone', 'coupon'] as $source) {
                DB::table('price_provenance')->where('source', $source)->update([
                    'currency' => $currency,
                    'evidence' => 'The RJ Leather supplier catalogue is quoted in '.$currency.'; the store sells in the same currency.',
                    'resolved_at' => now(),
                ]);
            }
        });

        app(PricingService::class)->bump();
        Cache::forget('all_settings');
    }

    /**
     * @param  array<int, array<int, string>>  $tree
     */
    private function syncCategories(array $tree, array &$summary): array
    {
        $map = [];

        $walk = function (array $nodes, ?int $parentId, int $sortBase) use (&$walk, &$map, &$summary): void {
            $index = 0;
            foreach ($nodes as $node) {
                $position = $sortBase + ($index + 1);
                $category = $this->upsertCategory($node, $parentId, $position, $summary);
                $map[implode('/', $node['path'])] = $category;
                $walk($node['children'], $category->id, $position * 100);
                $index++;
            }
        };

        $walk($tree, null, 0);

        return $map;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, int|string>  $summary
     */
    private function upsertCategory(array $node, ?int $parentId, int $displayOrder, array &$summary): Category
    {
        $slug = $this->categorySlug($node['path']);

        $category = Category::withoutEvents(fn () => Category::where('slug', $slug)->first());
        if (! $category) {
            $category = Category::withoutEvents(fn () => Category::where('name', $node['name'])
                ->where(fn ($query) => $query->where('parent_id', $parentId)->orWhereNull('parent_id'))
                ->first());
        }

        $attributes = [
            'name' => $node['name'],
            'parent_id' => $parentId,
            'description' => $this->categoryDescription($node),
            'display_order' => $displayOrder,
            'is_active' => true,
            // Groups lead the navigation and the landing page; the supplier's
            // unfiled folder stays out of the menus but keeps its products.
            'show_on_homepage' => $parentId === null && strtolower($node['name']) !== 'uncategorized',
        ];

        if ($category) {
            $category->forceFill($attributes)->saveQuietly();
            $summary['categories_updated']++;

            return $category;
        }

        $summary['categories_created']++;

        return Category::withoutEvents(fn () => Category::create($attributes + [
            'slug' => $this->uniqueCategorySlug($slug),
        ]));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function categoryDescription(array $node): string
    {
        $leaf = (string) end($node['path']);

        return 'Handmade leather '.$leaf.' from the RJ Leather workshop — cut, stitched and finished in small runs.';
    }

    /**
     * @param  array<int, string>  $path
     */
    private function categorySlug(array $path): string
    {
        return collect($path)->map(fn (string $segment) => Str::slug($segment))->filter()->implode('-');
    }

    private function uniqueCategorySlug(string $slug): string
    {
        $candidate = $slug;
        $suffix = 1;
        while (Category::withoutEvents(fn () => Category::where('slug', $candidate)->exists())) {
            $candidate = $slug.'-'.(++$suffix);
        }

        return $candidate;
    }

    /**
     * @param  array{code: string, dir: string, path: array<int, string>, data: array<string, mixed>}  $entry
     * @param  array<string, Category>  $categoryMap
     * @param  array<string, int|string>  $summary
     * @return bool False when the product was skipped.
     */
    private function importProduct(array $entry, array $categoryMap, string $currency, bool $copyImages, array &$summary): bool
    {
        $data = $entry['data'];
        $code = (string) ($data['code'] ?? $entry['code']);
        $price = $data['price'] ?? null;

        // The supplier export ships a handful of broken rows without a price.
        if (! is_numeric($price) || (float) $price <= 0) {
            return false;
        }

        $category = $categoryMap[implode('/', $entry['path'])] ?? null;
        if (! $category) {
            return false;
        }

        $title = trim((string) ($data['title'] ?? $code));
        $discount = is_numeric($data['discounted_price'] ?? null) ? (float) $data['discounted_price'] : null;
        if ($discount !== null && $discount >= (float) $price) {
            $discount = null;
        }

        $content = $this->formatter->format($data['desc'] ?? null, $title);
        $chart = $this->sizes->parse($data['size_chart'] ?? null);
        $sizes = $chart['sizes'] ?? [];
        if ($sizes === []) {
            $sizes = ['Standard'];
        }

        $existing = Product::withoutEvents(fn () => Product::where('sku', $code)->first());

        $attributes = [
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $existing?->slug ?? $this->uniqueProductSlug($title, $code),
            'short_description' => $content['short'],
            'description' => $content['html'],
            'specifications' => $content['specifications'],
            'size_chart' => $chart === null ? null : [
                'name' => $chart['name'],
                'diagram' => $chart['diagram'],
                'how_to_measure' => $chart['how_to_measure'],
                'columns' => $chart['columns'],
                'rows' => $chart['rows'],
            ],
            'price' => number_format((float) $price, 2, '.', ''),
            'discount_price' => $discount === null ? null : number_format($discount, 2, '.', ''),
            'price_currency' => $currency,
            'in_stock' => true,
            'is_active' => true,
            'inventory_mode' => 'untracked',
            'meta_title' => Str::limit($title, 70, ''),
            'meta_description' => Str::limit($content['short'], 160, ''),
            'meta_keywords' => collect([$category->name, 'leather', 'handmade', 'RJ Leather'])
                ->filter()
                ->implode(', '),
        ];

        if ($existing) {
            $existing->forceFill($attributes)->saveQuietly();
            $product = $existing;
            $summary['products_updated']++;
        } else {
            $product = Product::withoutEvents(fn () => Product::create($attributes + ['sku' => $code]));
            $summary['products_created']++;
        }

        $this->syncVariants($product, $sizes, (float) $price, $discount, $currency, $summary);
        $this->syncImages($product, $entry['dir'], $code, $copyImages, $summary);

        return true;
    }

    /**
     * @param  array<int, string>  $sizes
     * @param  array<string, int|string>  $summary
     */
    private function syncVariants(Product $product, array $sizes, float $price, ?float $discount, string $currency, array &$summary): void
    {
        $amount = Money::minor(number_format($price, 2, '.', ''));
        $sale = $discount === null ? null : Money::minor(number_format($discount, 2, '.', ''));

        $existing = $product->variants()->get()->keyBy('name');

        $created = [];
        foreach ($sizes as $index => $label) {
            if ($existing->has($label)) {
                continue;
            }
            $created[] = [
                'product_id' => $product->id,
                'name' => $label,
                'weight' => $label,
                'price' => number_format($price, 2, '.', ''),
                'discount_price' => $discount === null ? null : number_format($discount, 2, '.', ''),
                'servings' => null,
                'price_currency' => $currency,
                'sort_order' => $index,
                'is_default' => $index === 0 && $existing->isEmpty(),
                'is_active' => true,
                'track_inventory' => false,
                'in_stock' => true,
                'stock_quantity' => 0,
                'reserved_quantity' => 0,
                'inventory_version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($created !== []) {
            DB::table('product_variants')->insert($created);
            $ids = DB::table('product_variants')->where('product_id', $product->id)->orderBy('id')->pluck('id');
            $prices = [];
            foreach ($ids as $id) {
                $prices[] = [
                    'kind' => 'variant',
                    'entity_id' => $id,
                    'currency' => $currency,
                    'amount_minor' => $amount,
                    'sale_minor' => $sale,
                ];
            }
            DB::table('currency_prices')->insert($prices);
            $summary['variants_created'] += count($created);
        }

        // Every size carries the same price, so refresh the ones that already existed.
        foreach ($existing as $variant) {
            $variant->forceFill([
                'price' => number_format($price, 2, '.', ''),
                'discount_price' => $discount === null ? null : number_format($discount, 2, '.', ''),
                'price_currency' => $currency,
            ])->saveQuietly();
        }
    }

    /**
     * @param  array<string, int|string>  $summary
     */
    private function syncImages(Product $product, string $dir, string $code, bool $copyImages, array &$summary): void
    {
        $files = glob($dir.'/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [];
        sort($files);
        if ($files === []) {
            return;
        }

        $target = 'images/products/'.$code;
        $destination = public_path($target);

        if ($copyImages) {
            if (! is_dir($destination)) {
                @mkdir($destination, 0755, true);
            }
            foreach ($files as $file) {
                $name = basename($file);
                $path = $destination.'/'.$name;
                if (! is_file($path) || filesize($path) !== filesize($file)) {
                    copy($file, $path);
                    $summary['images_copied']++;
                }
            }
        }

        $main = basename($files[0]);
        foreach ($files as $file) {
            if (str_starts_with(basename($file), 'feature')) {
                $main = basename($file);
                break;
            }
        }

        $product->forceFill(['main_image' => $target.'/'.$main])->saveQuietly();

        $known = $product->images()->pluck('image')->all();
        $order = count($known);
        foreach ($files as $file) {
            $path = $target.'/'.basename($file);
            if (in_array($path, $known, true)) {
                continue;
            }
            ProductImage::withoutEvents(fn () => ProductImage::create([
                'product_id' => $product->id,
                'image' => $path,
                'sort_order' => $order++,
            ]));
        }
    }

    private function uniqueProductSlug(string $title, string $code): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = Str::slug($code);
        }
        $candidate = $base;
        $suffix = 1;
        while (Product::withoutEvents(fn () => Product::where('slug', $candidate)->exists())) {
            $candidate = $base.'-'.Str::slug($code).($suffix > 1 ? '-'.$suffix : '');
            $suffix++;
        }

        return $candidate;
    }
}
