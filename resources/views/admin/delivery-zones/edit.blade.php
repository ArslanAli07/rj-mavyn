<x-admin-layout title="Edit Delivery Zone">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Edit Delivery Zone</h1>
            <p class="text-xs text-espresso-500 mt-1">Modify shipping zone details below.</p>
        </div>
        <a href="{{ route('admin.delivery-zones.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Zones
        </a>
    </div>

    <form action="{{ route('admin.delivery-zones.update', $deliveryZone->id) }}" method="POST" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="label-luxury">Zone Name *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $deliveryZone->name) }}" required class="input-luxury text-sm">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="delivery_fee" class="label-luxury">Delivery Fee (PKR) *</label>
                <input type="number" step="0.01" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', $deliveryZone->delivery_fee) }}" required class="input-luxury text-sm">
                @error('delivery_fee') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="display_order" class="label-luxury">Display Order (Sorting)</label>
            <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $deliveryZone->display_order) }}" required class="input-luxury text-sm">
            @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $deliveryZone->is_active) ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Active (Available for checkout)</span>
            </label>
        </div>

        <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
            <a href="{{ route('admin.delivery-zones.index') }}" class="btn-secondary py-2 px-5 text-xs">Cancel</a>
            <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Update Zone</button>
        </div>
    </form>

</x-admin-layout>
