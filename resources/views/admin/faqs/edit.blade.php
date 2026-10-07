<x-admin-layout title="Edit FAQ">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Edit FAQ</h1>
            <p class="text-xs text-espresso-500 mt-1">Modify question and answer details below.</p>
        </div>
        <a href="{{ route('admin.faqs.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to FAQs
        </a>
    </div>

    <form action="{{ route('admin.faqs.update', $faq->id) }}" method="POST" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="category" class="label-luxury">Category *</label>
                <select id="category" name="category" required class="input-luxury text-sm">
                    <option value="General" {{ old('category', $faq->category) === 'General' ? 'selected' : '' }}>General</option>
                    <option value="Orders & Payments" {{ old('category', $faq->category) === 'Orders & Payments' ? 'selected' : '' }}>Orders & Payments</option>
                    <option value="Delivery Info" {{ old('category', $faq->category) === 'Delivery Info' ? 'selected' : '' }}>Delivery Info</option>
                    <option value="Products" {{ old('category', $faq->category) === 'Products' || old('category', $faq->category) === 'Custom Cakes' ? 'selected' : '' }}>Products</option>
                </select>
                @error('category') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="display_order" class="label-luxury">Display Order</label>
                <input type="number" id="display_order" name="display_order" value="{{ old('display_order', $faq->display_order) }}" required class="input-luxury text-sm">
                @error('display_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="question" class="label-luxury">Question *</label>
            <input type="text" id="question" name="question" value="{{ old('question', $faq->question) }}" required class="input-luxury text-sm">
            @error('question') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="answer" class="label-luxury">Answer details *</label>
            <textarea id="answer" name="answer" rows="5" required class="input-luxury text-sm">{{ old('answer', $faq->answer) }}</textarea>
            @error('answer') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_active" value="1" {{ $faq->is_active ? 'checked' : '' }} class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Active (Visible on website FAQ list)</span>
            </label>
        </div>

        <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
            <a href="{{ route('admin.faqs.index') }}" class="btn-secondary py-2 px-5 text-xs">Cancel</a>
            <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Save Changes</button>
        </div>
    </form>

</x-admin-layout>
