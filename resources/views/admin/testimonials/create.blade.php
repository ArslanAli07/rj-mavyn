<x-admin-layout title="Create Testimonial">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Add Testimonial</h1>
            <p class="text-xs text-espresso-500 mt-1">Specify reviewer information below.</p>
        </div>
        <a href="{{ route('admin.testimonials.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Testimonials
        </a>
    </div>

    <form action="{{ route('admin.testimonials.store') }}" method="POST" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm max-w-2xl space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="customer_name" class="label-luxury">Customer Name *</label>
                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required class="input-luxury text-sm">
                @error('customer_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="customer_location" class="label-luxury">Customer Location / Designation</label>
                <input type="text" id="customer_location" name="customer_location" value="{{ old('customer_location') }}" placeholder="e.g. Model Town, Sialkot" class="input-luxury text-sm">
                @error('customer_location') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="rating" class="label-luxury">Rating Stars *</label>
                <select id="rating" name="rating" required class="input-luxury text-sm">
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
                @error('rating') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="review" class="label-luxury">Customer Review Message *</label>
            <textarea id="review" name="review" rows="4" required class="input-luxury text-sm">{{ old('review') }}</textarea>
            @error('review') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex flex-wrap gap-6">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_approved" value="1" checked class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Approve Immediately</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                <input type="checkbox" name="is_featured" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                <span>Featured on Home Page</span>
            </label>
        </div>

        <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
            <a href="{{ route('admin.testimonials.index') }}" class="btn-secondary py-2 px-5 text-xs">Cancel</a>
            <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Save Testimonial</button>
        </div>
    </form>

</x-admin-layout>
