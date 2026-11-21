<x-admin-layout title="Customer: {{ $user->name }} — profile">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Customer profile: {{ $user->name }}</h1>
            <p class="small text-body-secondary mt-1">Review contact details and orders history for this customer.</p>
        </div>
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to customers
        </a>
    </div>

    <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">
        
        {{-- Profile Details --}}
        <div class="card admin-span-lg-4 bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
            <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Contact information</h3>
            
            <div class="d-flex flex-column align-items-center gap-2 py-3">
                <div class="admin-width-16 admin-height-16 rounded-pill bg-primary-subtle d-flex align-items-center justify-content-center fw-semibold text-primary fs-4 shadow-sm">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h4 class="text-body mt-2 admin-section-title">{{ $user->name }}</h4>
                <span class="text-body-secondary">Customer since {{ store_time($user->created_at)->format('M Y') }}</span>
            </div>

            <div class="admin-stack-3 fw-medium text-body">
                <div class="d-flex justify-content-between border-bottom border-light-subtle pb-2">
                    <span>Email Address:</span>
                    <span class="fw-semibold text-body font-monospace">{{ $user->email }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Phone Number:</span>
                    <span class="fw-semibold text-body">{{ $user->phone ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Order History --}}
        <div class="card admin-span-lg-8 bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
            <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Recent 10 orders</h3>

            @if($user->orders->isEmpty())
                <p class="small text-body-secondary text-center py-5">No orders placed by this customer yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle admin-table small">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="price num">Grand total</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->orders as $o)
                                <tr>
                                    <td class="text-body order-id">{{ $o->order_number }}</td>
                                    <td class="text-body-secondary">{{ store_time($o->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="small fw-semibold">{{ $o->payment_method_label }}</td>
                                    <td>
                                        <span class="admin-status-pill payment-status-{{ $o->payment_status }}">
                                            {{ ucfirst($o->payment_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-{{ $o->status }} small fw-semibold">
                                            {{ str_replace('_', ' ', $o->status) }}
                                        </span>
                                    </td>
                                    <td class="fw-semibold text-primary price num">{{ order_price($o, $o->grand_total) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.orders.show', $o->id) }}" class="d-inline-flex align-items-center justify-content-center px-2 py-1 bg-body-tertiary small fw-semibold text-body rounded-2">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

</x-admin-layout>
