<div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
    <div class="border-bottom border-light-subtle pb-2 mb-3">
        <h3 class="text-body admin-section-title">SEO details</h3>
        <p class="small text-body-secondary mt-1">Optional. Empty fields use the product name, short description, and default site keywords.</p>
    </div>

    <div>
        <label for="meta_title" class="form-label">Search title</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="255"
               value="{{ old('meta_title', $product->meta_title ?? '') }}"
               placeholder="e.g. Product name — Store name"
               class="form-control small">
        @error('meta_title') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="meta_description" class="form-label">Search description</label>
        <textarea id="meta_description" name="meta_description" rows="3" maxlength="500"
                  placeholder="Briefly describe this product for search results..."
                  class="form-control small">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
        @error('meta_description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="meta_keywords" class="form-label">Keywords (comma separated)</label>
        <input type="text" id="meta_keywords" name="meta_keywords" maxlength="1000"
               value="{{ old('meta_keywords', $product->meta_keywords ?? '') }}"
               placeholder="e.g. product name, category, store name"
               class="form-control small">
        @error('meta_keywords') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>
</div>
