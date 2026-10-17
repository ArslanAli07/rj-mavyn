<x-admin-layout title="Create new product">
    @php
        $legacyCurrency = ($product ?? $coupon ?? $deliveryZone ?? null)?->getRawOriginal('price_currency') ?? app(\Mavyn\Contracts\Pricing\PriceBook::class)->context()->currency;
    @endphp

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Add new product</h1>
            <p class="small text-body-secondary mt-1">Populate details below to upload a product to the catalog.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to products
        </a>
    </div>

    <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="admin-stack-6">
        @csrf
                @include('admin.partials.pricing-context')

        <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">
            
            {{-- Left: Details Form fields --}}
            <div class="admin-span-lg-8 admin-stack-6">
                
                {{-- Basic Information --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Basic information</h3>
                    
                    <div>
                        <label for="title" class="form-label">Product title *</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required class="form-control small">
                        @error('title') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="d-grid admin-cols-1 admin-cols-sm-3 gap-4">
                        <div>
                            <label for="category_id" class="form-label">Category *</label>
                            <select id="category_id" name="category_id" required class="form-select small">
                                <option value="">Select category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            @include('admin.products._inventory')
                        </div>
                        <div>
                            <label for="display_order" class="form-label">Category priority *</label>
                            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" required class="form-control small num">
                            @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>


                </div>

                {{-- Descriptions --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Descriptions</h3>

                    <div>
                        <label for="short_description" class="form-label">Short description (max 500 chars)</label>
                        <textarea id="short_description" name="short_description" rows="2" class="form-control small">{{ old('short_description') }}</textarea>
                        @error('short_description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="form-label">Long description / details</label>
                        <textarea id="description" name="description" rows="5" class="form-control small">{{ old('description') }}</textarea>
                        @error('description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                @include('admin.products._attributes')

                @php
                    $initialVariants = old('variants', [
                        ['id' => null, 'name' => 'Regular', 'price' => '', 'discount_price' => '', 'servings' => '1 Serving', 'is_default' => true, 'is_active' => true],
                        ['id' => null, 'name' => 'Large', 'price' => '', 'discount_price' => '', 'servings' => '1 Large Serving', 'is_default' => false, 'is_active' => true],
                    ]);
                    $initialOptionGroups = old('option_groups', []);
                @endphp
                @include('admin.products._options-editor')

                {{-- Images --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Images</h3>

                    <div>
                        <x-admin.media-picker name="main_image_asset_id" label="Choose main image from library" type="image" :value="old('main_image_asset_id', null)" />
                        @error('main_image_asset_id') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        <p class="small text-body-secondary mt-2">Upload reusable images in the <a href="{{ route('admin.media.index') }}" target="_blank" class="text-decoration-underline">Media library</a>. Clearing the selection restores the legacy image, when available.</p>
                        <label for="main_image" class="form-label">Main feature image (jpeg/png, max 4mb, optional legacy upload)</label>
                        <input type="file" id="main_image" name="main_image" class="form-control small">
                        @error('main_image') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                            <x-admin.media-picker name="gallery_asset_ids" label="Choose gallery images" type="image" :multiple="true" :value="old('gallery_asset_ids', [])" />
                            @error('gallery_asset_ids') <p class="text-danger small">{{ $message }}</p> @enderror
                            @error('gallery_asset_ids.*') <p class="text-danger small">{{ $message }}</p> @enderror
                            <p class="small text-body-secondary">Select up to 30 images and reorder them. Clear the selection to restore the legacy gallery.</p>
                        @endif
                        <label for="gallery_images" class="form-label">Gallery images (multiple, max 4mb each)</label>
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
                        <input type="checkbox" name="is_active" value="1" checked class="form-check-input">
                        <span>Active (Visible on website)</span>
                    </label>

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="in_stock" value="1" checked class="form-check-input">
                        <span>In Stock</span>
                    </label>

                    <hr class="border-light-subtle">

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="is_bestseller" value="1" class="form-check-input">
                        <span>Best Seller</span>
                    </label>

                    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                        <input type="checkbox" name="is_new_arrival" value="1" class="form-check-input">
                        <span>New Arrival</span>
                    </label>
                </div>

                {{-- Occasions Sync --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-3 fw-medium text-body">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Occasions</h3>
                    
                    <div class="admin-stack-2 admin-max-height-48 overflow-y-auto pe-1">
                        @foreach($occasions as $occ)
                            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none">
                                <input type="checkbox" name="occasions[]" value="{{ $occ->id }}" class="form-check-input">
                                <span>{{ $occ->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Action Box --}}
                <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-3">
                    <button type="submit" class="btn btn-primary w-100 justify-content-center py-3 small fw-semibold admin-letter-spacing">
                        Create product
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary w-100 justify-content-center py-2 small d-block text-center">
                        Cancel
                    </a>
                </div>
            </div>

        </div>
    </form>

    @push('scripts')
        @include('admin.products._options-editor-script')
    @endpush

</x-admin-layout>
