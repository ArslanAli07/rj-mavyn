<x-admin-layout title="Edit Product — {{ $product->title }}">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Edit Product: {{ $product->title }}</h1>
            <p class="text-xs text-espresso-500 mt-1">Modify the specifications, prices, or gallery of this product.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Products
        </a>
    </div>

    <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {{-- Left: Details Form fields --}}
            <div class="lg:col-span-8 space-y-6">
                
                {{-- Basic Information --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Basic Information</h3>
                    
                    <div>
                        <label for="title" class="label-luxury">Product Title *</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $product->title) }}" required class="input-luxury text-sm">
                        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="category_id" class="label-luxury">Category *</label>
                            <select id="category_id" name="category_id" required class="input-luxury text-sm">
                                <option value="">Select Category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            @include('admin.products._inventory')
                        </div>
                        <div>
                            <label for="display_order" class="label-luxury">Category Priority *</label>
                            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $product->display_order) }}" required class="input-luxury text-sm">
                            @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>


                </div>

                {{-- Descriptions --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Descriptions</h3>

                    <div>
                        <label for="short_description" class="label-luxury">Short Description (Max 500 chars)</label>
                        <textarea id="short_description" name="short_description" rows="2" class="input-luxury text-sm">{{ old('short_description', $product->short_description) }}</textarea>
                        @error('short_description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="label-luxury">Long Description / Details</label>
                        <textarea id="description" name="description" rows="5" class="input-luxury text-sm">{{ old('description', $product->description) }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Specifications & Ingredients --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Specifications</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="ingredients" class="label-luxury">Ingredients Listing</label>
                            <textarea id="ingredients" name="ingredients" rows="2" class="input-luxury text-sm">{{ old('ingredients', $product->ingredients) }}</textarea>
                            @error('ingredients') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="allergens" class="label-luxury">Allergens (e.g. Nuts, Dairy)</label>
                            <textarea id="allergens" name="allergens" rows="2" class="input-luxury text-sm">{{ old('allergens', $product->allergens) }}</textarea>
                            @error('allergens') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                @php
                    $initialVariants = old('variants', $product->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name ?: $v->weight,
                        'price' => (float) $v->price,
                        'discount_price' => $v->discount_price ? (float) $v->discount_price : '',
                        'servings' => $v->servings ?? '',
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
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-6">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Images</h3>

                    {{-- Main Image --}}
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 items-center">
                        <div class="sm:col-span-1 aspect-square rounded-xl overflow-hidden border bg-cream-50">
                            <img src="{{ $product->main_image_url }}" alt="{{ $product->title }}" class="w-full h-full object-cover">
                        </div>
                        <div class="sm:col-span-3">
                            <x-admin.media-picker name="main_image_asset_id" label="Choose main image from library" type="image" :value="old('main_image_asset_id', $product->main_image_asset_id)" />
                        @error('main_image_asset_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-espresso-500 mt-2">Upload reusable images in the <a href="{{ route('admin.media.index') }}" target="_blank" class="underline">media library</a>. Clearing the selection restores the legacy image, when available.</p>
                        <label for="main_image" class="label-luxury">Update Main Feature Image</label>
                            <input type="file" id="main_image" name="main_image" class="input-luxury text-sm">
                            @error('main_image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <hr class="border-cream-100">

                    {{-- Gallery Images Manager --}}
                    <div>
                        <h4 class="font-heading font-bold text-xs text-espresso-750 uppercase tracking-wider mb-3">Secondary Gallery</h4>
                        
                        @if($product->images->isNotEmpty())
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                                @foreach($product->images as $img)
                                    <div class="relative group aspect-square rounded-xl overflow-hidden border border-cream-200">
                                        <img src="{{ $img->image_url }}" alt="Gallery Item {{ $loop->iteration }}" class="w-full h-full object-cover">
                                        {{-- Delete hover overlay --}}
                                        <div class="absolute inset-0 bg-espresso-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <button type="button" 
                                                    onclick="if(confirm('Delete this gallery image permanently?')) { document.getElementById('delete-image-{{ $img->id }}').submit(); }"
                                                    class="px-3 py-1.5 bg-red-650 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[11px] text-espresso-400 italic mb-4">No gallery images uploaded yet.</p>
                        @endif

                        @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                            <x-admin.media-picker name="gallery_asset_ids" label="Choose gallery images" type="image" :multiple="true" :value="old('gallery_asset_ids', old('gallery_asset_ids_present') ? [] : $product->gallery_asset_ids)" />
                            @error('gallery_asset_ids') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                            @error('gallery_asset_ids.*') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                            <p class="text-xs text-espresso-500">Select up to 30 images and reorder them. Clear the selection to restore the legacy gallery.</p>
                        @endif
                        <label for="gallery_images" class="label-luxury">Upload New Gallery Images</label>
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
                        <input type="checkbox" name="is_active" value="1" {{ $product->is_active ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Active (Visible on website)</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="in_stock" value="1" {{ $product->in_stock ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>In Stock</span>
                    </label>

                    <hr class="border-cream-100">

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_featured" value="1" {{ $product->is_featured ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Chef Featured</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_bestseller" value="1" {{ $product->is_bestseller ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Best Seller</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="is_new_arrival" value="1" {{ $product->is_new_arrival ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>New Arrival</span>
                    </label>
                </div>

                {{-- Occasions Sync --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-3 text-xs font-medium text-espresso-750">
                    <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Occasions</h3>
                    
                    <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-hide pr-1">
                        @php $currentOccs = $product->occasions->pluck('id')->toArray(); @endphp
                        @foreach($occasions as $occ)
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="occasions[]" value="{{ $occ->id }}"
                                       {{ in_array($occ->id, $currentOccs) ? 'checked' : '' }}
                                       class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                                <span>{{ $occ->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Action Box --}}
                <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-3">
                    <button type="submit" class="btn-gold w-full justify-center py-3 text-sm font-semibold tracking-wide">
                        Save Changes
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="btn-secondary w-full justify-center py-2.5 text-xs block text-center">
                        Cancel
                    </a>
                </div>
            </div>

        </div>
    </form>

    {{-- Hidden Delete Gallery Image Forms --}}
    @foreach($product->images as $img)
        <form id="delete-image-{{ $img->id }}" action="{{ route('admin.products.images.delete', [$product->id, $img->id]) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach

    @push('scripts')
        @include('admin.products._options-editor-script')
    @endpush

</x-admin-layout>
