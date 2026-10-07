<x-admin-layout title="Manage Newsletter Subscribers">

    {{-- Header Actions --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Newsletter Subscribers</h1>
            <p class="text-xs text-espresso-500 mt-1">Export email listings, manage subscriptions, or remove subscribers.</p>
        </div>
        <a href="{{ route('admin.newsletter.export') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            Export CSV
        </a>
    </div>

    {{-- Stats Banner --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
        <div class="bg-white border border-cream-150 rounded-2xl p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center text-xl">📧</div>
            <div>
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider">Total Subscribers</span>
                <span class="text-xl sm:text-2xl font-bold text-espresso-800 block mt-0.5">{{ $total }}</span>
            </div>
        </div>

        <div class="bg-white border border-cream-150 rounded-2xl p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center text-xl">✓</div>
            <div>
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider">Active Subscriptions</span>
                <span class="text-xl sm:text-2xl font-bold text-espresso-800 block mt-0.5">{{ $active }}</span>
            </div>
        </div>
    </div>

    {{-- Subscribers Table --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($subscribers->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No subscribers found.</p>
        @else
            <x-admin.bulk-root :action="route('admin.newsletter.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="unsubscribe" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Unsubscribe</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Remove :count selected subscriber(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Email Address</th>
                            <th>Name</th>
                            <th>Subscribed At</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subscribers as $s)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$s->id" :label="'Select ' . $s->email" /></td>
                                <td class="font-bold text-espresso-800 font-mono text-sm">{{ $s->email }}</td>
                                <td class="text-espresso-750 font-semibold">{{ $s->name ?? 'N/A' }}</td>
                                <td class="text-espresso-500">{{ $s->subscribed_at ? $s->subscribed_at->format('Y-m-d H:i') : $s->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $s->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $s->is_active ? 'Active' : 'Unsubscribed' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <form action="{{ route('admin.newsletter.destroy', $s->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this subscriber?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $subscribers->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
