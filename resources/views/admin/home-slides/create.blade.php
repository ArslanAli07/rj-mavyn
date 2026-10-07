<x-admin-layout title="Add Hero Slide">

    {{-- Header --}}
    <div class="mb-8">
        <a href="{{ route('admin.home-slides.index') }}" class="text-xs text-espresso-500 hover:text-espresso-800 flex items-center gap-1 mb-2">
            ← Back to Hero Slides
        </a>
        <h1 class="text-2xl font-heading font-bold text-espresso-800">Add Hero Slide</h1>
    </div>

    {{-- Form --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 sm:p-8 max-w-3xl shadow-sm">
        <form action="{{ route('admin.home-slides.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Banner Image (Desktop) --}}
                <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="image_asset_id" label="Choose desktop image" type="image" :value="old('image_asset_id', null)" />
                        @error('image_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    @endif
                    <label for="image" class="label-luxury text-xs font-semibold uppercase">Desktop Image *</label>
                    <input type="file" id="image" name="image" accept="image/*"
                           class="w-full text-xs text-espresso-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 border border-cream-300 rounded-xl p-2 focus:outline-none">
                    <p class="text-[10px] text-espresso-400 mt-1">Recommended: 1920px wide or larger (max 4MB).</p>
                    @error('image') <p class="text-xs text-red-650 mt-1 font-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Mobile Banner Image --}}
                <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="mobile_image_asset_id" label="Choose mobile image" type="image" :value="old('mobile_image_asset_id', null)" />
                        @error('mobile_image_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    @endif
                    <label for="mobile_image" class="label-luxury text-xs font-semibold uppercase">Mobile Image</label>
                    <input type="file" id="mobile_image" name="mobile_image" accept="image/*"
                           class="w-full text-xs text-espresso-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 border border-cream-300 rounded-xl p-2 focus:outline-none">
                    <p class="text-[10px] text-espresso-400 mt-1">Optional. Uses desktop image on mobile if empty.</p>
                    @error('mobile_image') <p class="text-xs text-red-650 mt-1 font-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Display Order --}}
                <div>
                    <label for="display_order" class="label-luxury text-xs font-semibold uppercase">Display Order *</label>
                    <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" min="0" required
                           class="input-luxury text-xs">
                    @error('display_order') <p class="text-xs text-red-650 mt-1 font-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Status --}}
                <div class="flex items-center gap-2 pt-6">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked
                           class="rounded text-primary-500 focus:ring-primary-300 border-cream-300">
                    <label for="is_active" class="text-xs font-semibold uppercase text-espresso-700">Active (Visible on homepage)</label>
                </div>
            </div>

            <div class="border-t border-cream-100 pt-6 flex justify-end gap-3">
                <a href="{{ route('admin.home-slides.index') }}" class="btn-ghost text-xs font-semibold py-2 px-4">Cancel</a>
                <button type="submit" class="btn-gold py-2 px-6 text-xs font-semibold">Save Slide</button>
            </div>
        </form>
    </div>

</x-admin-layout>
