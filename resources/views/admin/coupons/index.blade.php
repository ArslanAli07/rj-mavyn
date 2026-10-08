<x-admin-layout title="Manage Coupons">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Coupons</h1>
            <p class="text-xs text-espresso-500 mt-1">Manage promotional discount codes for your customers.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.coupons.create'))
<a href="{{ route('admin.coupons.create') }}" class="btn-gold py-2 px-5 text-xs font-semibold">
            + Add Coupon
        </a>
@endif
    </div>

    {{-- Coupons List --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($coupons->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No coupons defined yet.</p>
        @else
            <x-admin.bulk-root :action="route('admin.coupons.bulk')">
                <x-slot:actions>
                    <button type="submit" name="bulk_action" value="activate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Activate</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Deactivate</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Delete :count selected coupon(s)?" class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Min Spend</th>
                            <th>Max Discount</th>
                            <th>Usage</th>
                            <th>Starts</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coupons as $c)
                            <tr>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$c->id" :label="'Select coupon ' . $c->code" /></td>
                                <td class="font-bold text-espresso-800 font-mono text-sm uppercase">{{ $c->code }}</td>
                                <td class="font-bold text-espresso-700">
                                    @if($c->type === 'fixed')
                                        Rs. {{ number_format($c->amount, 0) }}
                                    @else
                                        {{ (int) $c->amount }}%
                                    @endif
                                </td>
                                <td>Rs. {{ number_format($c->minimum_order ?? 0, 0) }}</td>
                                <td>{{ $c->maximum_discount ? 'Rs. ' . number_format($c->maximum_discount, 0) : 'N/A' }}</td>
                                <td>
                                    {{ $c->usage_count }} / {{ $c->usage_limit ?? '∞' }}
                                </td>
                                <td class="text-espresso-500">{{ $c->starts_at ? $c->starts_at->format('Y-m-d') : 'Immediate' }}</td>
                                <td class="text-espresso-500">{{ $c->expires_at ? $c->expires_at->format('Y-m-d') : 'Never' }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $c->is_active && $c->is_valid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-750' }}">
                                        {{ $c->is_active && $c->is_valid ? 'Valid' : 'Expired/Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.coupons.edit'))
<a href="{{ route('admin.coupons.edit', $c->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Edit</a>
@endif
                                        
                                        <form action="{{ route('admin.coupons.destroy', $c->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this coupon?');">
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
            {{ $coupons->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
