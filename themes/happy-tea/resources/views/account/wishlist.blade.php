<x-app-layout title="My Wishlist — {{ setting('site_name', config('site.name', 'Store')) }}">

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
                <a href="{{ route('orders.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>📦</span> Order History
                </a>
                <a href="{{ route('account.wishlist') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-500 text-white shadow-sm">
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
                    <h3 class="font-heading font-bold text-espresso-800 text-lg border-b border-cream-100 pb-3 mb-6">My Favorites</h3>

                    @if($wishlist->isEmpty())
                        <div class="text-center py-12">
                            <span class="text-3xl mb-3 block">❤️</span>
                            <p class="text-sm text-espresso-500 font-light">Your wishlist is empty.</p>
                            <a href="{{ route('shop') }}" class="btn-primary py-2 px-6 text-xs mt-4">Browse Collection</a>
                        </div>
                    @else
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-6">
                            @foreach($wishlist as $item)
                                @if($item->product)
                                    <x-product-card :product="$item->product" />
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
