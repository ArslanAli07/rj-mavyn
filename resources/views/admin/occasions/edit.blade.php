<x-admin-layout title="Edit Occasion — {{ $occasion->name }}">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Edit Occasion: {{ $occasion->name }}</h1>
            <p class="text-xs text-espresso-500 mt-1">Modify details for this occasion collection.</p>
        </div>
        <a href="{{ route('admin.occasions.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Occasions
        </a>
    </div>

    <form action="{{ route('admin.occasions.update', $occasion->id) }}" method="POST" enctype="multipart/form-data" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="label-luxury">Occasion Name *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $occasion->name) }}" required class="input-luxury text-sm">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="label-luxury">Occasion Slug</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug', $occasion->slug) }}" required class="input-luxury text-sm font-mono">
            @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="label-luxury">Description</label>
            <textarea id="description" name="description" rows="3" class="input-luxury text-sm">{{ old('description', $occasion->description) }}</textarea>
            @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-center">
            <div class="sm:col-span-1 aspect-square rounded-xl overflow-hidden border bg-cream-50">
                <img src="{{ $occasion->image_url ?? asset('images/placeholder.jpg') }}" alt="{{ $occasion->name }}" class="w-full h-full object-cover">
            </div>
            <div class="sm:col-span-3">
                <label for="image" class="label-luxury">Update Occasion Image (JPEG/PNG, Max 2MB)</label>
                <input type="file" id="image" name="image" class="input-luxury text-sm">
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="display_order" class="label-luxury">Display Order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $occasion->display_order) }}" required class="input-luxury text-sm">
                @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_active" value="1" {{ $occasion->is_active ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Active (Visible on website)</span>
            </label>
        </div>

        <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
            <a href="{{ route('admin.occasions.index') }}" class="btn-secondary py-2 px-5 text-xs">Cancel</a>
            <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Save Changes</button>
        </div>
    </form>

</x-admin-layout>
