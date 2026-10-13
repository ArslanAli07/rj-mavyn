<x-admin-layout title="Add hero slide">

    {{-- Header --}}
    <div class="mb-5">
        <a href="{{ route('admin.home-slides.index') }}" class="small text-body-secondary d-flex align-items-center gap-1 mb-2">
            ← Back to hero slides
        </a>
        <h1 class="text-body admin-page-title">Add hero slide</h1>
    </div>

    {{-- Form --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 p-sm-5 admin-max-width-3xl shadow-sm">
        <form action="{{ route('admin.home-slides.store') }}" method="POST" enctype="multipart/form-data" class="admin-stack-6">
            @csrf

            <div class="d-grid admin-cols-1 admin-cols-md-2 gap-4">
                {{-- Banner Image (Desktop) --}}
                <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="image_asset_id" label="Choose desktop image" type="image" :value="old('image_asset_id', null)" />
                        @error('image_asset_id') <p class="text-danger small">{{ $message }}</p> @enderror
                    @endif
                    <label for="image" class="form-label small fw-semibold">Desktop image *</label>
                    <input type="file" id="image" name="image" accept="image/*"
                           class="form-control w-100 small text-body-secondary border border-light-subtle rounded-2 p-2">
                    <p class="small text-body-secondary mt-1">Recommended: 1920px wide or larger (max 4MB).</p>
                    @error('image') <p class="small text-danger mt-1 fw-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Mobile Banner Image --}}
                <div>
                    @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                        <x-admin.media-picker name="mobile_image_asset_id" label="Choose mobile image" type="image" :value="old('mobile_image_asset_id', null)" />
                        @error('mobile_image_asset_id') <p class="text-danger small">{{ $message }}</p> @enderror
                    @endif
                    <label for="mobile_image" class="form-label small fw-semibold">Mobile image</label>
                    <input type="file" id="mobile_image" name="mobile_image" accept="image/*"
                           class="form-control w-100 small text-body-secondary border border-light-subtle rounded-2 p-2">
                    <p class="small text-body-secondary mt-1">Optional. Uses desktop image on mobile if empty.</p>
                    @error('mobile_image') <p class="small text-danger mt-1 fw-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Display Order --}}
                <div>
                    <label for="display_order" class="form-label small fw-semibold">Display order *</label>
                    <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" min="0" required
                           class="form-control small num">
                    @error('display_order') <p class="small text-danger mt-1 fw-semibold">{{ $message }}</p> @enderror
                </div>

                {{-- Status --}}
                <div class="d-flex align-items-center gap-2 pt-4">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked
                           class="form-check-input">
                    <label for="is_active" class="small fw-semibold text-body">Active (visible on homepage)</label>
                </div>
            </div>

            <div class="border-top border-light-subtle pt-4 d-flex justify-content-end gap-3">
                <a href="{{ route('admin.home-slides.index') }}" class="btn btn-outline-secondary small fw-semibold py-2 px-3">Cancel</a>
                <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save slide</button>
            </div>
        </form>
    </div>

</x-admin-layout>
