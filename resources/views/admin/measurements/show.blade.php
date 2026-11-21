<x-admin-layout title="Measurements from {{ $submission->name }}">
    <div class="mb-4 d-flex flex-wrap gap-3 align-items-end justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Measurements: {{ $submission->name }}</h1>
            <p class="small text-body-secondary mt-1">{{ $submission->garment_label }} · {{ ucfirst($submission->fit) }} · received {{ store_time($submission->created_at)->format('M d, Y h:i A T') }}</p>
        </div>
        <a href="{{ route('admin.measurements.index') }}" class="small fw-semibold">← Back to measurements</a>
    </div>

    <div class="d-grid admin-cols-1 admin-cols-lg-3 gap-4">
        <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-span-lg-2">
            <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Measurements ({{ $submission->unit === 'cm' ? 'cm, kg' : 'inches, lb' }})</h3>
            @include('admin.measurements._table')
        </div>

        <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
            <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Order &amp; review</h3>
            <div class="small">
                <span class="text-body-secondary d-block">Customer</span>
                <span class="fw-semibold">{{ $submission->name }}</span> · {{ $submission->email }}
            </div>
            <div class="small">
                <span class="text-body-secondary d-block">Order number entered</span>
                <span class="fw-semibold">{{ $submission->order_reference }}</span>
            </div>
            <div class="small">
                <span class="text-body-secondary d-block">Linked order</span>
                @if($submission->order)
                    <a href="{{ route('admin.orders.show', $submission->order) }}" class="fw-semibold">{{ $submission->order->order_number }}</a>
                    @if(mb_strtolower($submission->order->customer_email) !== mb_strtolower($submission->email))
                        <span class="d-block text-warning-emphasis">Email differs from the order's email ({{ $submission->order->customer_email }}).</span>
                    @endif
                @else
                    <span class="badge bg-warning-subtle text-warning-emphasis">Not linked</span>
                    <span class="d-block text-body-secondary mt-1">The order number and email did not both match an order.</span>
                @endif
            </div>
            @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.measurements.update'))
                <form method="POST" action="{{ route('admin.measurements.update', $submission) }}" class="admin-stack-3">
                    @csrf @method('PATCH')
                    <label class="small fw-semibold" for="order_number">{{ $submission->order ? 'Move to another order' : 'Link to order' }}</label>
                    <input id="order_number" name="order_number" class="form-control form-control-sm" placeholder="ORD-…" value="{{ old('order_number') }}">
                    @error('order_number')<span class="small text-danger">{{ $message }}</span>@enderror
                    <button class="btn btn-sm btn-outline-secondary">Save link</button>
                </form>
                <form method="POST" action="{{ route('admin.measurements.update', $submission) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="reviewed" value="{{ $submission->reviewed_at ? 0 : 1 }}">
                    <button class="btn btn-sm {{ $submission->reviewed_at ? 'btn-outline-secondary' : 'btn-primary' }}">{{ $submission->reviewed_at ? 'Mark as not reviewed' : 'Mark as reviewed' }}</button>
                    @if($submission->reviewed_at)<span class="small text-body-secondary d-block mt-1">Reviewed {{ store_time($submission->reviewed_at)->format('M d, Y h:i A') }}</span>@endif
                </form>
            @endif
        </div>
    </div>
</x-admin-layout>
