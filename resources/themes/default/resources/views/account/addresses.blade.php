<x-app-layout title="My Address Book — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10" 
         x-data="{ 
            modalOpen: false, 
            editMode: false,
            addressId: '',
            label: 'Home',
            firstName: '',
            lastName: '',
            phone: '',
            country: 'Pakistan',
            city: 'your area',
            deliveryZoneId: '',
            addressLine: '',
            actionUrl: '{{ route('account.addresses.store') }}',
            method: 'POST',

            openAddModal() {
                this.editMode = false;
                this.addressId = '';
                this.label = 'Home';
                this.firstName = '{{ explode(' ', auth()->user()->name)[0] ?? '' }}';
                this.lastName = '{{ explode(' ', auth()->user()->name)[1] ?? '' }}';
                this.phone = '{{ auth()->user()->phone ?? '' }}';
                this.city = 'your area';
                this.deliveryZoneId = '';
                this.addressLine = '';
                this.actionUrl = '{{ route('account.addresses.store') }}';
                this.method = 'POST';
                this.modalOpen = true;
            },
            openEditModal(addr) {
                this.editMode = true;
                this.addressId = addr.id;
                this.label = addr.label;
                this.firstName = addr.first_name;
                this.lastName = addr.last_name;
                this.phone = addr.phone;
                this.city = addr.city;
                this.deliveryZoneId = addr.delivery_zone_id || '';
                this.addressLine = addr.address_line;
                this.actionUrl = `/account/addresses/${addr.id}`;
                this.method = 'PUT';
                this.modalOpen = true;
            }
         }">
        
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
                <a href="{{ route('account.addresses') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-500 text-white shadow-sm">
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
                
                {{-- Address list header --}}
                <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-cream-100 pb-3 mb-6">
                        <h3 class="font-heading font-bold text-espresso-800 text-lg">Address Book</h3>
                        <button type="button" @click="openAddModal()" class="btn-gold py-2 px-4 text-xs font-semibold">
                            + Add New Address
                        </button>
                    </div>

                    @if($addresses->isEmpty())
                        <div class="text-center py-12">
                            <span class="text-3xl mb-3 block">📍</span>
                            <p class="text-sm text-espresso-500 font-light">You don't have any saved addresses yet.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($addresses as $addr)
                                <div class="bg-cream-50/40 border border-cream-200 rounded-3xl p-5 flex flex-col justify-between h-48 hover:shadow-card transition-all duration-300">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between w-full">
                                            <span class="text-xs font-bold text-espresso-800 uppercase tracking-wide bg-cream-200 px-2 py-0.5 rounded-full">{{ $addr->label }}</span>
                                            @if($addr->is_default)
                                                <span class="text-[9px] bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full font-bold uppercase">Default</span>
                                            @endif
                                        </div>
                                        
                                        <p class="text-xs font-semibold text-espresso-800">{{ $addr->first_name }} {{ $addr->last_name }}</p>
                                        <p class="text-xs text-espresso-600 line-clamp-2 leading-relaxed">
                                            {{ $addr->address_line }}, {{ $addr->area }}, {{ $addr->city }}
                                        </p>
                                        <p class="text-[10px] text-espresso-450">{{ $addr->phone }}</p>
                                    </div>

                                    <div class="flex items-center gap-3 pt-3 border-t border-cream-150 mt-4 text-xs">
                                        <button type="button" @click="openEditModal({{ json_encode($addr) }})" 
                                                class="text-primary-600 hover:text-primary-800 font-semibold">Edit</button>
                                        
                                        @if(!$addr->is_default)
                                            <form action="{{ route('account.addresses.default', $addr->id) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-espresso-500 hover:text-espresso-800">Set Default</button>
                                            </form>
                                        @endif

                                        <form action="{{ route('account.addresses.delete', $addr->id) }}" method="POST" class="ml-auto"
                                              onsubmit="return confirm('Are you sure you want to delete this address?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

        </div>

        {{-- Add/Edit Address Modal --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-espresso-800/60 backdrop-blur-sm animate-fade-in">
            <div class="w-full max-w-lg bg-white rounded-3xl shadow-luxury overflow-hidden animate-fade-up" @click.outside="modalOpen = false">
                
                {{-- Modal Header --}}
                <div class="px-6 py-5 border-b border-cream-100 flex items-center justify-between">
                    <h3 class="font-heading font-bold text-lg text-espresso-800" x-text="editMode ? 'Edit Address' : 'Add New Address'"></h3>
                    <button type="button" @click="modalOpen = false" class="btn-ghost p-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal Form --}}
                <form :action="actionUrl" method="POST" class="p-6 space-y-4">
                    @csrf
                    <template x-if="method === 'PUT'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div>
                        <label for="modal_label" class="label-luxury">Address Label</label>
                        <select id="modal_label" name="label" x-model="label"
                                class="input-luxury text-sm">
                            <option value="Home">Home</option>
                            <option value="Work">Work/Office</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="modal_first_name" class="label-luxury">First Name</label>
                            <input type="text" id="modal_first_name" name="first_name" x-model="firstName" required class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="modal_last_name" class="label-luxury">Last Name</label>
                            <input type="text" id="modal_last_name" name="last_name" x-model="lastName" required class="input-luxury text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="modal_phone" class="label-luxury">Contact Phone</label>
                            <input type="text" id="modal_phone" name="phone" x-model="phone" placeholder="e.g. 03001234567" required class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="modal_delivery_zone_id" class="label-luxury">Delivery Zone / Area *</label>
                            <select id="modal_delivery_zone_id" name="delivery_zone_id" x-model="deliveryZoneId" required class="input-luxury text-sm">
                                <option value="">Select Delivery Zone</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }} ({{ format_price($zone->delivery_fee) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="modal_country" class="label-luxury">Country</label>
                            <input type="text" id="modal_country" name="country" x-model="country" readonly class="input-luxury text-sm bg-cream-50 text-espresso-450">
                        </div>
                        <div>
                            <label for="modal_city" class="label-luxury">City</label>
                            <input type="text" id="modal_city" name="city" x-model="city" readonly class="input-luxury text-sm bg-cream-50 text-espresso-450">
                        </div>
                    </div>

                    <div>
                        <label for="modal_address_line" class="label-luxury">Detailed Delivery Address</label>
                        <textarea id="modal_address_line" name="address_line" x-model="addressLine" rows="3" placeholder="House/Apartment #, Street, Landmark details..." required class="input-luxury text-sm"></textarea>
                    </div>

                    <template x-if="!editMode">
                        <label class="flex items-center gap-2 text-xs font-semibold text-espresso-750 cursor-pointer pt-2">
                            <input type="checkbox" name="is_default" value="1" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                            <span>Set as my default shipping address</span>
                        </label>
                    </template>

                    <div class="flex gap-3 pt-4 border-t border-cream-100 justify-end">
                        <button type="button" @click="modalOpen = false" class="btn-secondary py-2 px-5 text-xs">Cancel</button>
                        <button type="submit" class="btn-gold py-2 px-5 text-xs font-semibold">Save Address</button>
                    </div>

                </form>

            </div>
        </div>

    </div>

</x-app-layout>
