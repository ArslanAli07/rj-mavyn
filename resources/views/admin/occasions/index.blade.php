<x-admin-layout title="Manage occasions">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Occasions</h1>
            <p class="small text-body-secondary mt-1">Manage occasion collections like birthdays, weddings, baby showers, etc.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.occasions.create'))
<a href="{{ route('admin.occasions.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add occasion
        </a>
@endif
    </div>

    {{-- Occasions List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($occasions->isEmpty())
            <p class="small text-body-secondary text-center py-5">No occasions defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.occasions.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected occasion(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table admin-table-with-thumbnails small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Display order</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($occasions as $occ)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$occ->id" :label="'Select ' . $occ->name" /></td>
                                <td>
                                    <img src="{{ $occ->image_url ?? asset('images/placeholder.svg') }}" alt="{{ $occ->name }}" class="admin-width-10 admin-height-10 rounded-2 object-fit-cover border border-light-subtle">
                                </td>
                                <td class="fw-semibold text-body small">{{ $occ->name }}</td>
                                <td class="font-monospace text-body-secondary">{{ $occ->slug }}</td>
                                <td class="text-body-secondary fw-semibold">{{ $occ->display_order }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $occ->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $occ->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.occasions.edit'))
<a href="{{ route('admin.occasions.edit', $occ->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.occasions.destroy', $occ->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this occasion?');">
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
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
