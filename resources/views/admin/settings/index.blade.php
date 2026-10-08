<x-admin-layout title="Admin General Settings">

    {{-- Header --}}
    <div class="mb-8 border-b border-cream-100 pb-4">
        <h1 class="text-2xl font-heading font-bold text-espresso-800 font-bold">General settings</h1>
        <p class="text-xs text-espresso-500 mt-1">Configure company profiles, delivery charges, and manual payment details.</p>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6" x-data="{ activeTab: 'general' }">
        @csrf
        @method('PUT')

        <details class="bg-white border rounded-xl p-5"><summary class="font-bold cursor-pointer">Store information and policy text</summary>
            <p class="text-sm mt-2">Plain text used by the default storefront. Content stays with the store when themes change.</p>
            @foreach(['site_city' => 'City', 'about_text' => 'About us', 'shipping_policy' => 'Shipping and delivery', 'returns_policy' => 'Returns and refunds', 'privacy_policy' => 'Privacy policy', 'terms_policy' => 'Terms and conditions'] as $key => $label)
                <label class="block mt-4">{{ $label }}<textarea class="input-luxury mt-1" name="{{ $key }}" rows="4" maxlength="20000">{{ old($key, $settings[$key] ?? '') }}</textarea></label>
            @endforeach
        </details>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {{-- Tabs Selection List --}}
            <div class="lg:col-span-4 bg-white border border-cream-150 rounded-2xl p-4 shadow-sm space-y-1">
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider px-3 mb-2 block">Settings Tabs</span>
                
                <button type="button" @click="activeTab = 'general'"
                        :class="activeTab === 'general' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'"
                        class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
                    📞 Business Profile
                </button>
                @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                <button type="button" @click="activeTab = 'media'" :class="activeTab === 'media' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'" class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold">Branding & Gallery</button>
                @endif
                <button type="button" @click="activeTab = 'ordering'"
                        :class="activeTab === 'ordering' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'"
                        class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
                    🛒 Online Ordering
                </button>
                <button type="button" @click="activeTab = 'delivery'"
                        :class="activeTab === 'delivery' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'"
                        class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
                    🚚 Delivery Rates
                </button>
                <button type="button" @click="activeTab = 'payment'"
                        :class="activeTab === 'payment' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'"
                        class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
                    💳 Payment Credentials
                </button>
                <button type="button" @click="activeTab = 'emails'"
                        :class="activeTab === 'emails' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'"
                        class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
                    ✉️ Email Notifications
                </button>
            </div>

            {{-- Tab details --}}
            <div class="lg:col-span-8 bg-white border border-cream-150 rounded-2xl p-6 sm:p-8 shadow-sm">
                
                {{-- Business Profile Tab --}}
                <div x-show="activeTab === 'general'" class="space-y-4">
                    <h3 class="font-heading font-bold text-base text-espresso-800 border-b border-cream-100 pb-2 mb-4">Business Information</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="site_name" class="label-luxury">Website Name</label>
                            <input type="text" id="site_name" name="site_name" value="{{ $settings->get('site_name')?->value }}" class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="site_tagline" class="label-luxury">Tagline</label>
                            <input type="text" id="site_tagline" name="site_tagline" value="{{ $settings->get('site_tagline')?->value }}" class="input-luxury text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="site_email" class="label-luxury">Support Email</label>
                            <input type="email" id="site_email" name="site_email" value="{{ $settings->get('site_email')?->value }}" class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="site_phone" class="label-luxury">Contact Landline</label>
                            <input type="text" id="site_phone" name="site_phone" value="{{ $settings->get('site_phone')?->value }}" class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="site_whatsapp" class="label-luxury">WhatsApp Business</label>
                            <input type="text" id="site_whatsapp" name="site_whatsapp" value="{{ $settings->get('site_whatsapp')?->value }}" class="input-luxury text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="site_address" class="label-luxury">Physical Outlet Address</label>
                        <input type="text" id="site_address" name="site_address" value="{{ $settings->get('site_address')?->value }}" class="input-luxury text-sm">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-cream-100 pt-4 mt-4">
                        <div>
                            <label for="facebook_url" class="label-luxury">Facebook Page</label>
                            <input type="text" id="facebook_url" name="facebook_url" value="{{ $settings->get('facebook_url')?->value }}" class="input-luxury text-xs">
                        </div>
                        <div>
                            <label for="instagram_url" class="label-luxury">Instagram Page</label>
                            <input type="text" id="instagram_url" name="instagram_url" value="{{ $settings->get('instagram_url')?->value }}" class="input-luxury text-xs">
                        </div>
                        <div>
                            <label for="tiktok_url" class="label-luxury">TikTok Account</label>
                            <input type="text" id="tiktok_url" name="tiktok_url" value="{{ $settings->get('tiktok_url')?->value }}" class="input-luxury text-xs">
                        </div>
                    </div>
                </div>

                @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                <div x-show="activeTab === 'media'" x-cloak class="space-y-6">
                    <h3 class="font-heading font-bold text-base">Branding & Gallery</h3>
                    <p class="text-xs text-espresso-500">Choose public images from the media library. Clearing a selection restores the existing brand image or legacy gallery.</p>
                    <x-admin.media-picker name="site_logo_asset_id" label="Choose store logo" type="image" :value="old('site_logo_asset_id', $settings->get('site_logo')?->libraryAssetIds('store.logo')[0] ?? null)" />
                    @error('site_logo_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    <x-admin.media-picker name="site_favicon_asset_id" label="Choose favicon" type="image" :value="old('site_favicon_asset_id', $settings->get('site_favicon')?->libraryAssetIds('store.favicon')[0] ?? null)" />
                    @error('site_favicon_asset_id') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    <x-admin.media-picker name="homepage_gallery_asset_ids" label="Choose site gallery images" type="image" :multiple="true" :value="old('homepage_gallery_asset_ids', old('homepage_gallery_asset_ids_present') ? [] : ($settings->get('homepage_gallery')?->libraryAssetIds('content.gallery') ?? []))" />
                    @error('homepage_gallery_asset_ids') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    @error('homepage_gallery_asset_ids.*') <p class="text-red-700 text-xs">{{ $message }}</p> @enderror
                    <p class="text-xs text-espresso-500">Up to 30 images, in the order shown, on the homepage and gallery page.</p>
                </div>
                @endif

                {{-- Online Ordering Tab --}}
                <fieldset @disabled(!plugin_active('mavyn.store-availability')) x-show="activeTab === 'ordering'" class="space-y-4">
                    @unless(plugin_active('mavyn.store-availability'))<p class="text-sm">This plugin is disabled. Enable it from Plugins to change these settings.</p>@endunless
                    <h3 class="font-heading font-bold text-base text-espresso-800 border-b border-cream-100 pb-2 mb-4">Online Ordering</h3>
                    <p class="text-xs text-espresso-500">Turn online ordering on or off without a code deploy. When disabled, customers can still browse the catalog, but add-to-cart and checkout are blocked. When enabled, orders are only accepted between the hours below ({{ config('site.timezone') }}, every day).</p>

                    <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                        <div class="flex items-center h-5">
                            <input type="hidden" name="ordering_enabled" value="0">
                            <input type="checkbox" id="ordering_enabled" name="ordering_enabled" value="1"
                                   {{ filter_var($settings->get('ordering_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                   class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                        </div>
                        <div class="flex-1 text-xs">
                            <label for="ordering_enabled" class="font-bold text-espresso-800 block cursor-pointer">Accept online orders</label>
                            <span class="text-espresso-500">When unchecked, the site shows a notice and pauses cart / checkout until you turn this back on.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="ordering_start_time" class="label-luxury">Ordering starts</label>
                            <input type="time" id="ordering_start_time" name="ordering_start_time"
                                   value="{{ substr($settings->get('ordering_start_time')?->value ?? '09:00', 0, 5) }}"
                                   class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="ordering_end_time" class="label-luxury">Ordering ends</label>
                            <input type="time" id="ordering_end_time" name="ordering_end_time"
                                   value="{{ substr($settings->get('ordering_end_time')?->value ?? '21:00', 0, 5) }}"
                                   class="input-luxury text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-espresso-500">Times use {{ config('site.timezone') }} and apply every day. Outside this window, customers can browse but cannot add to cart or check out — the pause popup is not shown.</p>
                </fieldset>

                {{-- Delivery Rates Tab --}}
                <fieldset @disabled(!plugin_active('mavyn.local-delivery')) x-show="activeTab === 'delivery'" class="space-y-4">
                    @unless(plugin_active('mavyn.local-delivery'))<p class="text-sm">This plugin is disabled. Enable it from Plugins to change these settings.</p>@endunless
                    <h3 class="font-heading font-bold text-base text-espresso-800 border-b border-cream-100 pb-2 mb-4">Delivery Configurations</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="delivery_fee" class="label-luxury">Standard Delivery Fee (PKR)</label>
                            <input type="number" min="0" step="0.01" id="delivery_fee" name="delivery_fee" value="{{ $settings->get('delivery_fee')?->value }}" class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="free_delivery_above" class="label-luxury">Free Delivery Threshold (PKR)</label>
                            <input type="number" min="0" step="0.01" id="free_delivery_above" name="free_delivery_above" value="{{ $settings->get('free_delivery_above')?->value }}" class="input-luxury text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="express_delivery_fee" class="label-luxury">Express/Urgent Delivery Fee (PKR)</label>
                            <input type="number" min="0" step="0.01" id="express_delivery_fee" name="express_delivery_fee" value="{{ $settings->get('express_delivery_fee')?->value }}" class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="same_day_cutoff" class="label-luxury">Same-day Order Cutoff (24h format)</label>
                            <input type="time" id="same_day_cutoff" name="same_day_cutoff" value="{{ $settings->get('same_day_cutoff')?->value }}" placeholder="e.g. 14:00" class="input-luxury text-sm font-mono">
                        </div>
                    </div>
                </fieldset>

                {{-- Payment Credentials Tab --}}
                <fieldset @disabled(!plugin_active('mavyn.manual-payments')) x-show="activeTab === 'payment'" class="space-y-6">
                    @unless(plugin_active('mavyn.manual-payments'))<p class="text-sm">This plugin is disabled. Enable it from Plugins to change these settings.</p>@endunless

                    <div class="space-y-3">
                        <h3 class="font-heading font-bold text-base text-espresso-800 border-b border-cream-100 pb-2">Payment Methods</h3>
                        <p class="text-xs text-espresso-500">Enable or disable payment options shown on the checkout page.</p>

                        <div class="space-y-3">
                            <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="payment_cod_enabled" value="0">
                                    <input type="checkbox" id="payment_cod_enabled" name="payment_cod_enabled" value="1"
                                           {{ filter_var($settings->get('payment_cod_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                                </div>
                                <div class="flex-1 text-xs">
                                    <label for="payment_cod_enabled" class="font-bold text-espresso-800 block cursor-pointer">Cash on Delivery (COD)</label>
                                    <span class="text-espresso-500">Allow customers to pay with cash at delivery.</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="payment_easypaisa_enabled" value="0">
                                    <input type="checkbox" id="payment_easypaisa_enabled" name="payment_easypaisa_enabled" value="1"
                                           {{ filter_var($settings->get('payment_easypaisa_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                                </div>
                                <div class="flex-1 text-xs">
                                    <label for="payment_easypaisa_enabled" class="font-bold text-espresso-800 block cursor-pointer">Easypaisa</label>
                                    <span class="text-espresso-500">Show Easypaisa wallet as a payment option at checkout.</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="payment_jazzcash_enabled" value="0">
                                    <input type="checkbox" id="payment_jazzcash_enabled" name="payment_jazzcash_enabled" value="1"
                                           {{ filter_var($settings->get('payment_jazzcash_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                                </div>
                                <div class="flex-1 text-xs">
                                    <label for="payment_jazzcash_enabled" class="font-bold text-espresso-800 block cursor-pointer">JazzCash</label>
                                    <span class="text-espresso-500">Show JazzCash wallet as a payment option at checkout.</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="payment_bank_transfer_enabled" value="0">
                                    <input type="checkbox" id="payment_bank_transfer_enabled" name="payment_bank_transfer_enabled" value="1"
                                           {{ filter_var($settings->get('payment_bank_transfer_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                                </div>
                                <div class="flex-1 text-xs">
                                    <label for="payment_bank_transfer_enabled" class="font-bold text-espresso-800 block cursor-pointer">Bank Transfer</label>
                                    <span class="text-espresso-500">Show direct bank transfer as a payment option at checkout.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Easypaisa --}}
                    <div class="space-y-3 pt-2">
                        <h4 class="font-heading font-bold text-xs text-green-700 uppercase tracking-wider border-b border-cream-100 pb-1">Easypaisa Wallet</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="easypaisa_account" class="label-luxury">Account Number</label>
                                <input type="text" id="easypaisa_account" name="easypaisa_account" value="{{ $settings->get('easypaisa_account')?->value }}" class="input-luxury text-xs font-mono">
                            </div>
                            <div>
                                <label for="easypaisa_name" class="label-luxury">Account Title Name</label>
                                <input type="text" id="easypaisa_name" name="easypaisa_name" value="{{ $settings->get('easypaisa_name')?->value }}" class="input-luxury text-xs">
                            </div>
                        </div>
                    </div>

                    {{-- JazzCash --}}
                    <div class="space-y-3 pt-2">
                        <h4 class="font-heading font-bold text-xs text-red-650 uppercase tracking-wider border-b border-cream-100 pb-1">JazzCash Wallet</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="jazzcash_account" class="label-luxury">Account Number</label>
                                <input type="text" id="jazzcash_account" name="jazzcash_account" value="{{ $settings->get('jazzcash_account')?->value }}" class="input-luxury text-xs font-mono">
                            </div>
                            <div>
                                <label for="jazzcash_name" class="label-luxury">Account Title Name</label>
                                <input type="text" id="jazzcash_name" name="jazzcash_name" value="{{ $settings->get('jazzcash_name')?->value }}" class="input-luxury text-xs">
                            </div>
                        </div>
                    </div>

                    {{-- Bank Transfer --}}
                    <div class="space-y-3 pt-2">
                        <h4 class="font-heading font-bold text-xs text-primary-600 uppercase tracking-wider border-b border-cream-100 pb-1">Bank Account Transfer</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="bank_name" class="label-luxury">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" value="{{ $settings->get('bank_name')?->value }}" class="input-luxury text-xs">
                            </div>
                            <div>
                                <label for="bank_title" class="label-luxury">Account Title</label>
                                <input type="text" id="bank_title" name="bank_title" value="{{ $settings->get('bank_title')?->value }}" class="input-luxury text-xs">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="bank_account" class="label-luxury">Account Number</label>
                                <input type="text" id="bank_account" name="bank_account" value="{{ $settings->get('bank_account')?->value }}" class="input-luxury text-xs font-mono">
                            </div>
                            <div>
                                <label for="bank_iban" class="label-luxury">IBAN</label>
                                <input type="text" id="bank_iban" name="bank_iban" value="{{ $settings->get('bank_iban')?->value }}" class="input-luxury text-xs font-mono">
                            </div>
                        </div>
                    </div>

                </fieldset>

                {{-- Email Notifications Tab --}}
                <div x-show="activeTab === 'emails'" class="space-y-6">
                    <h3 class="font-heading font-bold text-base text-espresso-800 border-b border-cream-100 pb-2 mb-4">Email Toggles</h3>
                    <p class="text-xs text-espresso-500">Configure which email notifications are active. Unchecking a toggle will disable sending that email immediately.</p>

                    <div class="space-y-4">
                        {{-- Order Confirmation Email --}}
                        <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                            <div class="flex items-center h-5">
                                <input type="hidden" name="email_order_confirmation" value="0">
                                <input type="checkbox" id="email_order_confirmation" name="email_order_confirmation" value="1"
                                       {{ filter_var($settings->get('email_order_confirmation')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                            </div>
                            <div class="flex-1 text-xs">
                                <label for="email_order_confirmation" class="font-bold text-espresso-800 block cursor-pointer">Order Confirmation Emails</label>
                                <span class="text-espresso-500">Sent to customers immediately after placing a new order.</span>
                            </div>
                        </div>

                        {{-- Account Signup Welcome Email --}}
                        <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                            <div class="flex items-center h-5">
                                <input type="hidden" name="email_account_signup" value="0">
                                <input type="checkbox" id="email_account_signup" name="email_account_signup" value="1"
                                       {{ filter_var($settings->get('email_account_signup')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                            </div>
                            <div class="flex-1 text-xs">
                                <label for="email_account_signup" class="font-bold text-espresso-800 block cursor-pointer">Account Welcome Emails</label>
                                <span class="text-espresso-500">Sent to users when they successfully register a new account.</span>
                            </div>
                        </div>

                        {{-- Forgot Password Email --}}
                        <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                            <div class="flex items-center h-5">
                                <input type="hidden" name="email_forgot_password" value="0">
                                <input type="checkbox" id="email_forgot_password" name="email_forgot_password" value="1"
                                       {{ filter_var($settings->get('email_forgot_password')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                            </div>
                            <div class="flex-1 text-xs">
                                <label for="email_forgot_password" class="font-bold text-espresso-800 block cursor-pointer">Forgot Password Emails</label>
                                <span class="text-espresso-500">Sent to users requesting a link to reset their account password.</span>
                            </div>
                        </div>

                        {{-- Order Update Email --}}
                        <div class="flex items-start gap-4 p-4 border border-cream-100 rounded-xl hover:bg-cream-50/50 transition-colors">
                            <div class="flex items-center h-5">
                                <input type="hidden" name="email_order_update" value="0">
                                <input type="checkbox" id="email_order_update" name="email_order_update" value="1"
                                       {{ filter_var($settings->get('email_order_update')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="rounded border-cream-300 text-primary-500 focus:ring-primary-300 h-4 w-4">
                            </div>
                            <div class="flex-1 text-xs">
                                <label for="email_order_update" class="font-bold text-espresso-800 block cursor-pointer">Order Delivery & Status Updates</label>
                                <span class="text-espresso-500">Sent to customers when an administrator updates their order status (e.g. to Processing, Shipped, Delivered).</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-6 border-t border-cream-100 mt-6 justify-end">
                    <button type="submit" class="btn-gold py-2.5 px-6 text-xs font-semibold">
                        Save Configurations
                    </button>
                </div>
            </div>

        </div>

    </form>

</x-admin-layout>
