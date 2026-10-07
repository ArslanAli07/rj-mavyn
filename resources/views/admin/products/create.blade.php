<x-admin-layout title="Create New Product">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Add New Product</h1>
            <p class="text-xs text-espresso-500 mt-1">Populate details below to upload a product to the catalog.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Products
        </a>
    </div>

    <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {{-- Left: Details Form fields --}}
            <div class="lg:col-span-8 space-y-6">
                
                {{-- Basic Information --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Basic Information</h3>
                    
                    <div>
                        <label for="title" class="label-luxury">Product Title *</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required class="input-luxury text-sm">
                        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="category_id" class="label-luxury">Category *</label>
                            <select id="category_id" name="category_id" required class="input-luxury text-sm">
                                <option value="">Select Category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="stock_quantity" class="label-luxury">Stock Quantity *</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', 10) }}" required class="input-luxury text-sm">
                            @error('stock_quantity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="display_order" class="label-luxury">Category Priority *</label>
                            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" required class="input-luxury text-sm">
                            @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>


                </div>

                {{-- Descriptions --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Descriptions</h3>

                    <div>
                        <label for="short_description" class="label-luxury">Short Description (Max 500 chars)</label>
                        <textarea id="short_description" name="short_description" rows="2" class="input-luxury text-sm">{{ old('short_description') }}</textarea>
                        @error('short_description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="label-luxury">Long Description / Details</label>
                        <textarea id="description" name="description" rows="5" class="input-luxury text-sm">{{ old('description') }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Specifications & Ingredients --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Specifications</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="ingredients" class="label-luxury">Ingredients Listing</label>
                            <textarea id="ingredients" name="ingredients" rows="2" class="input-luxury text-sm">{{ old('ingredients') }}</textarea>
                            @error('ingredients') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="allergens" class="label-luxury">Allergens (e.g. Nuts, Dairy)</label>
                            <textarea id="allergens" name="allergens" rows="2" class="input-luxury text-sm">{{ old('allergens') }}</textarea>
                            @error('allergens') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                @php
                    $initialVariants = old('variants', [
                        ['id' => null, 'name' => 'Regular', 'price' => '', 'discount_price' => '', 'servings' => '1 Serving', 'is_default' => true, 'is_active' => true],
                        ['id' => null, 'name' => 'Large', 'price' => '', 'discount_price' => '', 'servings' => '1 Large Serving', 'is_default' => false, 'is_active' => true],
                    ]);
                    $initialOptionGroups = old('option_groups', []);
                @endphp
                @include('admin.products._options-editor')

                {{-- Images --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Images</h3>

                    <div>
                        <x-admin.media-picker name="main_image_asset_id" label="Choose main image from library" type="image" :value="old('main_image_asset_id', null)" />
                        @error('main_image_asset_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-espresso-500 mt-2">Upload reusable images in the <a href="{{ route('admin.media.index') }}" target="_blank" class="underline">media library</a>. Clearing the selection restores the legacy image, when available.</p>
                        <label for="main_image" class="label-luxury">Main Feature Image (JPEG/PNG, Max 4MB, optional legacy upload)</label>
                        <input type="file" id="main_image" name="main_image" class="input-luxury text-sm">
                        @error('main_image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                            <x-admin.media-picker name="gallery_asset_ids" label="Choose gallery images" type="image" :multiple="true" :value="old('gallery_asset_ids', [])" />
                            @error('gallery_asset_ids') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                            @error('gallery_asset_ids.*') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                            <p class="text-xs text-espresso-500">Select up to 30 images and reorder them. Clear the selection to restore the legacy gallery.</p>
                        @endif
                        <label for="gallery_images" class="label-luxury">Gallery Images (Multiple, Max 4MB each)</label>
                        <input type="file" id="gallery_images" name="gallery_images[]" multiple class="input-luxury text-sm">
                        @error('gallery_images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                @include('admin.products._seo-fields')

            </div>

            {{-- Right: Flags, Attributes & Actions --}}
            <div class="lg:col-span-4 space-y-6">
                
                {{-- Visibility & Badges --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4 text-xs font-semibold text-espresso-750">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Settings & Badges</h3>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Active (Visible on website)</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="in_stock" value="1" checked class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>In Stock</span>
                    </label>

                    <hr class="border-cream-100">

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_featured" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Chef Featured</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_bestseller" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Best Seller</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_new_arrival" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>New Arrival</span>
                    </label>
                </div>

                {{-- Occasions Sync --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-3 text-xs font-medium text-espresso-750">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Occasions</h3>
                    
                    <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-hide pr-1">
                        @foreach($occasions as $occ)
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="occasions[]" value="{{ $occ->id }}" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                                <span>{{ $occ->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Action Box --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-3">
                    <button type="submit" class="btn-gold w-full justify-center py-3 text-sm font-semibold tracking-wide">
                        Create Product
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="btn-secondary w-full justify-center py-2.5 text-xs block text-center">
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
