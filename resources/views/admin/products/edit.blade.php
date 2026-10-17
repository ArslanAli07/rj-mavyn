<x-admin-layout title="Edit product — {{ $product->title }}">
    @php
        $legacyCurrency = ($product ?? $coupon ?? $deliveryZone ?? null)?->getRawOriginal('price_currency') ?? app(\Mavyn\Contracts\Pricing\PriceBook::class)->context()->currency;
    @endphp

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Edit product: {{ $product->title }}</h1>
            <p class="small text-body-secondary mt-1">Modify the specifications, prices, or gallery of this product.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to products
        </a>
    </div>

    <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="admin-stack-6">
        @csrf
                @include('admin.partials.pricing-context')
        @method('PUT')

        <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">
            
            {{-- Left: Details Form fields --}}
            <div class="admin-span-lg-8 admin-stack-6">
                
                {{-- Basic Information --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Basic information</h3>
                    
                    <div>
                        <label for="title" class="form-label">Product title *</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $product->title) }}" required class="form-control small">
                        @error('title') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="d-grid admin-cols-1 admin-cols-sm-3 gap-4">
                        <div>
                            <label for="category_id" class="form-label">Category *</label>
                            <select id="category_id" name="category_id" required class="form-select small">
                                <option value="">Select category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            @include('admin.products._inventory')
                        </div>
                        <div>
                            <label for="display_order" class="form-label">Category priority *</label>
                            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $product->display_order) }}" required class="form-control small num">
                            @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>


                </div>

                {{-- Descriptions --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Descriptions</h3>

                    <div>
                        <label for="short_description" class="form-label">Short description (max 500 chars)</label>
                        <textarea id="short_description" name="short_description" rows="2" class="form-control small">{{ old('short_description', $product->short_description) }}</textarea>
                        @error('short_description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="form-label">Long description / details</label>
                        <textarea id="description" name="description" rows="5" class="form-control small">{{ old('description', $product->description) }}</textarea>
                        @error('description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                @include('admin.products._attributes')

                @php
                    $initialVariants = old('variants', $product->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name ?: $v->weight,
                        'price' => (float) $v->price,
                        'discount_price' => $v->discount_price ? (float) $v->discount_price : '',
                        'servings' => $v->servings ?? '',
                        'plugin_attributes' => $v->plugin_attributes ?? [],
                        'is_default' => (bool) $v->is_default,
                        'is_active' => (bool) $v->is_active,
                        'in_stock' => (bool) $v->in_stock,
                        'track_inventory' => (bool) $v->track_inventory,
                        'stock_quantity' => $v->stock_quantity,
                        'reserved_quantity' => $v->reserved_quantity,
                        'inventory_version' => $v->inventory_version,
                    ])->values()->all());

                    $initialOptionGroups = old('option_groups', $product->optionGroups->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                        'selection_type' => $g->selection_type,
                        'min_selections' => $g->min_selections,
                        'max_selections' => $g->max_selections,
                        'is_required' => (bool) $g->is_required,
                        'is_active' => (bool) $g->is_active,
                        'values' => $g->values->map(fn ($v) => [
                            'id' => $v->id,
                            'name' => $v->name,
                            'price_adjustment' => (float) $v->price_adjustment,
                            'is_active' => (bool) $v->is_active,
                        'in_stock' => (bool) $v->in_stock,
                        'track_inventory' => (bool) $v->track_inventory,
                        'stock_quantity' => $v->stock_quantity,
                        'reserved_quantity' => $v->reserved_quantity,
                        'inventory_version' => $v->inventory_version,
                        ])->values()->all(),
                    ])->values()->all());
                @endphp
                @include('admin.products._options-editor')
                @include('admin.products._stock-history')

                {{-- Images --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-6">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Images</h3>

                    {{-- Main Image --}}
                    <div class="d-grid admin-cols-1 admin-cols-sm-4 gap-4 align-items-center">
                        <div class="admin-span-sm-1 admin-aspect-square rounded-2 overflow-hidden border bg-body-tertiary">
                            <img src="{{ $product->main_image_url }}" alt="{{ $product->title }}" class="w-100 h-100 object-fit-cover">
                        </div>
                        <div class="admin-span-sm-3">
                            <x-admin.media-picker name="main_image_asset_id" label="Choose main image from library" type="image" :value="old('main_image_asset_id', $product->main_image_asset_id)" />
                        @error('main_image_asset_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        <p class="small text-body-secondary mt-2">Upload reusable images in the <a href="{{ route('admin.media.index') }}" target="_blank" class="text-decoration-underline">Media library</a>. Clearing the selection restores the legacy image, when available.</p>
                        <label for="main_image" class="form-label">Update main feature image</label>
                            <input type="file" id="main_image" name="main_image" class="form-control small">
                            @error('main_image') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <hr class="border-light-subtle">

                    {{-- Gallery Images Manager --}}
                    <div>
                        <h4 class="text-body admin-letter-spacing mb-3 admin-section-title">Secondary gallery</h4>
                        
                        @if($product->images->isNotEmpty())
                            <div class="d-grid admin-cols-2 admin-cols-sm-4 gap-4 mb-3">
                                @foreach($product->images as $img)
                                    <div class="position-relative admin-aspect-square rounded-2 overflow-hidden border border-light-subtle">
                                        <img src="{{ $img->image_url }}" alt="Gallery Item {{ $loop->iteration }}" class="w-100 h-100 object-fit-cover">
                                        {{-- Delete hover overlay --}}
                                        <div class="position-absolute top-0 bottom-0 start-0 end-0 admin-image-actions d-flex align-items-center justify-content-center">
                                            <button type="button" 
                                                    onclick="if(confirm('Delete this gallery image permanently?')) { document.getElementById('delete-image-{{ $img->id }}').submit(); }"
                                                    class="px-3 py-2 bg-danger text-white rounded-2 small fw-semibold shadow-sm">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="small text-body-secondary fst-italic mb-3">No gallery images uploaded yet.</p>
                        @endif

                        @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                            <x-admin.media-picker name="gallery_asset_ids" label="Choose gallery images" type="image" :multiple="true" :value="old('gallery_asset_ids', old('gallery_asset_ids_present') ? [] : $product->gallery_asset_ids)" />
                            @error('gallery_asset_ids') <p class="text-danger small">{{ $message }}</p> @enderror
                            @error('gallery_asset_ids.*') <p class="text-danger small">{{ $message }}</p> @enderror
                            <p class="small text-body-secondary">Select up to 30 images and reorder them. Clear the selection to restore the legacy gallery.</p>
                        @endif
                        <label for="gallery_images" class="form-label">Upload new gallery images</label>
                        <input type="file" id="gallery_images" name="gallery_images[]" multiple class="form-control small">
                        @error('gallery_images.*') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                @include('admin.products._seo-fields')

            </div>

            {{-- Right: Flags, Attributes & Actions --}}
            <div class="admin-span-lg-4 admin-stack-6">
                
                {{-- Visibility & Badges --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4 fw-semibold text-body">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Settings & badges</h3>

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="is_active" value="1" {{ $product->is_active ? 'checked' : '' }} class="form-check-input">
                        <span>Active (Visible on website)</span>
                    </label>

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="in_stock" value="1" {{ $product->in_stock ? 'checked' : '' }} class="form-check-input">
                        <span>In Stock</span>
                    </label>

                    <hr class="border-light-subtle">

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="is_bestseller" value="1" {{ $product->is_bestseller ? 'checked' : '' }} class="form-check-input">
                        <span>Best Seller</span>
                    </label>

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="is_new_arrival" value="1" {{ $product->is_new_arrival ? 'checked' : '' }} class="form-check-input">
                        <span>New Arrival</span>
                    </label>
                </div>

                {{-- Occasions Sync --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-3 fw-medium text-body">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Occasions</h3>
                    
                    <div class="admin-stack-2 admin-max-height-48 overflow-y-auto pe-1">
                        @php $currentOccs = $product->occasions->pluck('id')->toArray(); @endphp
                        @foreach($occasions as $occ)
                            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                                <input type="checkbox" name="occasions[]" value="{{ $occ->id }}"
                                       {{ in_array($occ->id, $currentOccs) ? 'checked' : '' }}
                                       class="form-check-input">
                                <span>{{ $occ->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Action Box --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-3">
                    <button type="submit" class="btn btn-primary w-100 justify-content-center py-3 small fw-semibold admin-letter-spacing">
                        Save changes
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary w-100 justify-content-center py-2 small d-block text-center">
                        Cancel
                    </a>
                </div>
            </div>

        </div>
    </form>

    {{-- Hidden Delete Gallery Image Forms --}}
    @foreach($product->images as $img)
        <form id="delete-image-{{ $img->id }}" action="{{ route('admin.products.images.delete', [$product->id, $img->id]) }}" method="POST" class="d-none">
            @csrf @method('DELETE')
        </form>
    @endforeach

    @push('scripts')
        @include('admin.products._options-editor-script')
    @endpush

</x-admin-layout>
