<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\AdminMutation;
use App\Services\CatalogSlugService;
use App\Services\InventoryService;
use App\Services\Media\MediaLibraryService;
use App\Services\Pricing\PricingService;
use App\Services\ProductAttributesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class ProductController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function __construct(private MediaLibraryService $mediaLibrary, private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($q2) => $q2->where('title', 'LIKE', "%{$q}%")->orWhere('sku', 'LIKE', "%{$q}%"));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
            $query->orderBy('display_order')->latest();
        } else {
            $query->latest();
        }

        $products = $query->paginate(20)->withQueryString();
        $categories = Category::active()->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $categories = Category::active()->orderBy('display_order')->get();
        $occasions = Occasion::active()->orderBy('display_order')->get();

        return view('admin.products.create', compact('categories', 'occasions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLegacyPricingFromVariants($request);
        $data = $this->validateProduct($request);
        $data['slug'] = Str::slug($data['title']);
        app(CatalogSlugService::class)->validate(Product::class, $data['slug'], 'title');

        DB::transaction(function () use ($request, $data) {
            app(PricingService::class)->lock(true);
            DB::table('extension_runtime')->where('id', 1)->sharedLock()->first();
            if ($request->hasFile('main_image')) {
                $data['main_image'] = $this->storeProductImage($request->file('main_image'), 800);
            }

            $data['is_featured'] = $request->boolean('is_featured');
            $data['allows_design_upload'] = $request->boolean('allows_design_upload');
            $data['is_bestseller'] = $request->boolean('is_bestseller');
            $data['is_new_arrival'] = $request->boolean('is_new_arrival');
            $data['in_stock'] = $request->boolean('in_stock', true);
            $data['is_active'] = $request->boolean('is_active', true);

            $quantity = (int) $data['stock_quantity'];
            unset($data['main_image_asset_id'], $data['gallery_asset_ids'], $data['gallery_asset_ids_present'], $data['stock_quantity'], $data['inventory_mode'], $data['inventory_version']);
            if (isset($data['plugin_attributes'])) {
                $data['plugin_attributes'] = app(ProductAttributesService::class)->merge(null, $data['plugin_attributes']);
            }
            $product = app(CatalogSlugService::class)->create(Product::class, $data, 'title');
            $this->inventory->configureProduct($product, $request->input('inventory_mode', 'untracked'), $quantity, 0, $request->user('admin'));
            if ($request->exists('main_image_asset_id')) {
                $this->mediaLibrary->setProductMainImage($product, $request->integer('main_image_asset_id') ?: null, $request->user('admin'));
            }

            if ($request->exists('gallery_asset_ids_present') || $request->exists('gallery_asset_ids')) {
                $this->mediaLibrary->syncImages($product, 'catalog.product.gallery', $request->input('gallery_asset_ids', []), $request->user('admin'), 'gallery_asset_ids');
            }
            $this->syncVariants($product, $request->input('variants', []));
            $this->syncOptionGroups($product, $request->input('option_groups', []));

            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $i => $file) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image' => $this->storeProductImage($file, 600, true),
                        'sort_order' => $i,
                    ]);
                }
            }

            if ($request->filled('occasions')) {
                $product->occasions()->sync($request->occasions);
            }
        });

        $this->bustHomeCaches();

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully!');
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'occasions', 'variants', 'optionGroups.values']);
        $categories = Category::active()->orderBy('display_order')->get();
        $occasions = Occasion::active()->orderBy('display_order')->get();

        $stockMovements = InventoryMovement::where(function ($query) use ($product) {
            $query->where(fn ($q) => $q->where('stock_type', 'product')->where('stock_id', $product->id))
                ->orWhere(fn ($q) => $q->where('stock_type', 'variant')->whereIn('stock_id', $product->variants->pluck('id')));
        })->with('admin')->latest('id')->limit(20)->get();

        return view('admin.products.edit', compact('product', 'categories', 'occasions', 'stockMovements'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->mergeLegacyPricingFromVariants($request);
        $data = $this->validateProduct($request, $product);

        DB::transaction(function () use ($request, $product, $data) {
            app(PricingService::class)->lock(true);
            $product = $this->inventory->lockProducts([$product->id])->get($product->id);
            $this->inventory->configureProduct($product, $request->input('inventory_mode', $product->inventory_mode), (int) $data['stock_quantity'],
                $request->has('inventory_version') ? $request->integer('inventory_version') : null, $request->user('admin'));
            if ($request->hasFile('main_image')) {
                // Legacy originals may still be referenced by historical orders.
                $data['main_image'] = $this->storeProductImage($request->file('main_image'), 800);
            }

            $data['is_featured'] = $request->boolean('is_featured', $product->is_featured);
            $data['allows_design_upload'] = $request->boolean('allows_design_upload', $product->allows_design_upload);
            $data['is_bestseller'] = $request->boolean('is_bestseller');
            $data['is_new_arrival'] = $request->boolean('is_new_arrival');
            $data['in_stock'] = $request->boolean('in_stock');
            $data['is_active'] = $request->boolean('is_active');

            unset($data['main_image_asset_id'], $data['gallery_asset_ids'], $data['gallery_asset_ids_present'], $data['stock_quantity'], $data['inventory_mode'], $data['inventory_version']);
            if (isset($data['plugin_attributes'])) {
                $data['plugin_attributes'] = app(ProductAttributesService::class)->merge($product, $data['plugin_attributes']);
            }
            $product->update($data);
            if ($request->exists('main_image_asset_id') || $request->hasFile('main_image')) {
                $this->mediaLibrary->setProductMainImage($product, $request->integer('main_image_asset_id') ?: null, $request->user('admin'));
            }

            if ($request->exists('gallery_asset_ids_present') || $request->exists('gallery_asset_ids')) {
                $this->mediaLibrary->syncImages($product, 'catalog.product.gallery', $request->input('gallery_asset_ids', []), $request->user('admin'), 'gallery_asset_ids');
            }
            $this->syncVariants($product, $request->input('variants', []));
            $this->syncOptionGroups($product, $request->input('option_groups', []));

            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $i => $file) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image' => $this->storeProductImage($file, 600, true),
                        'sort_order' => $product->images()->count() + $i,
                    ]);
                }
            }

            $product->occasions()->sync($request->get('occasions', []));
        });

        $this->bustHomeCaches();
        Cache::forget("product_{$product->slug}");
        Cache::forget("related_{$product->category_id}_{$product->id}");

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully!');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteProduct($product);
        $this->bustHomeCaches();

        return back()->with('success', 'Product deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'products', ['activate', 'deactivate', 'delete']);
        $products = Product::with('images')->whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] === 'activate') {
            AdminMutation::update(Product::whereIn('id', $products->pluck('id')), ['is_active' => true]);
        } elseif ($data['bulk_action'] === 'deactivate') {
            AdminMutation::update(Product::whereIn('id', $products->pluck('id')), ['is_active' => false]);
        } else {
            foreach ($products as $product) {
                $this->deleteProduct($product);
            }
        }

        $this->bustHomeCaches();

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $products->count(), 'product'));
    }

    public function deleteImage(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            abort(403);
        }
        $path = $image->image;
        DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        $image->delete();

        return back()->with('success', 'Image deleted.');
    }

    protected function mergeLegacyPricingFromVariants(Request $request): void
    {
        $request->validate(['variants' => 'required|array|list|min:1', 'variants.*' => 'array']);
        if (! $request->has('variants') || ! is_array($request->variants) || count($request->variants) === 0) {
            return;
        }

        $default = collect($request->variants)->firstWhere('is_default', '1')
            ?? collect($request->variants)->firstWhere('is_default', 1)
            ?? collect($request->variants)->firstWhere('is_default', true)
            ?? $request->variants[0];

        $request->merge([
            'price' => isset($default['price']) && $default['price'] !== '' ? $default['price'] : null,
            'discount_price' => isset($default['discount_price']) && $default['discount_price'] !== '' ? $default['discount_price'] : null,
            'weight' => $default['name'] ?? $default['weight'] ?? null,
            ...(app(ProductAttributesService::class)->has('variant', 'servings') && array_key_exists('servings', $default) ? ['servings' => $default['servings'] ?: null] : []),
        ]);
    }

    protected function validateProduct(Request $request, ?Product $product = null): array
    {
        $attributeRules = app(ProductAttributesService::class)->rules($request);
        $data = $request->validate([...$attributeRules,
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'display_order' => 'required|integer|min:0',
            'price_currency' => 'sometimes|in:PKR,USD,EUR,GBP,AUD,CAD,AED,SAR,INR',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:1000',
            'weight' => 'nullable|string|max:50',
            'stock_quantity' => 'required|integer|min:0|max:2147483647',
            'inventory_mode' => 'sometimes|in:untracked,shared,variant',
            'inventory_version' => 'sometimes|integer|min:0',
            'main_image_asset_id' => $this->mediaLibrary->imageRules('main_image'),
            'gallery_asset_ids_present' => 'sometimes|in:1',
            'gallery_asset_ids' => 'sometimes|array|max:30',
            'gallery_asset_ids.*' => [...$this->mediaLibrary->imageRules(), 'required', 'distinct'],
            'main_image' => ['nullable', 'image', 'max:4096', function ($attribute, $value, $fail) use ($request) {
                if ($request->filled('main_image_asset_id')) {
                    $fail('Choose a library image or upload a legacy image, one at a time.');
                }
            }],
            'gallery_images.*' => 'nullable|image|max:4096',
            'is_featured' => 'boolean',
            'allows_design_upload' => 'nullable|boolean',
            'is_bestseller' => 'boolean',
            'is_new_arrival' => 'boolean',
            'in_stock' => 'boolean',
            'is_active' => 'boolean',
            'occasions' => 'nullable|array',
            'occasions.*' => 'exists:occasions,id',

            'variants' => 'required|array|list|min:1',
            'variants.*' => 'array',
            'variants.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')->where(fn ($q) => $product
                    ? $q->where('product_id', $product->id)
                    : $q->whereRaw('1 = 0')),
            ],
            'variants.*.name' => 'required|string|max:50',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.discount_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    preg_match('/variants\.(\d+)\.discount_price/', $attribute, $matches);
                    if (! isset($matches[1])) {
                        return;
                    }
                    $price = $request->input("variants.{$matches[1]}.price");
                    if ($price !== null && $value >= $price) {
                        $fail('The discount price must be less than the regular price.');
                    }
                },
            ],
            'variants.*.sort_order' => 'nullable|integer|min:0',
            'variants.*.is_default' => 'nullable|boolean',
            'variants.*.is_active' => 'nullable|boolean',
            'variants.*.in_stock' => 'sometimes|boolean',
            'variants.*.track_inventory' => 'sometimes|boolean',
            'variants.*.stock_quantity' => 'sometimes|integer|min:0|max:2147483647',
            'variants.*.inventory_version' => 'sometimes|integer|min:0',

            'option_groups' => 'nullable|array|list',
            'option_groups.*' => 'array',
            'option_groups.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('product_option_groups', 'id')->where(fn ($q) => $product
                    ? $q->where('product_id', $product->id)
                    : $q->whereRaw('1 = 0')),
            ],
            'option_groups.*.name' => 'required_with:option_groups|string|max:100',
            'option_groups.*.selection_type' => 'required_with:option_groups|in:single,multiple',
            'option_groups.*.min_selections' => 'nullable|integer|min:0|max:50',
            'option_groups.*.max_selections' => 'nullable|integer|min:1|max:50',
            'option_groups.*.is_required' => 'nullable|boolean',
            'option_groups.*.is_active' => 'nullable|boolean',
            'option_groups.*.sort_order' => 'nullable|integer|min:0',
            'option_groups.*.step' => 'nullable|in:style,extras',
            'option_groups.*.display' => 'nullable|in:select,tiles,swatches',
            'option_groups.*.help_text' => 'nullable|string|max:255',
            'option_groups.*.depends_on_value_id' => 'nullable|integer',
            'option_groups.*.values' => 'required_with:option_groups.*.name|array|list|min:1',
            'option_groups.*.values.*' => 'array',
            'option_groups.*.values.*.id' => [
                'nullable',
                'integer',
                'distinct',
                function ($attribute, $value, $fail) use ($request, $product) {
                    $parts = explode('.', $attribute);
                    $groupId = $request->input('option_groups.'.$parts[1].'.id');
                    if (! $product || ! $groupId || ! ProductOptionValue::whereKey($value)
                        ->where('product_option_group_id', $groupId)
                        ->whereHas('group', fn ($query) => $query->where('product_id', $product->id))->exists()) {
                        $fail('The selected option value does not belong to this product and group.');
                    }
                },
            ],
            'option_groups.*.values.*.name' => 'required|string|max:100',
            'option_groups.*.values.*.price_adjustment' => 'nullable|numeric',
            'option_groups.*.values.*.is_active' => 'nullable|boolean',
            'option_groups.*.values.*.sort_order' => 'nullable|integer|min:0',
            'option_groups.*.values.*.swatch' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'option_groups.*.values.*.input_label' => 'nullable|string|max:120',
        ]);

        $this->assertUniqueVariantNames($request->input('variants', []));
        $this->assertOptionGroupRules($request->input('option_groups', []));

        return $data;
    }

    protected function assertUniqueVariantNames(array $variants): void
    {
        $names = collect($variants)
            ->map(fn ($v) => Str::lower(trim((string) ($v['name'] ?? ''))))
            ->filter();

        if ($names->count() !== $names->unique()->count()) {
            throw ValidationException::withMessages([
                'variants' => 'Variant names must be unique for this product.',
            ]);
        }
    }

    protected function assertOptionGroupRules(array $groups): void
    {
        foreach ($groups as $index => $group) {
            $type = $group['selection_type'] ?? 'multiple';
            $min = (int) ($group['min_selections'] ?? 0);
            $max = isset($group['max_selections']) && $group['max_selections'] !== ''
                ? (int) $group['max_selections']
                : null;
            $required = filter_var($group['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $valueCount = count($group['values'] ?? []);

            if ($required && $min < 1) {
                $min = 1;
            }

            if ($type === 'single') {
                $max = 1;
            }

            if ($max !== null && $min > $max) {
                throw ValidationException::withMessages([
                    "option_groups.{$index}.min_selections" => 'Minimum selections cannot exceed maximum selections.',
                ]);
            }

            if ($max !== null && $max > $valueCount) {
                throw ValidationException::withMessages([
                    "option_groups.{$index}.max_selections" => 'Maximum selections cannot exceed the number of choices.',
                ]);
            }

            $valueNames = collect($group['values'] ?? [])
                ->map(fn ($v) => Str::lower(trim((string) ($v['name'] ?? ''))))
                ->filter();

            if ($valueNames->count() !== $valueNames->unique()->count()) {
                throw ValidationException::withMessages([
                    "option_groups.{$index}.values" => 'Option choice names must be unique within a group.',
                ]);
            }
        }
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];
        $defaultAssigned = false;

        foreach (array_values($variants) as $index => $payload) {
            $name = trim((string) $payload['name']);
            $isDefault = ! $defaultAssigned && filter_var($payload['is_default'] ?? ($index === 0), FILTER_VALIDATE_BOOLEAN);
            if ($isDefault) {
                $defaultAssigned = true;
            }

            $attributes = [
                'price_currency' => $product->getRawOriginal('price_currency'),
                'name' => $name,
                'weight' => $name,
                'price' => $payload['price'],
                'discount_price' => isset($payload['discount_price']) && $payload['discount_price'] !== ''
                    ? $payload['discount_price']
                    : null,
                'sort_order' => $payload['sort_order'] ?? $index,
                'is_default' => $isDefault,
                'is_active' => $this->resolveActiveFlag($payload['is_active'] ?? null),
            ];

            foreach (app(ProductAttributesService::class)->fields('variant') as $entry) {
                $key = $entry['field']->key;
                if ($entry['legacy'] && array_key_exists($key, $payload)) {
                    $attributes[$key] = $payload[$key] === '' ? null : $payload[$key];
                }
            }
            if (! empty($payload['id'])) {
                $variant = $product->variants()->find($payload['id']);
                if (! $variant) {
                    continue;
                }
                if (isset($payload['plugin_attributes'])) {
                    $attributes['plugin_attributes'] = app(ProductAttributesService::class)->merge($variant, $payload['plugin_attributes']);
                }
                $variant->update($attributes);
                $keptIds[] = $variant->id;
            } else {
                if (isset($payload['plugin_attributes'])) {
                    $attributes['plugin_attributes'] = app(ProductAttributesService::class)->merge(null, $payload['plugin_attributes']);
                }
                $variant = $product->variants()->create($attributes);
                $keptIds[] = $variant->id;
            }
            $this->inventory->configureVariant($variant, array_key_exists('track_inventory', $payload)
                ? filter_var($payload['track_inventory'], FILTER_VALIDATE_BOOLEAN) : $variant->track_inventory,
                (int) ($payload['stock_quantity'] ?? $variant->stock_quantity),
                isset($payload['inventory_version']) ? (int) $payload['inventory_version'] : null, auth('admin')->user());
            if (array_key_exists('in_stock', $payload)) {
                $variant->update(['in_stock' => filter_var($payload['in_stock'], FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        if (! $defaultAssigned && ! empty($keptIds)) {
            AdminMutation::update(ProductVariant::where('id', $keptIds[0]), ['is_default' => true]);
        }

        foreach ($product->variants()->whereNotIn('id', $keptIds)->get() as $removed) {
            $removed->delete();
        }
    }

    protected function syncOptionGroups(Product $product, array $groups): void
    {
        $keptGroupIds = [];

        foreach (array_values($groups) as $groupIndex => $payload) {
            $selectionType = $payload['selection_type'] ?? 'multiple';
            $isRequired = filter_var($payload['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $min = (int) ($payload['min_selections'] ?? 0);
            if ($isRequired) {
                $min = max(1, $min);
            }
            $max = isset($payload['max_selections']) && $payload['max_selections'] !== ''
                ? (int) $payload['max_selections']
                : ($selectionType === 'single' ? 1 : null);
            if ($selectionType === 'single') {
                $max = 1;
            }

            $groupAttributes = [
                'name' => trim((string) $payload['name']),
                'selection_type' => $selectionType,
                'min_selections' => $min,
                'max_selections' => $max,
                'is_required' => $isRequired || $min > 0,
                'is_active' => $this->resolveActiveFlag($payload['is_active'] ?? null),
                'sort_order' => $payload['sort_order'] ?? $groupIndex,
            ];
            // Builder fields are written only when the form sent them, so older forms never wipe them.
            if (array_key_exists('step', $payload)) {
                $groupAttributes['step'] = $payload['step'] ?: null;
            }
            if (array_key_exists('display', $payload)) {
                $groupAttributes['display'] = $payload['display'] ?: 'select';
            }
            if (array_key_exists('help_text', $payload)) {
                $groupAttributes['help_text'] = trim((string) $payload['help_text']) ?: null;
            }
            if (array_key_exists('depends_on_value_id', $payload)) {
                $dependsOn = (int) $payload['depends_on_value_id'] ?: null;
                // Only a choice of one of this product's other groups can be a condition.
                $valid = $dependsOn && ProductOptionValue::whereKey($dependsOn)->whereHas('group', fn ($q) => $q->where('product_id', $product->id)
                    ->when(! empty($payload['id']), fn ($q) => $q->where('id', '!=', $payload['id'])))->exists();
                $groupAttributes['depends_on_value_id'] = $valid ? $dependsOn : null;
            }

            if (! empty($payload['id'])) {
                $group = $product->optionGroups()->find($payload['id']);
                if (! $group) {
                    continue;
                }
                $group->update($groupAttributes);
            } else {
                $group = $product->optionGroups()->create($groupAttributes);
            }

            $keptGroupIds[] = $group->id;
            $keptValueIds = [];

            foreach (array_values($payload['values'] ?? []) as $valueIndex => $valuePayload) {
                $valueAttributes = [
                    'price_currency' => $product->getRawOriginal('price_currency'),
                    'name' => trim((string) $valuePayload['name']),
                    'price_adjustment' => isset($valuePayload['price_adjustment']) && $valuePayload['price_adjustment'] !== ''
                        ? $valuePayload['price_adjustment']
                        : 0,
                    'is_active' => $this->resolveActiveFlag($valuePayload['is_active'] ?? null),
                    'sort_order' => $valuePayload['sort_order'] ?? $valueIndex,
                ];
                if (array_key_exists('swatch', $valuePayload)) {
                    $valueAttributes['swatch'] = strtolower((string) $valuePayload['swatch']) ?: null;
                }
                if (array_key_exists('input_label', $valuePayload)) {
                    $valueAttributes['input_label'] = trim((string) $valuePayload['input_label']) ?: null;
                }

                if (! empty($valuePayload['id'])) {
                    $value = ProductOptionValue::where('product_option_group_id', $group->id)
                        ->find($valuePayload['id']);
                    if (! $value) {
                        continue;
                    }
                    $value->update($valueAttributes);
                    $keptValueIds[] = $value->id;
                } else {
                    $value = $group->values()->create($valueAttributes);
                    $keptValueIds[] = $value->id;
                }
            }

            AdminMutation::delete($group->values()->whereNotIn('id', $keptValueIds)->getQuery());
        }

        AdminMutation::delete($product->optionGroups()->whereNotIn('id', $keptGroupIds)->getQuery());
    }

    protected function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            app(PricingService::class)->lock(true);
            $this->inventory->lockProducts([$product->id]);
            $this->inventory->assertCanDeleteProduct($product);
            $product->delete();
            // Legacy originals may still be historical/external references; preserve them.
        });
        Cache::forget("product_{$product->slug}");
        Cache::forget("related_{$product->category_id}_{$product->id}");
    }

    protected function storeProductImage($file, int $size, bool $gallery = false): string
    {
        $filename = 'products/'.Str::uuid().($gallery ? '_gallery' : '').'.webp';
        $img = Image::decode($file)
            ->cover($size, $size)
            ->encodeUsingFormat(Format::WEBP, quality: 85);
        Storage::disk('public')->put($filename, (string) $img);

        return $filename;
    }

    /**
     * Treat missing/blank dynamic form flags as active (DB default),
     * while still honoring explicit 0/false/"0".
     */
    protected function resolveActiveFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function bustHomeCaches(): void
    {
        Cache::forget('home_featured');
        Cache::forget('home_bestsellers');
        Cache::forget('home_new_arrivals');
        Cache::forget('home_categories');
    }
}
