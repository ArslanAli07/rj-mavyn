<x-admin-layout title="Create FAQ">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Add new FAQ</h1>
            <p class="small text-body-secondary mt-1">Specify FAQ details below.</p>
        </div>
        <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to FAQs
        </a>
    </div>

    <form action="{{ route('admin.faqs.store') }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="category" class="form-label">Category *</label>
                <select id="category" name="category" required class="form-select small">
                    <option value="General">General</option>
                    <option value="Orders & Payments">Orders & payments</option>
                    <option value="Delivery Info">Delivery info</option>
                    <option value="Products">Products</option>
                </select>
                @error('category') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="display_order" class="form-label">Display order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', 0) }}" required class="form-control small num">
                @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="question" class="form-label">Question *</label>
            <input type="text" id="question" name="question" value="{{ old('question') }}" placeholder="e.g. Do you deliver to Sialkot Cantt?" required class="form-control small">
            @error('question') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="answer" class="form-label">Answer details *</label>
            <textarea id="answer" name="answer" rows="5" required class="form-control small">{{ old('answer') }}</textarea>
            @error('answer') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_active" value="1" checked class="form-check-input">
                <span>Active (Visible on website FAQ list)</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save FAQ</button>
        </div>
    </form>

</x-admin-layout>
