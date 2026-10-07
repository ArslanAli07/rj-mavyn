<x-admin-layout title="Manage Customers">

    {{-- Header --}}
    <div class="mb-8 border-b border-cream-100 pb-4">
        <h1 class="text-2xl font-heading font-bold text-espresso-800">Customers</h1>
        <p class="text-xs text-espresso-500 mt-1">Browse registered customer profiles, orders summary, and details.</p>
    </div>

    {{-- Customers List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($customers->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No customers registered yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.customers.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected customer(s)? Their saved addresses and carts will also be removed. Orders will be kept." class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Avatar</th>
                            <th>Name</th>
                            <th>Email Address</th>
                            <th>Phone</th>
                            <th>Total Orders Placed</th>
                            <th>Date Joined</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $c)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$c->id" :label="'Select ' . $c->name" /></td>
                                <td>
                                    <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center font-bold text-primary-700">
                                        {{ substr($c->name, 0, 1) }}
                                    </div>
                                </td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $c->name }}</td>
                                <td class="text-espresso-600 font-mono">{{ $c->email }}</td>
                                <td class="text-espresso-500">{{ $c->phone ?? 'N/A' }}</td>
                                <td class="text-espresso-600 font-bold">{{ $c->orders_count }}</td>
                                <td class="text-espresso-500">{{ $c->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <a href="{{ route('admin.customers.show', $c->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">View Profile</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $customers->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
