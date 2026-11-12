@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'My Address Book — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container"
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
            isDefault: false,
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
                this.isDefault = false;
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
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('account.dashboard') }}">My account</a><span>/</span><span aria-current="page">Address book</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-account">
                <x-account-nav active="addresses" />

                <div class="rj-panel">
                    <div class="rj-panel__head">
                        <h2>Address book</h2>
                        <button type="button" class="rj-button rj-button--accent rj-button--sm" @click="openAddModal()" data-dialog="address-dialog">Add new address</button>
                    </div>

                    @if(session('success'))
                        <div class="rj-flash" style="margin-bottom:22px">{{ session('success') }}</div>
                    @endif

                    @if($addresses->isEmpty())
                        <div class="rj-empty" style="border-width:0">
                            <svg class="icon" style="width:28px;height:28px;margin:0 auto 12px;display:block" aria-hidden="true"><use href="#icon-location"/></svg>
                            <p><strong>No saved addresses.</strong><br>Add a delivery address before checking out.</p>
                        </div>
                    @else
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px">
                            @foreach($addresses as $addr)
                                <div class="rj-addresscard">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                                        <span class="rj-chip">{{ $addr->label }}</span>
                                        @if($addr->is_default)
                                            <span class="rj-chip rj-chip--accent" style="padding:5px 9px">Default</span>
                                        @endif
                                    </div>
                                    <p style="font-size:13px;font-weight:700">{{ $addr->first_name }} {{ $addr->last_name }}</p>
                                    <p style="font-size:12.5px;line-height:1.7;color:var(--rj-muted)">{{ $addr->address_line }}, {{ $addr->area }}, {{ $addr->city }}</p>
                                    <p style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--rj-muted)">{{ $addr->phone }}</p>
                                    <div style="display:flex;align-items:center;gap:16px;border-top:1px solid var(--rj-line-soft);padding-top:12px;margin-top:auto">
                                        <button type="button" class="rj-textlink" style="font-size:10px" @click="openEditModal(@js($addr))" data-dialog="address-dialog">Edit</button>
                                        @if(!$addr->is_default)
                                            <form action="{{ route('account.addresses.default', $addr->id) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="rj-textlink" style="font-size:10px">Set default</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('account.addresses.delete', $addr->id) }}" method="POST" style="margin-left:auto"
                                              onsubmit="return confirm('Are you sure you want to delete this address?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" style="font-size:10px;font-weight:700;letter-spacing:.04em;color:#8a1c1c">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Add / edit address dialog --}}
        <dialog id="address-dialog" class="dialog">
            <div class="dialog__panel">
                <button type="button" class="dialog-close" aria-label="Close dialog"><svg class="icon"><use href="#icon-close"/></svg></button>
                <h2 x-text="editMode ? 'Edit address' : 'Add new address'"></h2>

                <form :action="actionUrl" method="POST" class="rj-form" style="margin-top:24px">
                    @csrf
                    <template x-if="method === 'PUT'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <label>
                        Address label
                        <select id="modal_label" name="label" x-model="label">
                            <option value="Home">Home</option>
                            <option value="Work">Work/Office</option>
                            <option value="Other">Other</option>
                        </select>
                    </label>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
                        <label>
                            First name *
                            <input id="modal_first_name" type="text" name="first_name" x-model="firstName" required>
                        </label>
                        <label>
                            Last name *
                            <input id="modal_last_name" type="text" name="last_name" x-model="lastName" required>
                        </label>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
                        <label>
                            Contact phone *
                            <input id="modal_phone" type="text" name="phone" x-model="phone" placeholder="e.g. 03001234567" required>
                        </label>
                        <label>
                            Delivery zone / area *
                            <select id="modal_delivery_zone_id" name="delivery_zone_id" x-model="deliveryZoneId" required>
                                <option value="">Select delivery zone</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }} ({{ format_price($zone->delivery_fee) }})</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
                        <label>
                            Country
                            <input id="modal_country" type="text" name="country" x-model="country" readonly style="background:var(--rj-surface);color:var(--rj-muted)">
                        </label>
                        <label>
                            City
                            <input id="modal_city" type="text" name="city" x-model="city" readonly style="background:var(--rj-surface);color:var(--rj-muted)">
                        </label>
                    </div>

                    <label>
                        Detailed delivery address *
                        <textarea id="modal_address_line" name="address_line" x-model="addressLine" rows="3" placeholder="House/Apartment #, street, landmark details..." required></textarea>
                    </label>

                    <template x-if="!editMode">
                        <label class="rj-checkbox">
                            <input type="checkbox" name="is_default" value="1">
                            <span>Set as my default shipping address</span>
                        </label>
                    </template>

                    <div style="display:flex;justify-content:flex-end;gap:14px;border-top:1px solid var(--rj-line-soft);padding-top:18px">
                        <button type="button" class="rj-button rj-button--outline rj-button--sm" @click="document.getElementById('address-dialog').close()">Cancel</button>
                        <button type="submit" class="rj-button rj-button--sm">Save address</button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>
</x-app-layout>
