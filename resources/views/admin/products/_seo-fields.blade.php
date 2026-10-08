<div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
    <div class="border-b border-cream-100 pb-2 mb-4">
        <h3 class="font-heading font-bold text-sm text-espresso-800">SEO Details</h3>
        <p class="text-[11px] text-espresso-400 mt-1">Optional. Empty fields use the product name, short description, and default site keywords.</p>
    </div>

    <div>
        <label for="meta_title" class="label-luxury">Search Title</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="255"
               value="{{ old('meta_title', $product->meta_title ?? '') }}"
               placeholder="e.g. Product name — Store name"
               class="input-luxury text-sm">
        @error('meta_title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="meta_description" class="label-luxury">Search Description</label>
        <textarea id="meta_description" name="meta_description" rows="3" maxlength="500"
                  placeholder="Briefly describe this product for search results..."
                  class="input-luxury text-sm">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
        @error('meta_description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="meta_keywords" class="label-luxury">Keywords (comma separated)</label>
        <input type="text" id="meta_keywords" name="meta_keywords" maxlength="1000"
               value="{{ old('meta_keywords', $product->meta_keywords ?? '') }}"
               placeholder="e.g. product name, category, store name"
               class="input-luxury text-sm">
        @error('meta_keywords') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</div>
