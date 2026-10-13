<x-admin-layout title="Manage FAQs">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">FAQs</h1>
            <p class="small text-body-secondary mt-1">Add or update frequently asked questions shown on the customer FAQ page.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.faqs.create'))
<a href="{{ route('admin.faqs.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add FAQ
        </a>
@endif
    </div>

    {{-- FAQs List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($faqs->isEmpty())
            <p class="small text-body-secondary text-center py-5">No FAQs defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.faqs.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected FAQ(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Category</th>
                            <th>Question</th>
                            <th>Answer details</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($faqs as $f)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$f->id" :label="'Select FAQ'" /></td>
                                <td class="fw-semibold text-body admin-letter-spacing">{{ $f->category ?? 'General' }}</td>
                                <td class="fw-semibold text-body small admin-max-width-xs text-truncate">{{ $f->question }}</td>
                                <td class="text-body-secondary admin-max-width-sm text-truncate">{{ $f->answer }}</td>
                                <td class="text-body-secondary fw-semibold">{{ $f->display_order }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $f->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $f->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.faqs.edit'))
<a href="{{ route('admin.faqs.edit', $f->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.faqs.destroy', $f->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
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
            {{ $faqs->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
