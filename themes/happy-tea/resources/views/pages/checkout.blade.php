<x-app-layout title="Secure Checkout — {{ setting('site_name', config('site.name', 'Store')) }}">

    @php
        $defaultPaymentMethod = $enabledPaymentMethods->first() ?? '';
    @endphp

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('checkoutForm', () => ({
                form: {
                    firstName: {!! json_encode(auth()->check() ? explode(' ', auth()->user()->name)[0] : '') !!},
                    lastName: {!! json_encode(auth()->check() ? (explode(' ', auth()->user()->name)[1] ?? '') : '') !!},
                    email: {!! json_encode(auth()->check() ? auth()->user()->email : '') !!},
                    phone: {!! json_encode(auth()->check() ? (auth()->user()->phone ?? '') : '') !!},
                    deliveryZoneId: {!! json_encode($defaultAddr?->delivery_zone_id ?? '') !!},
                    addressLine: {!! json_encode($defaultAddr?->address_line ?? '') !!},
                    mapLink: '',
                    paymentMethod: {!! json_encode($defaultPaymentMethod) !!}
                },
                
                orderSubmitted: false,
                idempotencyKey: @js($checkoutKey),
                isSubmitting: false,
                errorMessage: '',
                errors: {},
                trackingUrl: '',
                
                subtotal: {{ (float) $totals['subtotal'] }},
                discount: {{ (float) $totals['discount'] }},
                couponCode: {!! json_encode($totals['coupon']?->code ?? '') !!},
                appliedCouponCode: {!! json_encode($totals['coupon']?->code ?? '') !!},
                couponError: '',
                couponSuccess: '',
                isApplyingCoupon: false,

                async applyCoupon() {
                    if (this.isSubmitting || this.isApplyingCoupon) return;
                    if (!this.couponCode.trim()) return;
                    this.isApplyingCoupon = true;
                    this.couponError = '';
                    this.couponSuccess = '';
                    try {
                        const response = await fetch('{{ route('cart.coupon') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ code: this.couponCode, email: this.form.email, idempotency_key: this.idempotencyKey })
                        });
                        const data = await response.json();
                        if (response.ok && data.success) {
                            if (data.idempotency_key) this.idempotencyKey = data.idempotency_key;
                            this.couponSuccess = data.message;
                            this.appliedCouponCode = data.coupon.code;
                            this.discount = parseFloat(data.totals.discount);
                            this.subtotal = parseFloat(data.totals.subtotal);
                        } else {
                            this.couponError = data.message || 'Failed to apply coupon.';
                        }
                    } catch (error) {
                        this.couponError = 'A network error occurred.';
                    } finally {
                        this.isApplyingCoupon = false;
                    }
                },

                async removeCoupon() {
                    if (this.isSubmitting || this.isApplyingCoupon) return;
                    this.isApplyingCoupon = true;
                    this.couponError = '';
                    this.couponSuccess = '';
                    try {
                        const response = await fetch('{{ route('cart.coupon.remove') }}', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ idempotency_key: this.idempotencyKey })
                        });
                        const data = await response.json();
                        if (response.ok && data.success) {
                            if (data.idempotency_key) this.idempotencyKey = data.idempotency_key;
                            this.couponSuccess = 'Coupon removed.';
                            this.appliedCouponCode = '';
                            this.couponCode = '';
                            this.discount = parseFloat(data.totals.discount);
                            this.subtotal = parseFloat(data.totals.subtotal);
                        } else {
                            this.couponError = data.message || 'Failed to remove coupon.';
                        }
                    } catch (error) {
                        this.couponError = 'A network error occurred.';
                    } finally {
                        this.isApplyingCoupon = false;
                    }
                },
                zones: @json($zones->keyBy('id')),
                paymentChoices: @js(collect($paymentChoices)->map(fn ($choice) => ['requires_receipt' => $choice['instructions']->requiresReceipt])->all()),
                get receiptRequired() { return this.paymentChoices[this.form.paymentMethod]?.requires_receipt || false; },

                get selectedZone() {
                    return this.zones[this.form.deliveryZoneId] || null;
                },
                get deliveryFee() {
                    return this.selectedZone ? parseFloat(this.selectedZone.delivery_fee) : null;
                },
                get grandTotal() {
                    const fee = this.deliveryFee !== null ? this.deliveryFee : 0;
                    return Math.max(0, Math.round(this.subtotal * 100) - Math.round(this.discount * 100) + Math.round(fee * 100)) / 100;
                },
                
                setAddress(addr) {
                    this.form.firstName = String(addr.first_name ?? '');
                    this.form.lastName  = String(addr.last_name  ?? '');
                    this.form.phone     = String(addr.phone       ?? '');
                    this.form.deliveryZoneId = addr.delivery_zone_id ? String(addr.delivery_zone_id) : '';
                    this.form.addressLine    = String(addr.address_line ?? '');
                    ['first_name', 'last_name', 'phone', 'delivery_zone_id', 'address_line'].forEach((name) => this.clearError(name));
                },

                init() {
                    this.errors = this.flattenErrors(@json($errors->toArray()));
                },

                flattenErrors(payload) {
                    const errors = {};
                    if (!payload || typeof payload !== 'object') return errors;
                    Object.entries(payload).forEach(([key, messages]) => {
                        errors[key] = Array.isArray(messages) ? (messages[0] || '') : String(messages);
                    });
                    return errors;
                },

                clearError(name) {
                    if (!this.errors[name]) return;
                    const next = { ...this.errors };
                    delete next[name];
                    this.errors = next;
                },

                inputErrorClass(name) {
                    return this.errors[name] ? 'border-red-400 focus:ring-red-300 focus:border-red-500' : '';
                },

                scrollToFirstError() {
                    this.$nextTick(() => {
                        const order = ['first_name', 'last_name', 'email', 'phone', 'delivery_zone_id', 'address_line', 'map_link', 'payment_method', 'payment_receipt', 'order_notes'];
                        const first = order.find((name) => this.errors[name]);
                        if (!first) {
                            if (this.errorMessage) {
                                this.$refs.formError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                            return;
                        }
                        const node = document.getElementById(first) || document.querySelector(`[name="${first}"]:not([disabled])`);
                        node?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (node && typeof node.focus === 'function') node.focus({ preventScroll: true });
                    });
                },

                validateFields(formEl) {
                    const errors = {};
                    if (!(this.form.firstName || '').trim()) errors.first_name = 'Please enter your first name.';
                    if (!(this.form.lastName || '').trim()) errors.last_name = 'Please enter your last name.';
                    const email = (this.form.email || '').trim();
                    if (!email) {
                        errors.email = 'Please enter your email address.';
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        errors.email = 'Please enter a valid email address.';
                    }
                    if (!(this.form.phone || '').trim()) errors.phone = 'Please enter your phone number.';
                    if (!(this.form.deliveryZoneId || '')) errors.delivery_zone_id = 'Please select a delivery zone.';
                    if (!(this.form.addressLine || '').trim()) errors.address_line = 'Please enter your delivery address.';
                    if (!(this.form.paymentMethod || '')) errors.payment_method = 'Please select a payment method.';
                    if (this.form.paymentMethod && this.receiptRequired) {
                        const receiptInput = formEl.querySelector('input[name="payment_receipt"]:not([disabled])');
                        if (!receiptInput || !receiptInput.files || receiptInput.files.length === 0) {
                            errors.payment_receipt = 'Please attach your payment screenshot / receipt.';
                        }
                    }
                    this.errors = errors;
                    return Object.keys(errors).length === 0;
                },

                async submitForm(e) {
                    if (this.isSubmitting || this.isApplyingCoupon) return;

                    this.errorMessage = '';
                    if (!this.validateFields(e.target)) {
                        this.scrollToFirstError();
                        return;
                    }

                    this.isSubmitting = true;
                    document.body.classList.add('overflow-hidden');

                    try {
                        const formData = new FormData(e.target);
                        const response = await fetch(e.target.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const contentType = response.headers.get('content-type') || '';
                        if (!contentType.includes('application/json')) {
                            this.errorMessage = 'An error occurred while placing your order. Please check your inputs and try again.';
                            this.scrollToFirstError();
                            return;
                        }

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.trackingUrl = data.tracking_url;
                            this.orderSubmitted = true;
                            this.$nextTick(() => {
                                window.scrollTo(0, 0);
                            });
                            setTimeout(() => {
                                window.scrollTo(0, 0);
                            }, 50);
                            return;
                        }

                        if (data.errors) {
                            this.errors = this.flattenErrors(data.errors);
                            this.errorMessage = Object.values(this.errors)[0] || data.message || '';
                            this.scrollToFirstError();
                        } else {
                            this.errorMessage = data.message || 'An error occurred while placing your order. Please check your inputs and try again.';
                            this.scrollToFirstError();
                        }
                    } catch (error) {
                        console.error(error);
                        this.errorMessage = 'A network error occurred. Please check your internet connection and try again.';
                        this.scrollToFirstError();
                    } finally {
                        this.isSubmitting = false;
                        document.body.classList.remove('overflow-hidden');
                    }
                }
            }));
        });
    </script>

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="[
        'Cart' => route('cart.index'),
        'Checkout' => ''
    ]" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16"
         x-data="checkoutForm">
        
        <div class="mb-8">
            <h1 class="text-3xl font-heading font-bold text-espresso-800">Checkout</h1>
            <p class="text-sm text-espresso-500 mt-1">Complete your order details below. Fast delivery inside Sialkot.</p>
        </div>

        <template x-if="orderSubmitted">
            <div class="bg-white border border-cream-150 rounded-3xl p-8 sm:p-12 text-center max-w-2xl mx-auto my-8 shadow-sm relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-24 h-24 bg-primary-100 rounded-full blur-xl opacity-40"></div>
                <div class="text-6xl mb-6">🎉</div>
                <h2 class="font-heading text-2xl font-bold text-espresso-800">We have received your order!</h2>
                <p class="text-sm text-espresso-600 mt-4 leading-relaxed max-w-md mx-auto">
                    We will verify your order and payment details. Once confirmed, you will receive a confirmation email and a message on WhatsApp.
                </p>

                <div class="bg-primary-50 border border-primary-100 rounded-2xl p-5 my-6 max-w-md mx-auto text-xs" x-show="trackingUrl">
                    <span class="text-primary-700 font-bold block mb-1">Live Order Tracking Link</span>
                    <p class="text-espresso-600 mb-3 leading-relaxed">Copy this link or click it to track your order status anytime:</p>
                    <div class="flex items-center gap-2 bg-white border border-cream-200 p-2 rounded-xl">
                        <input type="text" readonly :value="trackingUrl" id="trackingLink"
                               class="flex-1 bg-transparent border-0 outline-none text-espresso-750 select-all p-0 text-xs focus:ring-0">
                        <button type="button" @click="window.copyTextToClipboard(trackingUrl)"
                                class="text-[10px] font-semibold bg-primary-100 text-primary-700 px-2.5 py-1.5 rounded-lg hover:bg-primary-200 transition-colors shrink-0">
                            Copy
                        </button>
                    </div>
                </div>

                <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('home') }}" class="btn-gold px-8 py-3 text-sm font-semibold tracking-wide shadow-md">
                        Continue Shopping
                    </a>
                    <a :href="trackingUrl" class="btn-secondary px-8 py-3 text-sm font-semibold tracking-wide shadow-md" x-show="trackingUrl">
                        Track Your Order
                    </a>
                </div>
            </div>
        </template>

        <div x-show="isSubmitting" x-cloak
             class="fixed inset-0 z-[95] flex items-center justify-center bg-espresso-900/50 px-4"
             role="status" aria-live="assertive" aria-busy="true">
            <div class="bg-white border border-cream-150 rounded-3xl px-8 py-8 w-full max-w-sm text-center shadow-sm">
                <svg class="animate-spin h-10 w-10 text-primary-500 mx-auto" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="font-heading font-bold text-espresso-800 text-lg mt-5">Placing your order</p>
                <p class="text-sm text-espresso-500 mt-2 leading-relaxed">Please wait — do not refresh or close this page.</p>
            </div>
        </div>

        <form action="{{ route('checkout.place-order') }}" method="POST" enctype="multipart/form-data" novalidate @submit.prevent="submitForm" x-show="!orderSubmitted"
              :aria-busy="isSubmitting" :class="isSubmitting && 'pointer-events-none'">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $checkoutKey }}" :value="idempotencyKey">
            <x-honeypot />

            <div x-ref="formError" x-show="errorMessage" x-cloak x-text="errorMessage"
                 class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>
            <a x-show="errors.cart || errors.coupon || errors.idempotency_key" x-cloak href="{{ route('cart.index') }}"
               class="block mb-6 text-sm font-semibold text-primary-600 underline">Review your cart</a>
            @if($enabledPaymentMethods->isEmpty())
                <p role="alert" class="mb-6 text-sm font-semibold text-red-700">No payment methods are available. Please contact the store before ordering.</p>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                {{-- Left Side: Address & Payment Info --}}
                <div class="lg:col-span-7 space-y-6">
                    
                    {{-- Saved Addresses --}}
                    @if($addresses->isNotEmpty())
                        <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm">
                            <h3 class="font-heading font-bold text-espresso-800 text-sm mb-4">Saved Delivery Addresses</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($addresses as $addr)
                                    <button type="button" @click="setAddress({{ json_encode($addr) }})"
                                            class="text-left p-4 rounded-2xl border border-cream-200 bg-cream-50/50 hover:bg-cream-50 hover:border-primary-400 focus:outline-none transition-all flex flex-col justify-between h-28">
                                        <div class="flex items-center justify-between w-full">
                                            <span class="text-xs font-bold text-espresso-800 uppercase tracking-wide">{{ $addr->label }}</span>
                                            @if($addr->is_default)
                                                <span class="text-[9px] bg-primary-100 text-primary-700 px-1.5 py-0.5 rounded-full font-bold uppercase">Default</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-espresso-600 line-clamp-2 mt-2 leading-relaxed">
                                            {{ $addr->address_line }}, {{ $addr->area }}, {{ $addr->city }}
                                        </p>
                                        <span class="text-[10px] text-espresso-450 mt-1 font-medium">{{ $addr->first_name }} {{ $addr->last_name }} · {{ $addr->phone }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Customer Details & Shipping Form --}}
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm space-y-4">
                        <h3 class="font-heading font-bold text-espresso-800 text-base border-b border-cream-100 pb-3 mb-4">Delivery Information</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="label-luxury">First Name</label>
                                <input type="text" id="first_name" name="first_name" x-model="form.firstName" @input="clearError('first_name')" required
                                       :class="inputErrorClass('first_name')" class="input-luxury text-sm" :aria-invalid="!!errors.first_name">
                                <p x-show="errors.first_name" x-text="errors.first_name" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                            </div>
                            <div>
                                <label for="last_name" class="label-luxury">Last Name</label>
                                <input type="text" id="last_name" name="last_name" x-model="form.lastName" @input="clearError('last_name')" required
                                       :class="inputErrorClass('last_name')" class="input-luxury text-sm" :aria-invalid="!!errors.last_name">
                                <p x-show="errors.last_name" x-text="errors.last_name" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="label-luxury">Email Address</label>
                                <input type="email" id="email" name="email" x-model="form.email" @input="clearError('email')" required
                                       class="input-luxury text-sm {{ auth()->check() ? 'bg-cream-50/50 text-espresso-450' : '' }}"
                                       :class="inputErrorClass('email')"
                                       {{ auth()->check() ? 'readonly' : '' }}
                                       :aria-invalid="!!errors.email">
                                <p x-show="errors.email" x-text="errors.email" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                            </div>
                            <div>
                                <label for="phone" class="label-luxury">Phone Number (WhatsApp preferred) *</label>
                                <input type="tel" id="phone" name="phone" x-model="form.phone" @input="clearError('phone')" placeholder="e.g. 03001234567" required
                                       :class="inputErrorClass('phone')" class="input-luxury text-sm" :aria-invalid="!!errors.phone">
                                <p x-show="errors.phone" x-text="errors.phone" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                            </div>
                        </div>

                        <input type="hidden" name="country" value="Pakistan">
                        <input type="hidden" name="city" value="{{ setting('site_city', config('site.city')) }}">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="delivery_zone_id" class="label-luxury">Delivery Zone / Area *</label>
                                <select id="delivery_zone_id" name="delivery_zone_id" x-model="form.deliveryZoneId" @change="clearError('delivery_zone_id')" required
                                        :class="inputErrorClass('delivery_zone_id')" class="input-luxury text-sm" :aria-invalid="!!errors.delivery_zone_id">
                                    <option value="">Select Delivery Zone</option>
                                    @foreach($zones as $zone)
                                        <option value="{{ $zone->id }}">{{ $zone->name }} (Rs. {{ number_format($zone->delivery_fee, 0) }})</option>
                                    @endforeach
                                </select>
                                <p x-show="errors.delivery_zone_id" x-text="errors.delivery_zone_id" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                            </div>
                            <div>
                                <label for="map_link" class="label-luxury">Map Link / GPS Coordinates (Optional)</label>
                                <input type="text" id="map_link" name="map_link" x-model="form.mapLink" @input="clearError('map_link')" placeholder="e.g. https://maps.app.goo.gl/..."
                                       :class="inputErrorClass('map_link')" class="input-luxury text-sm" :aria-invalid="!!errors.map_link">
                                <p x-show="errors.map_link" x-text="errors.map_link" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                            </div>
                        </div>

                        <div>
                            <label for="address_line" class="label-luxury">Detailed Delivery Address *</label>
                            <textarea id="address_line" name="address_line" x-model="form.addressLine" @input="clearError('address_line')" rows="3" placeholder="House number, Street, Landmark details..." required
                                      :class="inputErrorClass('address_line')" class="input-luxury text-sm" :aria-invalid="!!errors.address_line"></textarea>
                            <p x-show="errors.address_line" x-text="errors.address_line" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                        </div>
                    </div>

                    {{-- Payment Method Selection --}}
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm space-y-4">
                        <h3 class="font-heading font-bold text-espresso-800 text-base border-b border-cream-100 pb-3 mb-4">Payment Method</h3>
                        
                        <div class="space-y-3">
                            @foreach($paymentChoices as $method => $choice)
                                <label class="flex items-start gap-3 border border-cream-200 rounded-xl p-4 cursor-pointer">
                                    <input type="radio" name="payment_method" value="{{ $method }}" x-model="form.paymentMethod" @change="clearError('payment_method'); clearError('payment_receipt')">
                                    <span>{{ $choice['instructions']->label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p x-show="errors.payment_method" x-text="errors.payment_method" class="text-red-500 text-xs" x-cloak></p>
                        @foreach($paymentChoices as $method => $choice)
                            <div x-show="form.paymentMethod === '{{ $method }}'" class="mt-4 rounded-xl border p-4">
                                <p class="whitespace-pre-line text-sm">{{ $choice['instructions']->instructions }}</p>
                            </div>
                        @endforeach
                        <div x-show="receiptRequired" class="mt-4">
                            <label class="label-luxury">Payment receipt</label>
                            <input type="file" name="payment_receipt" accept="image/*" :disabled="!receiptRequired" @change="clearError('payment_receipt')" class="input-luxury">
                            <p x-show="errors.payment_receipt" x-text="errors.payment_receipt" class="text-red-500 text-xs" x-cloak></p>
                        </div>
                    </div>

                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm">
                        <label for="order_notes" class="block font-heading font-bold text-espresso-800 text-base mb-2">Order Notes / Custom Requests</label>
                        <textarea id="order_notes" name="order_notes" rows="3" placeholder="e.g. Less ice, 50% sugar, or deliver after 4 PM..."
                                  @input="clearError('order_notes')" :class="inputErrorClass('order_notes')" class="input-luxury text-sm" :aria-invalid="!!errors.order_notes"></textarea>
                        <p x-show="errors.order_notes" x-text="errors.order_notes" class="text-red-500 text-xs mt-1 font-medium" x-cloak></p>
                    </div>

                </div>

                {{-- Right Side: Order Review & Pricing Summary --}}
                <div class="lg:col-span-5 lg:sticky lg:top-24 space-y-6">
                    <div class="bg-white border border-cream-150 rounded-3xl p-6 shadow-sm">
                        <h2 class="font-heading font-bold text-espresso-800 text-lg mb-4">Review Your Order</h2>
                        
                        {{-- Items list snapshot --}}
                        <div class="divide-y divide-cream-100 max-h-64 overflow-y-auto scrollbar-hide mb-6 pr-1">
                            @foreach($items as $item)
                                <div class="flex gap-3 py-3 items-center">
                                    <img src="{{ $item->product?->main_image_url ?? asset('images/placeholder.jpg') }}" 
                                         alt="{{ $item->product?->title }}" 
                                         class="w-12 h-12 rounded-lg object-cover border border-cream-100 shrink-0">
                                    <div class="flex-1 min-w-0 text-xs">
                                        <p class="font-medium text-espresso-800 truncate">{{ $item->product?->title }}</p>
                                        @include('components.item-configuration', ['item' => $item, 'class' => 'mt-0.5 space-y-0.5', 'labelClass' => 'text-[10px] text-espresso-600', 'optionClass' => 'text-[10px] text-espresso-600'])
                                        <p class="text-espresso-400 mt-0.5">Qty: {{ $item->quantity }} @ {{ format_price($item->price) }}</p>
                                    </div>
                                    <span class="text-xs font-bold text-espresso-800 shrink-0">{{ format_price($item->line_total) }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Pricing Summary --}}
                        <div class="space-y-3 pb-4 border-b border-cream-100 text-sm">
                            <div class="flex justify-between text-espresso-600">
                                <span>Subtotal</span>
                                <span class="font-semibold text-espresso-800" x-text="'Rs. ' + subtotal.toLocaleString()"></span>
                            </div>
                            
                            <template x-if="discount > 0">
                                <div class="flex justify-between text-green-600">
                                    <span>Discount</span>
                                    <span class="font-semibold" x-text="'-Rs. ' + discount.toLocaleString()"></span>
                                </div>
                            </template>

                            <div class="flex justify-between text-espresso-600">
                                <span>Delivery Fee</span>
                                <span class="font-semibold text-espresso-800" x-text="deliveryFee !== null ? (deliveryFee > 0 ? 'Rs. ' + deliveryFee.toLocaleString() : 'Free') : 'Depends on Location'"></span>
                            </div>
                        </div>

                        {{-- Coupon Section --}}
                        <div class="py-4 border-b border-cream-100">
                            <label class="block text-[10px] font-bold text-espresso-800 uppercase tracking-wider mb-2">Have a Promo Code?</label>
                            
                            {{-- If coupon is applied --}}
                            <div x-show="appliedCouponCode" class="flex items-center justify-between bg-green-50 border border-green-150 rounded-xl p-2.5 text-xs text-green-800" x-cloak>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm">🏷️</span>
                                    <span>Coupon <strong><span x-text="appliedCouponCode"></span></strong> Applied</span>
                                </div>
                                <button type="button" @click="removeCoupon" class="text-red-500 hover:text-red-700 font-bold uppercase text-[10px] tracking-wide ml-2">Remove</button>
                            </div>

                            {{-- If coupon is not applied --}}
                            <div x-show="!appliedCouponCode" class="flex gap-2">
                                <input type="text" x-model="couponCode" placeholder="Enter coupon code" 
                                       class="flex-1 px-3 py-1.5 border border-cream-200 rounded-xl text-xs uppercase focus:outline-none focus:ring-1 focus:ring-primary-300 focus:border-primary-400 bg-cream-50/20 text-espresso-800 placeholder-espresso-300">
                                <button type="button" @click="applyCoupon" :disabled="isApplyingCoupon || !couponCode.trim()"
                                        class="px-4 py-1.5 bg-espresso-800 hover:bg-espresso-750 text-white font-semibold text-xs rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                    Apply
                                </button>
                            </div>
                            
                            {{-- Messages --}}
                            <div x-show="couponError" x-text="couponError" class="mt-2 text-[10px] font-semibold text-red-650" x-cloak></div>
                            <div x-show="couponSuccess" x-text="couponSuccess" class="mt-2 text-[10px] font-semibold text-green-700" x-cloak></div>
                        </div>

                        {{-- Grand Total --}}
                        <div class="flex justify-between items-baseline font-heading font-bold text-espresso-800 text-xl pt-4 mb-6">
                            <span>Grand Total</span>
                            <div class="text-right">
                                <span class="text-primary-650" x-text="'Rs. ' + grandTotal.toLocaleString()"></span>
                            </div>
                        </div>

                        {{-- Submit Order --}}
                        <button type="submit"
                                :disabled="isSubmitting || isApplyingCoupon || {{ ($enabledPaymentMethods->isEmpty() || $zones->isEmpty()) ? 'true' : 'false' }}"
                                :aria-busy="isSubmitting"
                                class="btn-gold w-full py-3.5 text-sm font-semibold tracking-wide"
                                :class="isSubmitting && 'cursor-wait opacity-90'">
                            <svg x-show="isSubmitting" x-cloak class="animate-spin h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="isSubmitting ? 'Placing order…' : 'Place Order (Confirm)'"></span>
                        </button>
                        
                        <p class="text-[10px] text-espresso-400 text-center mt-3 leading-relaxed">
                            By placing your order, you agree to our terms of service. Orders are processed inside Sialkot, Pakistan.
                        </p>
                    </div>
                </div>

            </div>

        </form>

    </div>

</x-app-layout>
