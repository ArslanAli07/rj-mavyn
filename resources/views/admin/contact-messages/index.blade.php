<x-admin-layout title="Manage Contact Messages">

    {{-- Header --}}
    <div class="mb-8 border-b border-cream-100 pb-4">
        <h1 class="text-2xl font-heading font-bold text-espresso-800">Contact Inquiries</h1>
        <p class="text-xs text-espresso-500 mt-1">Review and resolve customer questions or feedback received from the contact form.</p>
    </div>

    {{-- Messages List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($messages->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No contact messages received yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.contact-messages.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="resolve" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Resolve</button>
                    <button type="submit" name="bulk_action" value="reopen" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Reopen</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected message(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Sender</th>
                            <th>Contact Info</th>
                            <th>Subject</th>
                            <th>Inquiry Snippet</th>
                            <th>Date Received</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $m)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$m->id" :label="'Select message from ' . $m->name" /></td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $m->name }}</td>
                                <td>
                                    <span class="block text-espresso-750 font-semibold">{{ $m->email }}</span>
                                    <span class="text-espresso-400 text-[10px]">{{ $m->phone ?? 'No Phone' }}</span>
                                </td>
                                <td class="font-semibold text-espresso-800">{{ $m->subject }}</td>
                                <td class="text-espresso-600 max-w-xs truncate italic">"{{ $m->message }}"</td>
                                <td class="text-espresso-500">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $m->is_resolved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-750' }}">
                                        {{ $m->is_resolved ? 'Resolved' : 'Pending' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        {{-- Resolve Toggle --}}
                                        <form action="{{ route('admin.contact-messages.resolve', $m->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="px-2 py-1 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">
                                                {{ $m->is_resolved ? 'Reopen' : 'Resolve' }}
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.contact-messages.show', $m->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Read</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $messages->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
