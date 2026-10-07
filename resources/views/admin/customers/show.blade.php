<x-admin-layout title="Customer: {{ $user->name }} — Profile">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Customer Profile: {{ $user->name }}</h1>
            <p class="text-xs text-espresso-500 mt-1">Review contact details and orders history for this customer.</p>
        </div>
        <a href="{{ route('admin.customers.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Customers
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {{-- Profile Details --}}
        <div class="lg:col-span-4 bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4 text-xs">
            <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Contact Information</h3>
            
            <div class="flex flex-col items-center gap-2 py-4">
                <div class="w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center font-bold text-primary-700 text-xl shadow-sm">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h4 class="font-heading font-bold text-espresso-800 text-base mt-2">{{ $user->name }}</h4>
                <span class="text-espresso-400">Customer since {{ $user->created_at->format('M Y') }}</span>
            </div>

            <div class="space-y-3 font-medium text-espresso-750">
                <div class="flex justify-between border-b border-cream-50 pb-2">
                    <span>Email Address:</span>
                    <span class="font-bold text-espresso-850 font-mono">{{ $user->email }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Phone Number:</span>
                    <span class="font-bold text-espresso-850">{{ $user->phone ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Order History --}}
        <div class="lg:col-span-8 bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
            <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Recent 10 Orders</h3>

            @if($user->orders->isEmpty())
                <p class="text-xs text-espresso-400 text-center py-8">No orders placed by this customer yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table text-xs">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Grand Total</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->orders as $o)
                                <tr>
                                    <td class="font-bold text-espresso-800">{{ $o->order_number }}</td>
                                    <td class="text-espresso-500">{{ $o->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="uppercase text-[10px] font-semibold">{{ $o->payment_method }}</td>
                                    <td>
                                        <span class="badge text-[8px] uppercase px-1.5 py-0.5 rounded-full font-bold
                                                     {{ $o->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-750' }}">
                                            {{ $o->payment_status }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-{{ $o->status }} text-[9px] uppercase font-bold">
                                            {{ str_replace('_', ' ', $o->status) }}
                                        </span>
                                    </td>
                                    <td class="font-bold text-primary-650">{{ format_price($o->grand_total) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.orders.show', $o->id) }}" class="inline-flex items-center justify-center px-2 py-1 bg-cream-100 text-[10px] font-bold text-espresso-805 hover:bg-primary-500 hover:text-white rounded-lg transition-colors">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

</x-admin-layout>
