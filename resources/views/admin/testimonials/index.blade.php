<x-admin-layout title="Manage Testimonials">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Testimonials</h1>
            <p class="text-xs text-espresso-500 mt-1">Approve, feature, and manage customer testimonials showing on the home page.</p>
        </div>
        <a href="{{ route('admin.testimonials.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add Testimonial
        </a>
    </div>

    {{-- Testimonials List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($testimonials->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No testimonials defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.testimonials.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="approve" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Approve</button>
                    <button type="submit" name="bulk_action" value="hide" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Hide</button>
                    <button type="submit" name="bulk_action" value="feature" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Feature</button>
                    <button type="submit" name="bulk_action" value="unfeature" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Unfeature</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected testimonial(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Reviewer</th>
                            <th>Location</th>
                            <th>Rating</th>
                            <th>Review Message</th>
                            <th>Featured</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($testimonials as $t)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$t->id" :label="'Select review by ' . $t->customer_name" /></td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $t->customer_name }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $t->customer_location ?? 'Sialkot' }}</td>
                                <td>
                                    <div class="flex text-yellow-500 font-bold">
                                        {{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}
                                    </div>
                                </td>
                                <td class="text-espresso-700 italic max-w-sm truncate">"{{ $t->review }}"</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $t->is_featured ? 'bg-primary-100 text-primary-750' : 'bg-cream-100 text-espresso-400' }}">
                                        {{ $t->is_featured ? 'Featured' : 'Regular' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $t->is_approved ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $t->is_approved ? 'Approved' : 'Hidden' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        {{-- Approve Toggle --}}
                                        <form action="{{ route('admin.testimonials.approve', $t->id) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="px-2 py-1 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">
                                                {{ $t->is_approved ? 'Hide' : 'Approve' }}
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.testimonials.edit', $t->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
                                        
                                        <form action="{{ route('admin.testimonials.destroy', $t->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this review?');">
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
            {{ $testimonials->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
