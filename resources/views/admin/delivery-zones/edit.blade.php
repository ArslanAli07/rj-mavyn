<x-admin-layout title="Edit delivery zone">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Edit delivery zone</h1>
            <p class="small text-body-secondary mt-1">Modify shipping zone details below.</p>
        </div>
        <a href="{{ route('admin.delivery-zones.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to zones
        </a>
    </div>

    <form action="{{ route('admin.delivery-zones.update', $deliveryZone->id) }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf
        @method('PUT')

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="name" class="form-label">Zone name *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $deliveryZone->name) }}" required class="form-control small">
                @error('name') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="delivery_fee" class="form-label">Delivery fee (PKR) *</label>
                <input type="number" step="0.01" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', $deliveryZone->delivery_fee) }}" required class="form-control small num">
                @error('delivery_fee') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="display_order" class="form-label">Display order (sorting)</label>
            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $deliveryZone->display_order) }}" required class="form-control small num">
            @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $deliveryZone->is_active) ? 'checked' : '' }} class="form-check-input">
                <span>Active (Available for checkout)</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.delivery-zones.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Update zone</button>
        </div>
    </form>

</x-admin-layout>
