<x-admin-layout title="Customer measurements">
    <div class="mb-4 border-bottom border-light-subtle pb-3 d-flex flex-wrap gap-3 align-items-end justify-content-between">
        <div>
            <h1 class="text-body admin-page-title">Customer measurements</h1>
            <p class="small text-body-secondary mt-1">Body measurements sent through the measurement form for Custom Size and Custom Made orders. Check them before production starts.</p>
        </div>
        <div class="d-flex gap-2 small">
            <a href="{{ route('admin.measurements.index') }}" class="btn btn-sm {{ ! $filter ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
            <a href="{{ route('admin.measurements.index', ['filter' => 'unreviewed']) }}" class="btn btn-sm {{ $filter === 'unreviewed' ? 'btn-primary' : 'btn-outline-secondary' }}">Not reviewed ({{ $unreviewed }})</a>
            <a href="{{ route('admin.measurements.index', ['filter' => 'unmatched']) }}" class="btn btn-sm {{ $filter === 'unmatched' ? 'btn-primary' : 'btn-outline-secondary' }}">Not linked to an order ({{ $unmatched }})</a>
        </div>
    </div>

    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($submissions->isEmpty())
            <p class="small text-body-secondary text-center py-5">No measurement submissions yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr><th>Received</th><th>Customer</th><th>Order</th><th>Garment</th><th>Units</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $s)
                            <tr>
                                <td class="text-body-secondary">{{ store_time($s->created_at)->format('M d, Y h:i A') }}</td>
                                <td><span class="d-block fw-semibold text-body">{{ $s->name }}</span><span class="text-body-secondary">{{ $s->email }}</span></td>
                                <td>
                                    @if($s->order)
                                        <a href="{{ route('admin.orders.show', $s->order) }}" class="fw-semibold">{{ $s->order->order_number }}</a>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Not linked</span>
                                        <span class="d-block text-body-secondary">Entered: {{ $s->order_reference }}</span>
                                    @endif
                                </td>
                                <td>{{ $s->garment_label }} · {{ ucfirst($s->fit) }}</td>
                                <td>{{ $s->unit === 'cm' ? 'cm / kg' : 'in / lb' }}</td>
                                <td>
                                    @if($s->reviewed_at)<span class="badge bg-success-subtle text-success">Reviewed</span>@else<span class="badge bg-primary-subtle text-primary">New</span>@endif
                                    @if($s->photos)<span class="badge bg-body-tertiary text-body-secondary">{{ count($s->photos) }} photo{{ count($s->photos) === 1 ? '' : 's' }}</span>@endif
                                </td>
                                <td class="text-end"><a href="{{ route('admin.measurements.show', $s) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $submissions->links() }}</div>
        @endif
    </div>
</x-admin-layout>
