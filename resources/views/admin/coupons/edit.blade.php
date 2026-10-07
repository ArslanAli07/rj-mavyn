<x-admin-layout title="Edit Coupon: {{ $coupon->code }}">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Edit Coupon: {{ $coupon->code }}</h1>
            <p class="text-xs text-espresso-500 mt-1">Update promotional code limits and values.</p>
        </div>
        <a href="{{ route('admin.coupons.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Coupons
        </a>
    </div>

    <form action="{{ route('admin.coupons.update', $coupon->id) }}" method="POST" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="code" class="label-luxury">Coupon Code</label>
                <input type="text" id="code" value="{{ $coupon->code }}" readonly class="input-luxury text-sm font-mono uppercase bg-cream-50 text-espresso-450">
                <span class="text-[10px] text-espresso-400 mt-1 block">Coupon code cannot be changed once created.</span>
            </div>
            <div>
                <label for="type" class="label-luxury">Discount Type *</label>
                <select id="type" name="type" required class="input-luxury text-sm">
                    <option value="percentage" {{ old('type', $coupon->type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                    <option value="fixed" {{ old('type', $coupon->type) === 'fixed' ? 'selected' : '' }}>Fixed Amount (PKR)</option>
                </select>
                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="amount" class="label-luxury">Value / Discount Amount *</label>
                <input type="number" step="0.01" id="amount" name="amount" value="{{ old('amount', $coupon->amount) }}" required class="input-luxury text-sm">
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="minimum_order" class="label-luxury">Minimum Order Value (PKR)</label>
                <input type="number" step="0.01" id="minimum_order" name="minimum_order" value="{{ old('minimum_order', $coupon->minimum_order) }}" class="input-luxury text-sm">
                @error('minimum_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="maximum_discount" class="label-luxury">Max Discount (PKR)</label>
                <input type="number" step="0.01" id="maximum_discount" name="maximum_discount" value="{{ old('maximum_discount', $coupon->maximum_discount) }}" class="input-luxury text-sm">
                @error('maximum_discount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="usage_limit" class="label-luxury">Global Usage Limit</label>
                <input type="number" id="usage_limit" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" placeholder="Leave blank for unlimited" class="input-luxury text-sm">
                @error('usage_limit') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="per_user_limit" class="label-luxury">Per User Limit</label>
                <input type="number" id="per_user_limit" name="per_user_limit" value="{{ old('per_user_limit', $coupon->per_user_limit) }}" required class="input-luxury text-sm">
                @error('per_user_limit') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="starts_at" class="label-luxury">Starts At</label>
                <input type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', $coupon->starts_at ? $coupon->starts_at->format('Y-m-d') : '') }}" class="input-luxury text-sm">
                @error('starts_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expires_at" class="label-luxury">Expires At</label>
                <input type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', $coupon->expires_at ? $coupon->expires_at->format('Y-m-d') : '') }}" class="input-luxury text-sm">
                @error('expires_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_active" value="1" {{ $coupon->is_active ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Active (Acceptable in Cart checkout)</span>
            </label>
        </div>

        <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
            <a href="{{ route('admin.coupons.index') }}" class="btn-secondary py-2 px-5 text-xs">Cancel</a>
            <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Save Changes</button>
        </div>
    </form>

</x-admin-layout>
