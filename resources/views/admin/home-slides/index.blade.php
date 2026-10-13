<x-admin-layout title="Manage hero slides">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Homepage hero slides</h1>
            <p class="small text-body-secondary mt-1">Upload and manage image slides for the homepage carousel.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.home-slides.create'))
<a href="{{ route('admin.home-slides.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add new slide
        </a>
@endif
    </div>

    {{-- Slides List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($slides->isEmpty())
            <p class="small text-body-secondary text-center py-5">No hero slides defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.home-slides.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected slide(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table admin-table-with-thumbnails small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Desktop image</th>
                            <th>Mobile image</th>
                            <th>Display order</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slides as $slide)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$slide->id" label="Select slide" /></td>
                                <td>
                                    <img src="{{ $slide->image_url }}" alt="Hero slide" class="admin-width-24 h-auto rounded-2 border border-light-subtle">
                                </td>
                                <td>
                                    @if($slide->mobile_image_path || $slide->mobile_image_asset_id)
                                        <img src="{{ $slide->mobile_image_url }}" alt="Mobile slide" class="admin-width-16 h-auto rounded-2 border border-light-subtle">
                                    @else
                                        <span class="text-body-secondary fst-italic">Uses desktop</span>
                                    @endif
                                </td>
                                <td class="text-body-secondary fw-semibold">{{ $slide->display_order }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $slide->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $slide->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.home-slides.edit'))
<a href="{{ route('admin.home-slides.edit', $slide->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif

                                        <form action="{{ route('admin.home-slides.destroy', $slide->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this hero slide?');">
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
