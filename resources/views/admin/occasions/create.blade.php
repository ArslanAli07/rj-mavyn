<x-admin-layout title="Create new occasion">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Add new occasion</h1>
            <p class="small text-body-secondary mt-1">Specify details below to register an occasion catalog category.</p>
        </div>
        <a href="{{ route('admin.occasions.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to occasions
        </a>
    </div>

    <form action="{{ route('admin.occasions.store') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf

        <div>
            <label for="name" class="form-label">Occasion name *</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required class="form-control small">
            @error('name') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="form-label">Occasion slug (optional)</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="e.g. birthday-celebration" class="form-control small font-monospace">
            @error('slug') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3" class="form-control small">{{ old('description') }}</textarea>
            @error('description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="display_order" class="form-label">Display order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" required class="form-control small num">
                @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="image" class="form-label">Feature image (jpeg/png, max 2mb)</label>
                <input type="file" id="image" name="image" class="form-control small">
                @error('image') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_active" value="1" checked class="form-check-input">
                <span>Active (Visible on website)</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.occasions.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save occasion</button>
        </div>
    </form>

</x-admin-layout>
