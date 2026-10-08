<x-admin-layout title="Manage Categories">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Categories</h1>
            <p class="text-xs text-espresso-500 mt-1">Group your products into collections like products, etc.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.categories.create'))
<a href="{{ route('admin.categories.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add Category
        </a>
@endif
    </div>

    {{-- Categories List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($categories->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No categories defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.categories.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected categories? Categories with products will be skipped." class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
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
                            <th>Products Count</th>
                            <th>Status</th>
                            <th>Show on Home</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$cat->id" :label="'Select ' . $cat->name" /></td>
                                <td>
                                    <img src="{{ $cat->image_url ?? asset('images/placeholder.jpg') }}" alt="{{ $cat->name }}" class="w-10 h-10 rounded-lg object-cover border border-cream-100">
                                </td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $cat->name }}</td>
                                <td class="font-mono text-espresso-500">{{ $cat->slug }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $cat->display_order }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $cat->products_count }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $cat->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $cat->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $cat->show_on_homepage ? 'bg-blue-100 text-blue-750 font-bold' : 'bg-cream-200 text-espresso-400' }}">
                                        {{ $cat->show_on_homepage ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.categories.edit'))
<a href="{{ route('admin.categories.edit', $cat->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.categories.destroy', $cat->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this category?');">
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
