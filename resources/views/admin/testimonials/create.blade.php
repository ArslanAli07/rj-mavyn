<x-admin-layout title="Create testimonial">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Add testimonial</h1>
            <p class="small text-body-secondary mt-1">Specify reviewer information below.</p>
        </div>
        <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to testimonials
        </a>
    </div>

    <form action="{{ route('admin.testimonials.store') }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="customer_name" class="form-label">Customer name *</label>
                <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required class="form-control small">
                @error('customer_name') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="customer_location" class="form-label">Customer location / designation</label>
                <input type="text" id="customer_location" name="customer_location" value="{{ old('customer_location') }}" placeholder="e.g. Model Town, Sialkot" class="form-control small">
                @error('customer_location') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
            <div>
                <label for="rating" class="form-label">Rating stars *</label>
                <select id="rating" name="rating" required class="form-select small">
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
                @error('rating') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="review" class="form-label">Customer review message *</label>
            <textarea id="review" name="review" rows="4" required class="form-control small">{{ old('review') }}</textarea>
            @error('review') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 d-flex flex-wrap gap-4">
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_approved" value="1" checked class="form-check-input">
                <span>Approve Immediately</span>
            </label>
            <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
                <input type="checkbox" name="is_featured" value="1" class="form-check-input">
                <span>Featured on Home Page</span>
            </label>
        </div>

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save testimonial</button>
        </div>
    </form>

</x-admin-layout>
