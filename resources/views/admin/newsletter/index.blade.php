<x-admin-layout title="Manage newsletter subscribers">

    {{-- Header Actions --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Newsletter subscribers</h1>
            <p class="small text-body-secondary mt-1">Export email listings, manage subscriptions, or remove subscribers.</p>
        </div>
        <a href="{{ route('admin.newsletter.export') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            Export CSV
        </a>
    </div>

    {{-- Stats Banner --}}
    <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4 mb-5">
        <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm d-flex align-items-center gap-4 flex-row">
            <div class="admin-width-12 admin-height-12 rounded-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-4">📧</div>
            <div>
                <span class="text-body-secondary small fw-semibold admin-letter-spacing">Total subscribers</span>
                <span class="fs-4 admin-font-sm-2xl fw-semibold text-body d-block mt-1">{{ $total }}</span>
            </div>
        </div>

        <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm d-flex align-items-center gap-4 flex-row">
            <div class="admin-width-12 admin-height-12 rounded-2 bg-success-subtle text-success d-flex align-items-center justify-content-center fs-4">✓</div>
            <div>
                <span class="text-body-secondary small fw-semibold admin-letter-spacing">Active subscriptions</span>
                <span class="fs-4 admin-font-sm-2xl fw-semibold text-body d-block mt-1">{{ $active }}</span>
            </div>
        </div>
    </div>

    {{-- Subscribers Table --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($subscribers->isEmpty())
            <p class="small text-body-secondary text-center py-5">No subscribers found.</p>
        @else
            <x-admin.bulk-root :action="route('admin.newsletter.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="unsubscribe" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Unsubscribe</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Remove :count selected subscriber(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Email address</th>
                            <th>Name</th>
                            <th>Subscribed at</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subscribers as $s)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$s->id" :label="'Select ' . $s->email" /></td>
                                <td class="fw-semibold text-body font-monospace small">{{ $s->email }}</td>
                                <td class="text-body fw-semibold">{{ $s->name ?? 'N/A' }}</td>
                                <td class="text-body-secondary">{{ $s->subscribed_at ? store_time($s->subscribed_at)->format('Y-m-d H:i') : store_time($s->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $s->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $s->is_active ? 'Active' : 'Unsubscribed' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        <form action="{{ route('admin.newsletter.destroy', $s->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this subscriber?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $subscribers->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
