<x-admin-layout title="Manage delivery zones">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Delivery zones</h1>
            <p class="small text-body-secondary mt-1">Configure Sialkot delivery zones and their respective delivery charges.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.delivery-zones.create'))
<a href="{{ route('admin.delivery-zones.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add delivery zone
        </a>
@endif
    </div>

    {{-- Delivery Zones List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($zones->isEmpty())
            <p class="small text-body-secondary text-center py-5">No delivery zones defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.delivery-zones.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected delivery zone(s)? Zones used on past orders will be skipped." class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Zone name</th>
                            <th class="price num">Delivery fee</th>
                            <th>Display order</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zones as $zone)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$zone->id" :label="'Select ' . $zone->name" /></td>
                                <td class="fw-semibold text-body small">{{ $zone->name }}</td>
                                <td class="text-body fw-semibold small price num">{{ format_price($zone->delivery_fee) }}</td>
                                <td class="text-body-secondary fw-semibold">{{ $zone->display_order }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $zone->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.delivery-zones.edit'))
<a href="{{ route('admin.delivery-zones.edit', $zone->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.delivery-zones.destroy', $zone->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this delivery zone?');">
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
            {{ $zones->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
