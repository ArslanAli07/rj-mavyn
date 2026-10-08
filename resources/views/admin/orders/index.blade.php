<x-admin-layout title="Manage Orders">

    {{-- Header --}}
    <div class="mb-8 border-b border-cream-100 pb-4">
        <h1 class="text-2xl font-heading font-bold text-espresso-800">Orders</h1>
        <p class="text-xs text-espresso-500 mt-1">Review orders, update statuses, verify manual transfers, and print invoices.</p>
    </div>

    {{-- Search and Filters --}}
    <x-admin.collapsible-filters title="Search & filters" :active="request()->anyFilled(['search', 'status', 'payment_method'])">
        <form action="{{ route('admin.orders.index') }}" method="GET" class="flex flex-wrap items-center gap-4">
            
            {{-- Search input --}}
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search order #, customer name, email..."
                       class="w-full px-4 py-2 border border-cream-250 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400">
            </div>

            {{-- Status filter --}}
            <div class="w-full sm:w-44">
                <select name="status" onchange="this.form.submit()"
                        class="w-full px-3 py-2 border border-cream-250 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400 bg-cream-50/20">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $key => $lbl)
                        <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Payment method filter --}}
            <div class="w-full sm:w-44">
                <select name="payment_method" onchange="this.form.submit()"
                        class="w-full px-3 py-2 border border-cream-250 rounded-xl text-xs focus:ring-primary-300 focus:border-primary-400 bg-cream-50/20">
                    <option value="">All Payments</option>
                    @foreach($methods as $key => $lbl)
                        <option value="{{ $key }}" {{ request('payment_method') === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary py-2 px-6 text-xs rounded-xl shrink-0">Filter</button>
            @if(request()->anyFilled(['search', 'status', 'payment_method']))
                <a href="{{ route('admin.orders.index') }}" class="btn-secondary py-2 px-4 text-xs rounded-xl text-center shrink-0">Reset</a>
            @endif
        </form>
    </x-admin.collapsible-filters>

    {{-- Orders list --}}
    <div id="admin-orders-live" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        @if($orders->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No orders found.</p>
        @else
            <x-admin.bulk-root :action="route('admin.orders.bulk')">
                <x-slot:actions>
                    <label class="text-xs"><input type="checkbox" name="send_email" value="1"> Email customers when status changes</label>
                    <select name="status" class="px-3 py-1.5 border border-cream-250 rounded-lg text-xs bg-white focus:ring-primary-300 focus:border-primary-400">
                        <option value="">Set order status…</option>
                        @foreach($statuses as $key => $lbl)
                            <option value="{{ $key }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <button type="submit" name="bulk_action" value="set_status" data-bulk-requires="status" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Apply status</button>
                    <select name="payment_status" class="px-3 py-1.5 border border-cream-250 rounded-lg text-xs bg-white focus:ring-primary-300 focus:border-primary-400">
                        <option value="">Set payment…</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                    <button type="submit" name="bulk_action" value="set_payment" data-bulk-requires="payment_status" class="px-2.5 py-1.5 bg-cream-100 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Apply payment</button>
                    <button type="submit" name="bulk_action" value="delete" data-bulk-confirm="Permanently delete :count selected order(s)? This cannot be undone." class="px-2.5 py-1.5 bg-red-50 text-red-650 hover:bg-red-600 hover:text-white rounded-lg transition-colors font-bold text-[10px] uppercase">Delete</button>
                </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th class="admin-bulk-col"><x-admin.bulk-checkbox all /></th>
                            <th>Order Number</th>
                            <th>Customer Info</th>
                            <th>Date Placed</th>
                            <th>Payment Details</th>
                            <th>Order Status</th>
                            <th>Grand Total</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $o)
                            <tr @class(['order-pending' => $o->status === 'pending'])>
                                <td class="admin-bulk-col"><x-admin.bulk-checkbox :value="$o->id" :label="'Select order ' . $o->order_number" /></td>
                                <td class="font-bold text-espresso-800 text-sm">{{ $o->order_number }}</td>
                                <td>
                                    <span class="font-bold text-espresso-800 block">{{ $o->customer_name }}</span>
                                    <span class="text-espresso-400 text-[10px]">{{ $o->customer_phone }} · {{ $o->customer_email }}</span>
                                </td>
                                <td class="text-espresso-500">{{ $o->created_at->format('M d, Y h:i A') }}</td>
                                <td>
                                    <span class="text-[10px] font-bold text-espresso-700 uppercase block">{{ $o->payment_method }}</span>
                                    <span class="badge text-[8px] uppercase px-1.5 py-0.5 rounded-full font-bold mt-1
                                                 {{ $o->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-750' }}">
                                        {{ $o->payment_status }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-{{ $o->status }} text-[9px] uppercase font-bold">
                                        {{ $statuses[$o->status] ?? $o->status }}
                                    </span>
                                </td>
                                <td class="font-bold text-primary-650 text-sm">{{ format_price($o->grand_total) }}</td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <a href="{{ route('admin.orders.show', $o->id) }}" class="px-2.5 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white rounded-lg transition-colors font-bold">Manage</a>
                                        
                                        <a href="{{ route('admin.orders.invoice', $o->id) }}" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-espresso-50 text-espresso-800 hover:bg-espresso-800 hover:text-white rounded-lg transition-colors font-bold" title="Print Invoice">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            Print Invoice
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $orders->links('components.pagination') }}
            </x-admin.bulk-root>
        @endif
    </div>

</x-admin-layout>
