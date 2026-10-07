<x-admin-layout title="Edit Hero Slide">

    {{-- Header --}}
    <div class="mb-8">
        <a href="{{ route('admin.home-slides.index') }}" class="text-xs text-espresso-500 hover:text-espresso-800 flex items-center gap-1 mb-2">
            ← Back to Hero Slides
        </a>
        <h1 class="text-2xl font-heading font-bold text-espresso-800">Edit Hero Slide</h1>
    </div>

    {{-- Form --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 sm:p-8 max-w-3xl shadow-sm">
        <form action="{{ route('admin.home-slides.update', $homeSlide->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Current Desktop Image Preview --}}
                <div>
                    <label class="label-luxury text-xs font-semibold uppercase">Current Desktop Image</label>
                    <img src="{{ $homeSlide->image_url }}" alt="Hero slide" class="w-full rounded-2xl border border-cream-200 mb-2">
                </div>

                {{-- Current Mobile Image Preview --}}
                <div>
                    <label class="label-luxury text-xs font-semibold uppercase">Current Mobile Image</label>
                    @if($homeSlide->mobile_image_path || $homeSlide->mobile_image_asset_id)
                        <img src="{{ $homeSlide->mobile_image_url }}" alt="Mobile slide" class="w-full rounded-2xl border border-cream-200 mb-2">
                    @else
                        <div class="w-full aspect-video bg-cream-100 rounded-2xl border border-dashed border-cream-300 flex items-center justify-center text-[10px] text-espresso-400 text-center px-2 mb-2">No mobile image (using desktop)</div>
                    @endif
                </div>

                {{-- Desktop Banner Image Upload --}}
                <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="image_asset_id" label="Choose desktop image" type="image" :value="old('image_asset_id', $homeSlide->image_asset_id)" />
                        @error('image_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    @endif
                    <label for="image" class="label-luxury text-xs font-semibold uppercase">Change Desktop Image</label>
                    <input type="file" id="image" name="image" accept="image/*"
                           class="w-full text-xs text-espresso-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 border border-cream-300 rounded-xl p-2 focus:outline-none">
                    <p class="text-[10px] text-espresso-400 mt-1">Leave empty to keep current (max 4MB).</p>
                    @error('image') <p class="text-xs text-red-650 mt-1 font-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Mobile Banner Image Upload --}}
                <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="mobile_image_asset_id" label="Choose mobile image" type="image" :value="old('mobile_image_asset_id', $homeSlide->mobile_image_asset_id)" />
                        @error('mobile_image_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    @endif
                    <label for="mobile_image" class="label-luxury text-xs font-semibold uppercase">Change Mobile Image</label>
                    <input type="file" id="mobile_image" name="mobile_image" accept="image/*"
                           class="w-full text-xs text-espresso-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 border border-cream-300 rounded-xl p-2 focus:outline-none">
                    <p class="text-[10px] text-espresso-400 mt-1">Leave empty to keep current (max 4MB).</p>
                    @error('mobile_image') <p class="text-xs text-red-650 mt-1 font-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Display Order --}}
                <div>
                    <label for="display_order" class="label-luxury text-xs font-semibold uppercase">Display Order *</label>
                    <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $homeSlide->display_order) }}" min="0" required
                           class="input-luxury text-xs">
                    @error('display_order') <p class="text-xs text-red-650 mt-1 font-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Status --}}
                <div class="flex items-center gap-2 pt-6">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ $homeSlide->is_active ? 'checked' : '' }}
                           class="rounded text-primary-500 focus:ring-primary-300 border-cream-300">
                    <label for="is_active" class="text-xs font-semibold uppercase text-espresso-700">Active (Visible on homepage)</label>
                </div>
            </div>

            <div class="border-t border-cream-100 pt-6 flex justify-end gap-3">
                <a href="{{ route('admin.home-slides.index') }}" class="btn-ghost text-xs font-semibold py-2 px-4">Cancel</a>
                <button type="submit" class="btn-gold py-2 px-6 text-xs font-semibold">Update Slide</button>
            </div>
        </form>
    </div>

</x-admin-layout>
