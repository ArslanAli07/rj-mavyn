<x-app-layout title="My Order History — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col lg:flex-row gap-8">
            
            {{-- Account Sidebar --}}
            <aside class="w-full lg:w-64 bg-white border border-cream-150 rounded-3xl p-6 shrink-0 h-fit space-y-2">
                <div class="flex items-center gap-3 pb-5 mb-4 border-b border-cream-100">
                    <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-base">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-heading font-bold text-espresso-800 text-sm truncate">{{ auth()->user()->name }}</h4>
                        <p class="text-[10px] text-espresso-400 font-medium truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>

                <a href="{{ route('account.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>🏠</span> Dashboard
                </a>
                <a href="{{ route('account.profile') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>👤</span> Profile Details
                </a>
                <a href="{{ route('account.addresses') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>📍</span> Address Book
                </a>
                <a href="{{ route('orders.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-500 text-white shadow-sm">
                    <span>📦</span> Order History
                </a>
                <a href="{{ route('account.wishlist') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>❤️</span> My Wishlist
                </a>
                <a href="{{ route('account.password') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>🔒</span> Change Password
                </a>
                <hr class="border-cream-100 my-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-650 hover:bg-red-50 transition-colors text-left">
                        <span>🚪</span> Log Out
                    </button>
                </form>
            </aside>

            {{-- Main Content --}}
            <div class="flex-1">
                <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <h3 class="font-heading font-bold text-espresso-800 text-lg border-b border-cream-100 pb-3 mb-6">Order History</h3>

                    @if($orders->isEmpty())
                        <div class="text-center py-12">
                            <span class="text-3xl mb-3 block">📦</span>
                            <p class="text-sm text-espresso-500 font-light">You haven't placed any orders yet.</p>
                            <a href="{{ route('shop') }}" class="btn-primary py-2 px-6 text-xs mt-4">Start Shopping</a>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs sm:text-sm">
                                <thead>
                                    <tr class="bg-cream-50/50 text-espresso-500 uppercase text-xs font-semibold">
                                        <th class="px-4 py-3 rounded-l-xl">Order Number</th>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Payment</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Grand Total</th>
                                        <th class="px-4 py-3 rounded-r-xl text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-cream-50 font-medium">
                                    @foreach($orders as $o)
                                        <tr class="hover:bg-cream-50/20 text-xs sm:text-sm">
                                            <td class="px-4 py-3.5 font-bold text-espresso-800">{{ $o->order_number }}</td>
                                            <td class="px-4 py-3.5 text-espresso-500">{{ store_time($o->created_at)->format('M d, Y') }}</td>
                                            <td class="px-4 py-3.5 text-espresso-500 uppercase">{{ $o->payment_method_label }}</td>
                                            <td class="px-4 py-3.5">
                                                <span class="status-{{ $o->status }} uppercase text-[9px] px-2 py-0.5 rounded-full font-bold">
                                                    {{ str_replace('_', ' ', $o->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-primary-600 font-bold">{{ order_price($o, $o->grand_total) }}</td>
                                            <td class="px-4 py-3.5 text-right">
                                                <a href="{{ route('orders.show', $o->id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-cream-100 hover:bg-primary-500 hover:text-white transition-colors rounded-xl text-xs font-semibold text-espresso-800">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        {{ $orders->links('components.pagination') }}
                    @endif
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
