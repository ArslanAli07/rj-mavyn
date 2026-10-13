<x-admin-layout title="Manage contact messages">

    {{-- Header --}}
    <div class="mb-5 border-bottom border-light-subtle pb-3">
        <h1 class="text-body admin-page-title">Contact inquiries</h1>
        <p class="small text-body-secondary mt-1">Review and resolve customer questions or feedback received from the contact form.</p>
    </div>

    {{-- Messages List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($messages->isEmpty())
            <p class="small text-body-secondary text-center py-5">No contact messages received yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.contact-messages.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="resolve" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Resolve</button>
                    <button type="submit" name="bulk_action" value="reopen" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Reopen</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected message(s)?" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Sender</th>
                            <th>Contact info</th>
                            <th>Subject</th>
                            <th>Inquiry snippet</th>
                            <th>Date received</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $m)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$m->id" :label="'Select message from ' . $m->name" /></td>
                                <td class="fw-semibold text-body small">{{ $m->name }}</td>
                                <td>
                                    <span class="d-block text-body fw-semibold">{{ $m->email }}</span>
                                    <span class="text-body-secondary small">{{ $m->phone ?? 'No Phone' }}</span>
                                </td>
                                <td class="fw-semibold text-body">{{ $m->subject }}</td>
                                <td class="text-body-secondary admin-max-width-xs text-truncate fst-italic">"{{ $m->message }}"</td>
                                <td class="text-body-secondary">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $m->is_resolved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }}">
                                        {{ $m->is_resolved ? 'Resolved' : 'Pending' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        {{-- Resolve Toggle --}}
                                        <form action="{{ route('admin.contact-messages.resolve', $m->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary px-2 py-1 bg-body-tertiary rounded-2 fw-semibold small">
                                                {{ $m->is_resolved ? 'Reopen' : 'Resolve' }}
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.contact-messages.show', $m->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Read</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $messages->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
