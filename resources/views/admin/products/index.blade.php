<x-admin-layout title="Manage Products">

    {{-- Header Actions --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Products</h1>
            <p class="text-xs text-espresso-500 mt-1">Add, update, and manage your product catalog.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.products.create'))
<a href="{{ route('admin.products.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add Product
        </a>
@endif
    </div>

    {{-- Filters and Search Bar --}}
    <x-admin.collapsible-filters title="Search & filters" :active="request()->anyFilled(['search', 'category_id'])">
        <form action="{{ route('admin.products.index') }}" method="GET" class="flex flex-wrap items-center gap-4">
            {{-- Search Input --}}
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or SKU..."
                       class="w-full px-4 py-2 border border-cream-250 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400">
            </div>

            {{-- Category Filter --}}
            <div class="w-full sm:w-48">
                <select name="category_id" onchange="this.form.submit()"
                        class="w-full px-3 py-2 border border-cream-250 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400 bg-cream-50/20">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary py-2 px-6 text-xs rounded-xl shrink-0">Search</button>
            @if(request()->anyFilled(['search', 'category_id']))
                <a href="{{ route('admin.products.index') }}" class="btn-secondary py-2 px-4 text-xs rounded-xl text-center shrink-0">Reset</a>
            @endif
        </form>
    </x-admin.collapsible-filters>

    {{-- Products Table --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($products->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No products found in catalog.</p>
        @else
            <x-admin.bulk-root :action="route('admin.products.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected product(s)? Image files will also be removed." class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Image</th>
                            <th>Product Details</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Badges</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$p->id" :label="'Select ' . $p->title" /></td>
                                <td>
                                    <img src="{{ $p->main_image_url }}" alt="{{ $p->title }}" class="w-10 h-10 rounded-lg object-cover border border-cream-100 shrink-0">
                                </td>
                                <td>
                                    <span class="font-bold text-espresso-800 block text-sm">{{ $p->title }}</span>
                                    @if($p->weight) <span class="text-espresso-400 text-[10px]">Weight: {{ $p->weight }}</span> @endif
                                </td>
                                <td class="font-mono text-espresso-500">{{ $p->sku }}</td>
                                <td class="text-espresso-600 font-medium">{{ $p->category?->name }}</td>
                                <td class="text-espresso-600 font-semibold">{{ $p->display_order }}</td>
                                <td>
                                    @if($p->is_on_sale)
                                        <span class="text-red-650 font-bold block">{{ format_price($p->effective_price) }}</span>
                                        <span class="text-[10px] text-espresso-400 line-through">{{ format_price($p->price) }}</span>
                                    @else
                                        <span class="font-bold text-espresso-800">{{ format_price($p->price) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p->inventory_mode === 'untracked')
                                        <span class="text-espresso-600">Untracked</span>
                                    @elseif($p->inventory_mode === 'variant')
                                        <span class="text-espresso-600">Per variant</span>
                                    @else
                                        <span class="font-semibold {{ $p->stock_quantity - $p->reserved_quantity <= 5 ? 'text-red-600' : 'text-espresso-700' }}">
                                            {{ $p->stock_quantity - $p->reserved_quantity }} available
                                        </span>
                                        <span class="text-xs text-espresso-500">{{ $p->reserved_quantity }} reserved</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $p->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $p->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="space-y-0.5">
                                    @if($p->is_featured) <span class="badge-featured text-[8px] px-1 py-0.5">Featured</span> @endif
                                    @if($p->is_bestseller) <span class="badge-bestseller text-[8px] px-1 py-0.5">Best</span> @endif
                                    @if($p->is_new_arrival) <span class="badge-new text-[8px] px-1 py-0.5">New</span> @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.products.edit'))
<a href="{{ route('admin.products.edit', $p->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.products.destroy', $p->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this product? All image files will be removed.');">
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
            {{ $products->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
