<x-admin-layout title="Edit occasion — {{ $occasion->name }}">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Edit occasion: {{ $occasion->name }}</h1>
            <p class="small text-body-secondary mt-1">Modify details for this occasion collection.</p>
        </div>
        <a href="{{ route('admin.occasions.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to occasions
        </a>
    </div>

    <form action="{{ route('admin.occasions.update', $occasion->id) }}" method="POST" enctype="multipart/form-data" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="form-label">Occasion name *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $occasion->name) }}" required class="form-control small">
            @error('name') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="form-label">Occasion slug</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug', $occasion->slug) }}" required class="form-control small font-monospace">
            @error('slug') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3" class="form-control small">{{ old('description', $occasion->description) }}</textarea>
            @error('description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-4 gap-4 align-items-center">
            <div class="admin-span-sm-1 admin-aspect-square rounded-2 overflow-hidden border bg-body-tertiary">
                <img src="{{ $occasion->image_url ?? asset('images/placeholder.svg') }}" alt="{{ $occasion->name }}" class="w-100 h-100 object-fit-cover">
            </div>
            <div class="admin-span-sm-3">
                <label for="image" class="form-label">Update occasion image (jpeg/png, max 2mb)</label>
                <input type="file" id="image" name="image" class="form-control small">
                @error('image') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="display_order" class="form-label">Display order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $occasion->display_order) }}" required class="form-control small num">
                @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_active" value="1" {{ $occasion->is_active ? 'checked' : '' }} class="form-check-input">
                <span>Active (Visible on website)</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.occasions.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save changes</button>
        </div>
    </form>

</x-admin-layout>
