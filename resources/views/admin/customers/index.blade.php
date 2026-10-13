<x-admin-layout title="Manage customers">

    {{-- Header --}}
    <div class="mb-5 border-bottom border-light-subtle pb-3">
        <h1 class="text-body admin-page-title">Customers</h1>
        <p class="small text-body-secondary mt-1">Browse registered customer profiles, orders summary, and details.</p>
    </div>

    {{-- Customers List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($customers->isEmpty())
            <p class="small text-body-secondary text-center py-5">No customers registered yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.customers.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected customer(s)? Their saved addresses and carts will also be removed. Orders will be kept." class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Avatar</th>
                            <th>Name</th>
                            <th>Email address</th>
                            <th>Phone</th>
                            <th>Total orders placed</th>
                            <th>Date joined</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $c)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$c->id" :label="'Select ' . $c->name" /></td>
                                <td>
                                    <div class="admin-width-8 admin-height-8 rounded-pill bg-primary-subtle d-flex align-items-center justify-content-center fw-semibold text-primary">
                                        {{ substr($c->name, 0, 1) }}
                                    </div>
                                </td>
                                <td class="fw-semibold text-body small">{{ $c->name }}</td>
                                <td class="text-body-secondary font-monospace">{{ $c->email }}</td>
                                <td class="text-body-secondary">{{ $c->phone ?? 'N/A' }}</td>
                                <td class="text-body-secondary fw-semibold">{{ $c->orders_count }}</td>
                                <td class="text-body-secondary">{{ $c->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        <a href="{{ route('admin.customers.show', $c->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">View profile</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $customers->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
