<x-admin-layout title="Manage testimonials">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Testimonials</h1>
            <p class="small text-body-secondary mt-1">Approve, feature, and manage customer testimonials showing on the home page.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.testimonials.create'))
<a href="{{ route('admin.testimonials.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add testimonial
        </a>
@endif
    </div>

    {{-- Testimonials List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($testimonials->isEmpty())
            <p class="small text-body-secondary text-center py-5">No testimonials defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.testimonials.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="approve" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Approve</button>
                    <button type="submit" name="bulk_action" value="hide" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Hide</button>
                    <button type="submit" name="bulk_action" value="feature" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Feature</button>
                    <button type="submit" name="bulk_action" value="unfeature" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Unfeature</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected testimonial(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Reviewer</th>
                            <th>Location</th>
                            <th>Rating</th>
                            <th>Review message</th>
                            <th>Featured</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($testimonials as $t)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$t->id" :label="'Select review by ' . $t->customer_name" /></td>
                                <td class="fw-semibold text-body small">{{ $t->customer_name }}</td>
                                <td class="text-body-secondary fw-semibold">{{ $t->customer_location ?? 'Sialkot' }}</td>
                                <td>
                                    <div class="d-flex text-warning fw-semibold">
                                        {{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}
                                    </div>
                                </td>
                                <td class="text-body fst-italic admin-max-width-sm text-truncate">"{{ $t->review }}"</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $t->is_featured ? 'bg-primary-subtle text-primary' : 'bg-body-tertiary text-body-secondary' }}">
                                        {{ $t->is_featured ? 'Featured' : 'Regular' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $t->is_approved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }}">
                                        {{ $t->is_approved ? 'Approved' : 'Hidden' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        {{-- Approve Toggle --}}
                                        <form action="{{ route('admin.testimonials.approve', $t->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary px-2 py-1 bg-body-tertiary rounded-2 fw-semibold small">
                                                {{ $t->is_approved ? 'Hide' : 'Approve' }}
                                            </button>
                                        </form>

                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.testimonials.edit'))
<a href="{{ route('admin.testimonials.edit', $t->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.testimonials.destroy', $t->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $testimonials->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
