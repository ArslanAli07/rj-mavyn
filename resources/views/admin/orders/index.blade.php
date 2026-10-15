<x-admin-layout title="Manage orders">

    {{-- Header --}}
    <div class="mb-5 border-bottom border-light-subtle pb-3">
        <h1 class="text-body admin-page-title">Orders</h1>
        <p class="small text-body-secondary mt-1">Review orders, update statuses, verify manual transfers, and print invoices.</p>
    </div>

    {{-- Search and Filters --}}
    <x-admin.collapsible-filters title="Search & filters" :active="request()->anyFilled(['search', 'status', 'payment_method'])">
        <form action="{{ route('admin.orders.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-4">
            
            {{-- Search input --}}
            <div class="flex-grow-1 admin-min-width-search">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search order #, customer name, email..."
                       class="form-control w-100 px-3 py-2 border border-light-subtle rounded-2 small">
            </div>

            {{-- Status filter --}}
            <div class="w-100 admin-width-sm-44">
                <select name="status" onchange="this.form.submit()"
                        class="form-select w-100 px-3 py-2 border border-light-subtle rounded-2 small bg-body-tertiary">
                    <option value="">All statuses</option>
                    @foreach($statuses as $key => $lbl)
                        <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Payment method filter --}}
            <div class="w-100 admin-width-sm-44">
                <select name="payment_method" onchange="this.form.submit()"
                        class="form-select w-100 px-3 py-2 border border-light-subtle rounded-2 small bg-body-tertiary">
                    <option value="">All payments</option>
                    @foreach($methods as $key => $lbl)
                        <option value="{{ $key }}" {{ request('payment_method') === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary py-2 px-4 small rounded-2 flex-shrink-0">Filter</button>
            @if(request()->anyFilled(['search', 'status', 'payment_method']))
                <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary py-2 px-3 small rounded-2 text-center flex-shrink-0">Reset</a>
            @endif
        </form>
    </x-admin.collapsible-filters>

    {{-- Orders list --}}
    <div id="admin-orders-live" class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($orders->isEmpty())
            <p class="small text-body-secondary text-center py-5">No orders found.</p>
        @else
            <x-admin.bulk-root :action="route('admin.orders.bulk')" data-admin-order-bulk>
                <x-slot:actions>
                    <label class="small"><input type="checkbox" name="send_email" value="1" class="form-check-input"> Email customers when status changes</label>
                    <select name="status" class="form-select px-3 py-2 border border-light-subtle rounded-2 small bg-white">
                        <option value="">Set order status…</option>
                        @foreach($statuses as $key => $lbl)
                            <option value="{{ $key }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <button type="submit" name="bulk_action" value="set_status" data-bulk-requires="status" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Apply status</button>
                    <select name="payment_status" class="form-select px-3 py-2 border border-light-subtle rounded-2 small bg-white">
                        <option value="">Set payment…</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                    <button type="submit" name="bulk_action" value="set_payment" data-bulk-requires="payment_status" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Apply payment</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Permanently delete :count selected order(s)? This cannot be undone." class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Order number</th>
                            <th>Customer info</th>
                            <th>Date placed</th>
                            <th>Payment details</th>
                            <th>Order status</th>
                            <th class="price num">Grand total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr @class(['order-pending' => $o->status === 'pending'])>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$o->id" :label="'Select order ' . $o->order_number" /></td>
                                <td class="text-body order-id">{{ $o->order_number }}</td>
                                <td>
                                    <span class="fw-semibold text-body d-block">{{ $o->customer_name }}</span>
                                    <span class="text-body-secondary small">{{ $o->customer_phone }} · {{ $o->customer_email }}</span>
                                </td>
                                <td class="text-body-secondary">{{ $o->created_at->format('M d, Y h:i A') }}</td>
                                <td>
                                    <span class="fw-semibold text-body d-block">{{ $o->payment_method_label }}</span>
                                    <span class="admin-status-pill payment-status-{{ $o->payment_status }}">
                                        {{ ucfirst($o->payment_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-{{ $o->status }} small fw-semibold">
                                        {{ ucfirst(str_replace('_', ' ', $o->status)) }}
                                    </span>
                                </td>
                                <td class="fw-semibold text-primary small price num">{{ order_price($o, $o->grand_total) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        <a href="{{ route('admin.orders.show', $o->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Manage</a>
                                        
                                        <a href="{{ route('admin.orders.invoice', $o->id) }}" target="_blank" rel="noopener noreferrer"
                                           class="d-inline-flex align-items-center gap-2 px-2 py-2 bg-body-tertiary text-body rounded-2 fw-semibold" title="Print Invoice">
                                            <svg class="admin-width-3-5 admin-height-3-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            Print Invoice
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $orders->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
