<x-admin-layout title="Manage Occasions">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Occasions</h1>
            <p class="text-xs text-espresso-500 mt-1">Manage occasion collections like birthdays, weddings, baby showers, etc.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.occasions.create'))
<a href="{{ route('admin.occasions.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add Occasion
        </a>
@endif
    </div>

    {{-- Occasions List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($occasions->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No occasions defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.occasions.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected occasion(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($occasions as $occ)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$occ->id" :label="'Select ' . $occ->name" /></td>
                                <td>
                                    <img src="{{ $occ->image_url ?? asset('images/placeholder.jpg') }}" alt="{{ $occ->name }}" class="w-10 h-10 rounded-lg object-cover border border-cream-100">
                                </td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $occ->name }}</td>
                                <td class="font-mono text-espresso-500">{{ $occ->slug }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $occ->display_order }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $occ->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $occ->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.occasions.edit'))
<a href="{{ route('admin.occasions.edit', $occ->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.occasions.destroy', $occ->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this occasion?');">
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
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
