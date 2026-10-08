<x-admin-layout title="Manage FAQs">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">FAQs</h1>
            <p class="text-xs text-espresso-500 mt-1">Add or update frequently asked questions shown on the customer FAQ page.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.faqs.create'))
<a href="{{ route('admin.faqs.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add FAQ
        </a>
@endif
    </div>

    {{-- FAQs List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($faqs->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No FAQs defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.faqs.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected FAQ(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Category</th>
                            <th>Question</th>
                            <th>Answer Details</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($faqs as $f)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$f->id" :label="'Select FAQ'" /></td>
                                <td class="font-bold text-espresso-800 uppercase tracking-wide">{{ $f->category ?? 'General' }}</td>
                                <td class="font-bold text-espresso-800 text-sm max-w-xs truncate">{{ $f->question }}</td>
                                <td class="text-espresso-600 max-w-sm truncate">{{ $f->answer }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $f->display_order }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $f->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $f->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.faqs.edit'))
<a href="{{ route('admin.faqs.edit', $f->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.faqs.destroy', $f->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
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
            {{ $faqs->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
