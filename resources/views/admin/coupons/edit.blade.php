<x-admin-layout title="Edit coupon: {{ $coupon->code }}">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Edit coupon: {{ $coupon->code }}</h1>
            <p class="small text-body-secondary mt-1">Update promotional code limits and values.</p>
        </div>
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to coupons
        </a>
    </div>

    <form action="{{ route('admin.coupons.update', $coupon->id) }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf
        @method('PUT')

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="code" class="form-label">Coupon code</label>
                <input type="text" id="code" value="{{ $coupon->code }}" readonly class="form-control small font-monospace bg-body-tertiary text-body-secondary">
                <span class="small text-body-secondary mt-1 d-block">Coupon code cannot be changed once created.</span>
            </div>
            <div>
                <label for="type" class="form-label">Discount type *</label>
                <select id="type" name="type" required class="form-select small">
                    <option value="percentage" {{ old('type', $coupon->type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                    <option value="fixed" {{ old('type', $coupon->type) === 'fixed' ? 'selected' : '' }}>Fixed amount (PKR)</option>
                </select>
                @error('type') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="amount" class="form-label">Value / discount amount *</label>
                <input type="number" step="0.01" id="amount" name="amount" value="{{ old('amount', $coupon->amount) }}" required class="form-control small num">
                @error('amount') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="minimum_order" class="form-label">Minimum order value (PKR)</label>
                <input type="number" step="0.01" id="minimum_order" name="minimum_order" value="{{ old('minimum_order', $coupon->minimum_order) }}" class="form-control small num">
                @error('minimum_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-3 gap-4">
            <div>
                <label for="maximum_discount" class="form-label">Max discount (PKR)</label>
                <input type="number" step="0.01" id="maximum_discount" name="maximum_discount" value="{{ old('maximum_discount', $coupon->maximum_discount) }}" class="form-control small num">
                @error('maximum_discount') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="usage_limit" class="form-label">Global usage limit</label>
                <input type="number" id="usage_limit" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" placeholder="Leave blank for unlimited" class="form-control small num">
                @error('usage_limit') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="per_user_limit" class="form-label">Per user limit</label>
                <input type="number" id="per_user_limit" name="per_user_limit" value="{{ old('per_user_limit', $coupon->per_user_limit) }}" required class="form-control small num">
                @error('per_user_limit') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="starts_at" class="form-label">Starts at</label>
                <input type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', $coupon->starts_at ? $coupon->starts_at->format('Y-m-d') : '') }}" class="form-control small">
                @error('starts_at') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expires_at" class="form-label">Expires at</label>
                <input type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', $coupon->expires_at ? $coupon->expires_at->format('Y-m-d') : '') }}" class="form-control small">
                @error('expires_at') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_active" value="1" {{ $coupon->is_active ? 'checked' : '' }} class="form-check-input">
                <span>Active (Acceptable in Cart checkout)</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save changes</button>
        </div>
    </form>

</x-admin-layout>
