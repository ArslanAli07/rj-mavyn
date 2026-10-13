<x-admin-layout title="Manage products">

    {{-- Header Actions --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Products</h1>
            <p class="small text-body-secondary mt-1">Add, update, and manage your product catalog.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.products.create'))
<a href="{{ route('admin.products.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add product
        </a>
@endif
    </div>

    {{-- Filters and Search Bar --}}
    <x-admin.collapsible-filters title="Search & filters" :active="request()->anyFilled(['search', 'category_id'])">
        <form action="{{ route('admin.products.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-4">
            {{-- Search Input --}}
            <div class="flex-grow-1 admin-min-width-search">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or SKU..."
                       class="form-control w-100 px-3 py-2 border border-light-subtle rounded-2 small">
            </div>

            {{-- Category Filter --}}
            <div class="w-100 admin-width-sm-48">
                <select name="category_id" onchange="this.form.submit()"
                        class="form-select w-100 px-3 py-2 border border-light-subtle rounded-2 small bg-body-tertiary">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary py-2 px-4 small rounded-2 flex-shrink-0">Search</button>
            @if(request()->anyFilled(['search', 'category_id']))
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary py-2 px-3 small rounded-2 text-center flex-shrink-0">Reset</a>
            @endif
        </form>
    </x-admin.collapsible-filters>

    {{-- Products Table --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($products->isEmpty())
            <p class="small text-body-secondary text-center py-5">No products found in catalog.</p>
        @else
            <x-admin.bulk-root :action="route('admin.products.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary px-2 py-2 bg-body-tertiary rounded-2 fw-semibold small">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected product(s)? Image files will also be removed." class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold small">Delete</button>
                </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table admin-table-with-thumbnails small">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Image</th>
                            <th>Product details</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th class="price num">Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Badges</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$p->id" :label="'Select ' . $p->title" /></td>
                                <td>
                                    <img src="{{ $p->main_image_url }}" alt="{{ $p->title }}" class="admin-width-10 admin-height-10 rounded-2 object-fit-cover border border-light-subtle flex-shrink-0">
                                </td>
                                <td>
                                    <span class="fw-semibold text-body d-block">{{ $p->title }}</span>
                                    @if($p->weight) <span class="text-body-secondary small">Weight: {{ $p->weight }}</span> @endif
                                </td>
                                <td class="font-monospace text-body-secondary sku">{{ $p->sku }}</td>
                                <td class="text-body-secondary fw-medium">{{ $p->category?->name }}</td>
                                <td class="text-body-secondary fw-semibold">{{ $p->display_order }}</td>
                                <td class="price num">
                                    @if($p->is_on_sale)
                                        <span class="text-body fw-semibold d-block price num">{{ format_price($p->effective_price) }}</span>
                                        <span class="small text-body-secondary text-decoration-line-through price num">{{ format_price($p->price) }}</span>
                                    @else
                                        <span class="fw-semibold text-body price num">{{ format_price($p->price) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p->inventory_mode === 'untracked')
                                        <span class="text-body-secondary">Untracked</span>
                                    @elseif($p->inventory_mode === 'variant')
                                        <span class="text-body-secondary">Per variant</span>
                                    @else
                                        <span class="fw-semibold {{ $p->stock_quantity - $p->reserved_quantity <= 0 ? 'text-danger' : ($p->stock_quantity - $p->reserved_quantity <= 5 ? 'text-warning' : 'text-body') }}">
                                            {{ $p->stock_quantity - $p->reserved_quantity }} available
                                        </span>
                                        <span class="small text-body-secondary">{{ $p->reserved_quantity }} reserved</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $p->is_active ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $p->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="admin-stack-0-5">
                                    @if($p->is_featured) <span class="badge bg-primary-subtle text-primary small px-1 py-1">Featured</span> @endif
                                    @if($p->is_bestseller) <span class="badge bg-body-tertiary text-body-secondary small px-1 py-1">Best</span> @endif
                                    @if($p->is_new_arrival) <span class="badge bg-success-subtle text-success small px-1 py-1">New</span> @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.products.edit'))
<a href="{{ route('admin.products.edit', $p->id) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.products.destroy', $p->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this product? All image files will be removed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $products->links('pagination::bootstrap-5') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
