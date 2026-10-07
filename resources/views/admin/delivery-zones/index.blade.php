<x-admin-layout title="Manage Delivery Zones">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Delivery Zones</h1>
            <p class="text-xs text-espresso-500 mt-1">Configure Sialkot delivery zones and their respective delivery charges.</p>
        </div>
        <a href="{{ route('admin.delivery-zones.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add Delivery Zone
        </a>
    </div>

    {{-- Delivery Zones List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($zones->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No delivery zones defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.delivery-zones.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected delivery zone(s)? Zones used on past orders will be skipped." class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Zone Name</th>
                            <th>Delivery Fee</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zones as $zone)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$zone->id" :label="'Select ' . $zone->name" /></td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $zone->name }}</td>
                                <td class="text-espresso-800 font-semibold text-sm">{{ format_price($zone->delivery_fee) }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $zone->display_order }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $zone->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <a href="{{ route('admin.delivery-zones.edit', $zone->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
                                        
                                        <form action="{{ route('admin.delivery-zones.destroy', $zone->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this delivery zone?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $zones->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
