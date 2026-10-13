<x-app-layout title="My Account — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col lg:flex-row gap-8">
            
            {{-- Account Sidebar --}}
            <aside class="w-full lg:w-64 bg-white border border-cream-150 rounded-3xl p-6 shrink-0 h-fit space-y-2">
                <div class="flex items-center gap-3 pb-5 mb-4 border-b border-cream-100">
                    <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-base">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-heading font-bold text-espresso-800 text-sm truncate">{{ $user->name }}</h4>
                        <p class="text-[10px] text-espresso-400 font-medium truncate">{{ $user->email }}</p>
                    </div>
                </div>

                <a href="{{ route('account.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-500 text-white shadow-sm">
                    <span>🏠</span> Dashboard
                </a>
                <a href="{{ route('account.profile') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>👤</span> Profile Details
                </a>
                <a href="{{ route('account.addresses') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>📍</span> Address Book
                </a>
                <a href="{{ route('orders.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
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
            <div class="flex-1 space-y-6">
                
                {{-- Stats Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-primary-50 flex items-center justify-center text-xl">📦</div>
                        <div class="space-y-0.5">
                            <span class="text-espresso-400 text-[10px] font-semibold uppercase tracking-wider">Total Orders</span>
                            <span class="text-2xl font-bold text-espresso-800 block">{{ $totalOrders }}</span>
                        </div>
                    </div>
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center text-xl">💰</div>
                        <div class="space-y-0.5">
                            <span class="text-espresso-400 text-[10px] font-semibold uppercase tracking-wider">Delivered Spent</span>
                            <span class="text-2xl font-bold text-espresso-800 block">{{ format_price($totalSpent) }}</span>
                        </div>
                    </div>
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center text-xl">❤️</div>
                        <div class="space-y-0.5">
                            <span class="text-espresso-400 text-[10px] font-semibold uppercase tracking-wider">Wishlist Items</span>
                            <span class="text-2xl font-bold text-espresso-800 block">{{ $wishlistCount }}</span>
                        </div>
                    </div>
                </div>

                {{-- Welcome Banner --}}
                <div class="bg-espresso-800 text-cream-100 rounded-3xl p-6 relative overflow-hidden shadow-md">
                    <div class="absolute inset-0 bg-cover bg-center opacity-10 mix-blend-overlay" style="background-image: url('{{ asset('images/placeholder.svg') }}')"></div>
                    <div class="relative z-10 space-y-1">
                        <h2 class="font-heading font-bold text-xl sm:text-2xl text-white">Hello, {{ $user->name }}!</h2>
                        <p class="text-xs sm:text-sm text-cream-200 font-light leading-relaxed">
                            Welcome to your dashboard. Here you can easily manage your shipping addresses, edit profile details, check order statuses, and review your favorites list.
                        </p>
                    </div>
                </div>

                {{-- Recent Orders Table --}}
                <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4 border-b border-cream-100 pb-3">
                        <h3 class="font-heading font-bold text-espresso-800 text-base">Recent Orders</h3>
                        <a href="{{ route('orders.index') }}" class="text-xs font-semibold text-primary-600 hover:underline">View All →</a>
                    </div>

                    @if($orders->isEmpty())
                        <div class="text-center py-8 text-xs text-espresso-500">
                            You haven't placed any orders yet. <a href="{{ route('shop') }}" class="text-primary-600 hover:underline font-semibold">Shop now</a>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-cream-50/50 text-espresso-500 uppercase font-semibold">
                                        <th class="px-4 py-3 rounded-l-xl">Order #</th>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Payment</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Total</th>
                                        <th class="px-4 py-3 rounded-r-xl text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-cream-50 font-medium">
                                    @foreach($orders as $o)
                                        <tr class="hover:bg-cream-50/20">
                                            <td class="px-4 py-3 font-bold text-espresso-800">{{ $o->order_number }}</td>
                                            <td class="px-4 py-3 text-espresso-500">{{ $o->created_at->format('M d, Y') }}</td>
                                            <td class="px-4 py-3 text-espresso-500 uppercase">{{ $o->payment_method }}</td>
                                            <td class="px-4 py-3">
                                                <span class="status-{{ $o->status }} uppercase text-[9px] px-2 py-0.5 rounded-full font-bold">
                                                    {{ str_replace('_', ' ', $o->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-primary-600 font-bold">{{ format_price($o->grand_total) }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('orders.show', $o->id) }}" class="inline-flex items-center justify-center px-3 py-1.5 bg-cream-100 hover:bg-primary-500 hover:text-white transition-colors rounded-xl text-[10px] font-semibold text-espresso-800">
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

        </div>
    </div>

</x-app-layout>
