<x-admin-layout title="Create New Category">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Add New Category</h1>
            <p class="text-xs text-espresso-500 mt-1">Specify collection details below.</p>
        </div>
        <a href="{{ route('admin.categories.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Categories
        </a>
    </div>

    <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm max-w-2xl space-y-4">
        @csrf

        <div>
            <label for="name" class="label-luxury">Category Name *</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required class="input-luxury text-sm">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="label-luxury">Category Slug (Optional)</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="e.g. example-category" class="input-luxury text-sm font-mono">
            @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="label-luxury">Description</label>
            <textarea id="description" name="description" rows="3" class="input-luxury text-sm">{{ old('description') }}</textarea>
            @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="display_order" class="label-luxury">Display Order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" required class="input-luxury text-sm">
                @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="image_asset_id" label="Choose category image" type="image" :value="old('image_asset_id', null)" />
                        @error('image_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    @endif
                <label for="image" class="label-luxury">Feature Image (JPEG/PNG, Max 2MB)</label>
                <input type="file" id="image" name="image" class="input-luxury text-sm">
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row gap-4">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Active (Visible on website)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="show_on_homepage" value="1" checked class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Show on Home Page</span>
            </label>
        </div>

        <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
            <a href="{{ route('admin.categories.index') }}" class="btn-secondary py-2 px-5 text-xs">Cancel</a>
            <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Save Category</button>
        </div>
    </form>

</x-admin-layout>
