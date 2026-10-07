<x-admin-layout title="Manage Hero Slides">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Homepage Hero Slides</h1>
            <p class="text-xs text-espresso-500 mt-1">Upload and manage image slides for the homepage carousel.</p>
        </div>
        <a href="{{ route('admin.home-slides.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add New Slide
        </a>
    </div>

    {{-- Slides List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($slides->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No hero slides defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.home-slides.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected slide(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Desktop Image</th>
                            <th>Mobile Image</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slides as $slide)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$slide->id" label="Select slide" /></td>
                                <td>
                                    <img src="{{ $slide->image_url }}" alt="Hero slide" class="w-24 h-auto rounded-lg border border-cream-100">
                                </td>
                                <td>
                                    @if($slide->mobile_image_path || $slide->mobile_image_asset_id)
                                        <img src="{{ $slide->mobile_image_url }}" alt="Mobile slide" class="w-16 h-auto rounded-lg border border-cream-100">
                                    @else
                                        <span class="text-espresso-400 italic">Uses desktop</span>
                                    @endif
                                </td>
                                <td class="text-espresso-600 font-semibold">{{ $slide->display_order }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $slide->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $slide->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <a href="{{ route('admin.home-slides.edit', $slide->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>

                                        <form action="{{ route('admin.home-slides.destroy', $slide->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this hero slide?');">
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
