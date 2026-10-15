<x-app-layout title="Profile Settings — {{ setting('site_name', config('site.name', 'Store')) }}">

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

                <a href="{{ route('account.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-espresso-700 hover:bg-cream-50 transition-colors">
                    <span>🏠</span> Dashboard
                </a>
                <a href="{{ route('account.profile') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-500 text-white shadow-sm">
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
            <div class="flex-1">
                <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <h3 class="font-heading font-bold text-espresso-800 text-lg border-b border-cream-100 pb-3 mb-6">Profile Details</h3>

                    <form action="{{ route('account.profile.update') }}" method="POST" class="space-y-4 max-w-xl">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="name" class="label-luxury">Full Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="input-luxury text-sm">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="label-luxury">Email Address</label>
                                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="input-luxury text-sm bg-cream-50/50" readonly>
                                <span class="text-[10px] text-espresso-400 mt-1 block">Contact customer support to update email.</span>
                                @error('email')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="phone" class="label-luxury">Phone Number (WhatsApp preferred)</label>
                                <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 03001234567" class="input-luxury text-sm">
                                @error('phone')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn-gold px-8 py-3 mt-2 text-sm font-semibold tracking-wide shadow-sm">
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
