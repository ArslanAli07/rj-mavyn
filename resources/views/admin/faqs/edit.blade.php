<x-admin-layout title="Edit FAQ">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Edit FAQ</h1>
            <p class="small text-body-secondary mt-1">Modify question and answer details below.</p>
        </div>
        <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to FAQs
        </a>
    </div>

    <form action="{{ route('admin.faqs.update', $faq->id) }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf
        @method('PUT')

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="category" class="form-label">Category *</label>
                <select id="category" name="category" required class="form-select small">
                    <option value="General" {{ old('category', $faq->category) === 'General' ? 'selected' : '' }}>General</option>
                    <option value="Orders & Payments" {{ old('category', $faq->category) === 'Orders & Payments' ? 'selected' : '' }}>Orders & payments</option>
                    <option value="Delivery Info" {{ old('category', $faq->category) === 'Delivery Info' ? 'selected' : '' }}>Delivery info</option>
                    <option value="Products" {{ old('category', $faq->category) === 'Products' || old('category', $faq->category) === 'Custom Cakes' ? 'selected' : '' }}>Products</option>
                </select>
                @error('category') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="display_order" class="form-label">Display order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $faq->display_order) }}" required class="form-control small num">
                @error('display_order') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="question" class="form-label">Question *</label>
            <input type="text" id="question" name="question" value="{{ old('question', $faq->question) }}" required class="form-control small">
            @error('question') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="answer" class="form-label">Answer details *</label>
            <textarea id="answer" name="answer" rows="5" required class="form-control small">{{ old('answer', $faq->answer) }}</textarea>
            @error('answer') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_active" value="1" {{ $faq->is_active ? 'checked' : '' }} class="form-check-input">
                <span>Active (Visible on website FAQ list)</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save changes</button>
        </div>
    </form>

</x-admin-layout>
