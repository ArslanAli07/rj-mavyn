<x-admin-layout title="Manage coupons">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Coupons</h1>
            <p class="small text-body-secondary mt-1">Manage promotional discount codes for your customers.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.coupons.create'))
<a href="{{ route('admin.coupons.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add coupon
        </a>
@endif
    </div>

    {{-- Coupons List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($coupons->isEmpty())
            <p class="small text-body-secondary text-center py-5">No coupons defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.coupons.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected coupon(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Code</th>
                            <th class="price num">Discount</th>
                            <th class="price num">Min spend</th>
                            <th class="price num">Max discount</th>
                            <th>Usage</th>
                            <th>Starts</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coupons as $c)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$c->id" :label="'Select coupon ' . $c->code" /></td>
                                <td class="text-body font-monospace sku">{{ $c->code }}</td>
                                <td class="fw-semibold text-body price num">
                                    @if($c->type === 'fixed')
                                        {{ ($c->price_currency ? format_price($c->amount, $c->price_currency) : 'Currency needs review') }}
                                    @else
                                        {{ (int) $c->amount }}%
                                    @endif
                                </td>
                                <td class="price num">{{ ($c->price_currency ? format_price($c->minimum_order ?? 0, $c->price_currency) : 'Currency needs review') }}</td>
                                <td class="price num">{{ $c->maximum_discount !== null ? ($c->price_currency ? format_price($c->maximum_discount, $c->price_currency) : 'Currency needs review') : 'N/A' }}</td>
                                <td>
                                    {{ $c->usage_count }} / {{ $c->usage_limit ?? '∞' }}
                                </td>
                                <td class="text-body-secondary">{{ $c->starts_at ? $c->starts_at->format('Y-m-d') : 'Immediate' }}</td>
                                <td class="text-body-secondary">{{ $c->expires_at ? $c->expires_at->format('Y-m-d') : 'Never' }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $c->is_active && $c->is_valid ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $c->is_active && $c->is_valid ? 'Valid' : 'Expired/Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.coupons.edit'))
<a href="{{ route('admin.coupons.edit', $c->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.coupons.destroy', $c->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this coupon?');">
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
            {{ $coupons->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
