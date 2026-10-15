<x-admin-layout title="Admin general settings">

    {{-- Header --}}
    <div class="mb-5 border-bottom border-light-subtle pb-3">
        <h1 class="text-body admin-page-title">General settings</h1>
        <p class="small text-body-secondary mt-1">Configure company profiles, delivery charges, and manual payment details.</p>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-4 rounded-2 border border-danger-subtle bg-danger-subtle p-3 text-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="admin-stack-6" x-data="{ activeTab: 'general' }">
        @csrf
        <input type="hidden" name="pricing_revision" value="{{ app(\Mavyn\Contracts\Pricing\PriceBook::class)->context()->revision }}">
        @method('PUT')

        <details class="card bg-white border rounded-2 p-4">
            <summary class="fw-semibold">Regional and financial settings</summary>
            <p class="small text-body-secondary mt-3">Tax is added to discounted merchandise; fulfillment charges are excluded. Manage selling currencies and their prices from the currency-management plugin.</p>
            @foreach(['store_currency' => 'Currency code', 'store_currency_symbol' => 'Currency symbol', 'store_tax_rate' => 'Tax rate (%)', 'store_country' => 'Country', 'store_country_code' => 'Country code (two letters)', 'store_timezone' => 'Timezone', 'store_locale' => 'Language (en)'] as $key => $label)
                <label class="d-block mt-3">{{ $label }}
                    <input class="form-control mt-1" name="{{ $key }}" value="{{ old($key, $settings->get($key)?->value) }}" @readonly(in_array($key, ['store_currency', 'store_currency_symbol'], true)) required>
                </label>
                @error($key)<p class="text-danger small">{{ $message }}</p>@enderror
            @endforeach
        </details>

        <details class="card bg-white border rounded-2 p-4"><summary class="fw-semibold admin-clickable">Store information and policy text</summary>
            <p class="small mt-2">Plain text used by the default storefront. Content stays with the store when themes change.</p>
            @foreach(['site_city' => 'City', 'about_text' => 'About us', 'shipping_policy' => 'Shipping and delivery', 'returns_policy' => 'Returns and refunds', 'privacy_policy' => 'Privacy policy', 'terms_policy' => 'Terms and conditions'] as $key => $label)
                <label class="d-block mt-3">{{ $label }}<textarea class="form-control mt-1" name="{{ $key }}" rows="4" maxlength="20000">{{ old($key, $settings[$key] ?? '') }}</textarea></label>
            @endforeach
        </details>

        <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">
            
            {{-- Tabs Selection List --}}
            <div class="card admin-span-lg-4 bg-white border border-light-subtle rounded-2 p-3 shadow-sm admin-stack-1">
                <span class="text-body-secondary small fw-semibold admin-letter-spacing px-3 mb-2 d-block">Settings Tabs</span>
                
                <button type="button" @click="activeTab = 'general'"
                        :class="activeTab === 'general' ? 'bg-primary-subtle text-primary' : 'text-body '"
                        class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">
                    📞 Business profile
                </button>
                @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                <button type="button" @click="activeTab = 'media'" :class="activeTab === 'media' ? 'bg-primary-subtle text-primary' : 'text-body '" class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">Branding & gallery</button>
                @endif
                <button type="button" @click="activeTab = 'ordering'"
                        :class="activeTab === 'ordering' ? 'bg-primary-subtle text-primary' : 'text-body '"
                        class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">
                    🛒 Online ordering
                </button>
                <button type="button" @click="activeTab = 'delivery'"
                        :class="activeTab === 'delivery' ? 'bg-primary-subtle text-primary' : 'text-body '"
                        class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">
                    🚚 Delivery rates
                </button>
                <button type="button" @click="activeTab = 'payment'"
                        :class="activeTab === 'payment' ? 'bg-primary-subtle text-primary' : 'text-body '"
                        class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">
                    💳 Payment credentials
                </button>
                <button type="button" @click="activeTab = 'emails'"
                        :class="activeTab === 'emails' ? 'bg-primary-subtle text-primary' : 'text-body '"
                        class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">
                    ✉️ Email notifications
                </button>
            </div>

            {{-- Tab details --}}
            <div class="card admin-span-lg-8 bg-white border border-light-subtle rounded-2 p-4 p-sm-5 shadow-sm">
                
                {{-- Business Profile Tab --}}
                <div x-show.important="activeTab === 'general'" class="admin-stack-4">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Business information</h3>

                    <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                        <div>
                            <label for="site_name" class="form-label">Website name</label>
                            <input type="text" id="site_name" name="site_name" value="{{ $settings->get('site_name')?->value }}" class="form-control small">
                        </div>
                        <div>
                            <label for="site_tagline" class="form-label">Tagline</label>
                            <input type="text" id="site_tagline" name="site_tagline" value="{{ $settings->get('site_tagline')?->value }}" class="form-control small">
                        </div>
                    </div>

                    <div class="d-grid admin-cols-1 admin-cols-sm-3 gap-4">
                        <div>
                            <label for="site_email" class="form-label">Support email</label>
                            <input type="email" id="site_email" name="site_email" value="{{ $settings->get('site_email')?->value }}" class="form-control small">
                        </div>
                        <div>
                            <label for="site_phone" class="form-label">Contact landline</label>
                            <input type="text" id="site_phone" name="site_phone" value="{{ $settings->get('site_phone')?->value }}" class="form-control small">
                        </div>
                        <div>
                            <label for="site_whatsapp" class="form-label">WhatsApp business</label>
                            <input type="text" id="site_whatsapp" name="site_whatsapp" value="{{ $settings->get('site_whatsapp')?->value }}" class="form-control small">
                        </div>
                    </div>

                    <div>
                        <label for="site_address" class="form-label">Physical outlet address</label>
                        <input type="text" id="site_address" name="site_address" value="{{ $settings->get('site_address')?->value }}" class="form-control small">
                    </div>

                    <div class="d-grid admin-cols-1 admin-cols-sm-3 gap-4 border-top border-light-subtle pt-3 mt-3">
                        <div>
                            <label for="facebook_url" class="form-label">Facebook page</label>
                            <input type="text" id="facebook_url" name="facebook_url" value="{{ $settings->get('facebook_url')?->value }}" class="form-control small">
                        </div>
                        <div>
                            <label for="instagram_url" class="form-label">Instagram page</label>
                            <input type="text" id="instagram_url" name="instagram_url" value="{{ $settings->get('instagram_url')?->value }}" class="form-control small">
                        </div>
                        <div>
                            <label for="tiktok_url" class="form-label">Tiktok account</label>
                            <input type="text" id="tiktok_url" name="tiktok_url" value="{{ $settings->get('tiktok_url')?->value }}" class="form-control small">
                        </div>
                    </div>
                </div>

                @if(\Illuminate\Support\Facades\Gate::forUser(auth('admin')->user())->allows('manage-media'))
                <div x-show.important="activeTab === 'media'" x-cloak class="admin-stack-6">
                    <h3 class="admin-section-title">Branding & gallery</h3>
                    <p class="small text-body-secondary">Choose public images from the media library. Clearing a selection restores the existing brand image or legacy gallery.</p>
                    <x-admin.media-picker name="site_logo_asset_id" label="Choose store logo" type="image" :value="old('site_logo_asset_id', $settings->get('site_logo')?->libraryAssetIds('store.logo')[0] ?? null)" />
                    @error('site_logo_asset_id') <p class="text-danger small">{{ $message }}</p> @enderror
                    <x-admin.media-picker name="site_favicon_asset_id" label="Choose favicon" type="image" :value="old('site_favicon_asset_id', $settings->get('site_favicon')?->libraryAssetIds('store.favicon')[0] ?? null)" />
                    @error('site_favicon_asset_id') <p class="text-danger small">{{ $message }}</p> @enderror
                    <x-admin.media-picker name="homepage_gallery_asset_ids" label="Choose site gallery images" type="image" :multiple="true" :value="old('homepage_gallery_asset_ids', old('homepage_gallery_asset_ids_present') ? [] : ($settings->get('homepage_gallery')?->libraryAssetIds('content.gallery') ?? []))" />
                    @error('homepage_gallery_asset_ids') <p class="text-danger small">{{ $message }}</p> @enderror
                    @error('homepage_gallery_asset_ids.*') <p class="text-danger small">{{ $message }}</p> @enderror
                    <p class="small text-body-secondary">Up to 30 images, in the order shown, on the homepage and gallery page.</p>
                </div>
                @endif

                {{-- Online Ordering Tab --}}
                <fieldset @disabled(!plugin_active('mavyn.store-availability')) x-show.important="activeTab === 'ordering'" class="admin-stack-4">
                    @unless(plugin_active('mavyn.store-availability'))<p class="small">This plugin is disabled. Enable it from Plugins to change these settings.</p>@endunless
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Online ordering</h3>
                    <p class="small text-body-secondary">Turn online ordering on or off without a code deploy. When disabled, customers can still browse the catalog, but add-to-cart and checkout are blocked. When enabled, orders are only accepted between the hours below ({{ config('site.timezone') }}, every day).</p>

                    <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                        <div class="d-flex align-items-center admin-height-5">
                            <input type="hidden" name="ordering_enabled" value="0">
                            <input type="checkbox" id="ordering_enabled" name="ordering_enabled" value="1"
                                   {{ filter_var($settings->get('ordering_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                   class="form-check-input">
                        </div>
                        <div class="flex-grow-1">
                            <label for="ordering_enabled" class="fw-semibold text-body d-block admin-clickable">Accept online orders</label>
                            <span class="text-body-secondary">When unchecked, the site shows a notice and pauses cart / checkout until you turn this back on.</span>
                        </div>
                    </div>

                    <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                        <div>
                            <label for="ordering_start_time" class="form-label">Ordering starts</label>
                            <input type="time" id="ordering_start_time" name="ordering_start_time"
                                   value="{{ substr($settings->get('ordering_start_time')?->value ?? '09:00', 0, 5) }}"
                                   class="form-control small">
                        </div>
                        <div>
                            <label for="ordering_end_time" class="form-label">Ordering ends</label>
                            <input type="time" id="ordering_end_time" name="ordering_end_time"
                                   value="{{ substr($settings->get('ordering_end_time')?->value ?? '21:00', 0, 5) }}"
                                   class="form-control small">
                        </div>
                    </div>
                    <p class="small text-body-secondary">Times use {{ config('site.timezone') }} and apply every day. Outside this window, customers can browse but cannot add to cart or check out — the pause popup is not shown.</p>
                </fieldset>

                {{-- Delivery Rates Tab --}}
                <fieldset @disabled(!plugin_active('mavyn.local-delivery')) x-show.important="activeTab === 'delivery'" class="admin-stack-4">
                    @unless(plugin_active('mavyn.local-delivery'))<p class="small">This plugin is disabled. Enable it from Plugins to change these settings.</p>@endunless
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Delivery configurations</h3>

                    <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                        <div>
                            <label for="delivery_fee" class="form-label">Standard delivery fee (PKR)</label>
                            <input type="number" min="0" step="0.01" id="delivery_fee" name="delivery_fee" value="{{ $settings->get('delivery_fee')?->value }}" class="form-control small num">
                        </div>
                        <div>
                            <label for="free_delivery_above" class="form-label">Free delivery threshold (PKR)</label>
                            <input type="number" min="0" step="0.01" id="free_delivery_above" name="free_delivery_above" value="{{ $settings->get('free_delivery_above')?->value }}" class="form-control small num">
                        </div>
                    </div>

                    <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                        <div>
                            <label for="express_delivery_fee" class="form-label">Express/urgent delivery fee (PKR)</label>
                            <input type="number" min="0" step="0.01" id="express_delivery_fee" name="express_delivery_fee" value="{{ $settings->get('express_delivery_fee')?->value }}" class="form-control small num">
                        </div>
                        <div>
                            <label for="same_day_cutoff" class="form-label">Same-day order cutoff (24h format)</label>
                            <input type="time" id="same_day_cutoff" name="same_day_cutoff" value="{{ $settings->get('same_day_cutoff')?->value }}" placeholder="e.g. 14:00" class="form-control small font-monospace">
                        </div>
                    </div>
                </fieldset>

                {{-- Payment Credentials Tab --}}
                <fieldset @disabled(!plugin_active('mavyn.manual-payments')) x-show.important="activeTab === 'payment'" class="admin-stack-6">
                    @unless(plugin_active('mavyn.manual-payments'))<p class="small">This plugin is disabled. Enable it from Plugins to change these settings.</p>@endunless

                    <div class="admin-stack-3">
                        <h3 class="text-body border-bottom border-light-subtle pb-2 admin-section-title">Payment methods</h3>
                        <p class="small text-body-secondary">Enable or disable payment options shown on the checkout page.</p>

                        <div class="admin-stack-3">
                            <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                                <div class="d-flex align-items-center admin-height-5">
                                    <input type="hidden" name="payment_cod_enabled" value="0">
                                    <input type="checkbox" id="payment_cod_enabled" name="payment_cod_enabled" value="1"
                                           {{ filter_var($settings->get('payment_cod_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="form-check-input">
                                </div>
                                <div class="flex-grow-1">
                                    <label for="payment_cod_enabled" class="fw-semibold text-body d-block admin-clickable">Cash on delivery (COD)</label>
                                    <span class="text-body-secondary">Allow customers to pay with cash at delivery.</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                                <div class="d-flex align-items-center admin-height-5">
                                    <input type="hidden" name="payment_easypaisa_enabled" value="0">
                                    <input type="checkbox" id="payment_easypaisa_enabled" name="payment_easypaisa_enabled" value="1"
                                           {{ filter_var($settings->get('payment_easypaisa_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="form-check-input">
                                </div>
                                <div class="flex-grow-1">
                                    <label for="payment_easypaisa_enabled" class="fw-semibold text-body d-block admin-clickable">Easypaisa</label>
                                    <span class="text-body-secondary">Show Easypaisa wallet as a payment option at checkout.</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                                <div class="d-flex align-items-center admin-height-5">
                                    <input type="hidden" name="payment_jazzcash_enabled" value="0">
                                    <input type="checkbox" id="payment_jazzcash_enabled" name="payment_jazzcash_enabled" value="1"
                                           {{ filter_var($settings->get('payment_jazzcash_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="form-check-input">
                                </div>
                                <div class="flex-grow-1">
                                    <label for="payment_jazzcash_enabled" class="fw-semibold text-body d-block admin-clickable">JazzCash</label>
                                    <span class="text-body-secondary">Show JazzCash wallet as a payment option at checkout.</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                                <div class="d-flex align-items-center admin-height-5">
                                    <input type="hidden" name="payment_bank_transfer_enabled" value="0">
                                    <input type="checkbox" id="payment_bank_transfer_enabled" name="payment_bank_transfer_enabled" value="1"
                                           {{ filter_var($settings->get('payment_bank_transfer_enabled')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                           class="form-check-input">
                                </div>
                                <div class="flex-grow-1">
                                    <label for="payment_bank_transfer_enabled" class="fw-semibold text-body d-block admin-clickable">Bank transfer</label>
                                    <span class="text-body-secondary">Show direct bank transfer as a payment option at checkout.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Easypaisa --}}
                    <div class="admin-stack-3 pt-2">
                        <h4 class="text-success admin-letter-spacing border-bottom border-light-subtle pb-1 admin-section-title">Easypaisa wallet</h4>
                        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                            <div>
                                <label for="easypaisa_account" class="form-label">Account number</label>
                                <input type="text" id="easypaisa_account" name="easypaisa_account" value="{{ $settings->get('easypaisa_account')?->value }}" class="form-control small font-monospace">
                            </div>
                            <div>
                                <label for="easypaisa_name" class="form-label">Account title name</label>
                                <input type="text" id="easypaisa_name" name="easypaisa_name" value="{{ $settings->get('easypaisa_name')?->value }}" class="form-control small">
                            </div>
                        </div>
                    </div>

                    {{-- JazzCash --}}
                    <div class="admin-stack-3 pt-2">
                        <h4 class="text-danger admin-letter-spacing border-bottom border-light-subtle pb-1 admin-section-title">JazzCash wallet</h4>
                        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                            <div>
                                <label for="jazzcash_account" class="form-label">Account number</label>
                                <input type="text" id="jazzcash_account" name="jazzcash_account" value="{{ $settings->get('jazzcash_account')?->value }}" class="form-control small font-monospace">
                            </div>
                            <div>
                                <label for="jazzcash_name" class="form-label">Account title name</label>
                                <input type="text" id="jazzcash_name" name="jazzcash_name" value="{{ $settings->get('jazzcash_name')?->value }}" class="form-control small">
                            </div>
                        </div>
                    </div>

                    {{-- Bank Transfer --}}
                    <div class="admin-stack-3 pt-2">
                        <h4 class="text-primary admin-letter-spacing border-bottom border-light-subtle pb-1 admin-section-title">Bank account transfer</h4>
                        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                            <div>
                                <label for="bank_name" class="form-label">Bank name</label>
                                <input type="text" id="bank_name" name="bank_name" value="{{ $settings->get('bank_name')?->value }}" class="form-control small">
                            </div>
                            <div>
                                <label for="bank_title" class="form-label">Account title</label>
                                <input type="text" id="bank_title" name="bank_title" value="{{ $settings->get('bank_title')?->value }}" class="form-control small">
                            </div>
                        </div>
                        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                            <div>
                                <label for="bank_account" class="form-label">Account number</label>
                                <input type="text" id="bank_account" name="bank_account" value="{{ $settings->get('bank_account')?->value }}" class="form-control small font-monospace">
                            </div>
                            <div>
                                <label for="bank_iban" class="form-label">Iban</label>
                                <input type="text" id="bank_iban" name="bank_iban" value="{{ $settings->get('bank_iban')?->value }}" class="form-control small font-monospace">
                            </div>
                        </div>
                    </div>

                </fieldset>

                {{-- Email Notifications Tab --}}
                <div x-show.important="activeTab === 'emails'" class="admin-stack-6">
                    <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Email toggles</h3>
                    <p class="small text-body-secondary">Configure which email notifications are active. Unchecking a toggle will disable sending that email immediately.</p>

                    <div class="admin-stack-4">
                        {{-- Order Confirmation Email --}}
                        <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                            <div class="d-flex align-items-center admin-height-5">
                                <input type="hidden" name="email_order_confirmation" value="0">
                                <input type="checkbox" id="email_order_confirmation" name="email_order_confirmation" value="1"
                                       {{ filter_var($settings->get('email_order_confirmation')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="form-check-input">
                            </div>
                            <div class="flex-grow-1">
                                <label for="email_order_confirmation" class="fw-semibold text-body d-block admin-clickable">Order confirmation emails</label>
                                <span class="text-body-secondary">Sent to customers immediately after placing a new order.</span>
                            </div>
                        </div>

                        {{-- Account Signup Welcome Email --}}
                        <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                            <div class="d-flex align-items-center admin-height-5">
                                <input type="hidden" name="email_account_signup" value="0">
                                <input type="checkbox" id="email_account_signup" name="email_account_signup" value="1"
                                       {{ filter_var($settings->get('email_account_signup')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="form-check-input">
                            </div>
                            <div class="flex-grow-1">
                                <label for="email_account_signup" class="fw-semibold text-body d-block admin-clickable">Account welcome emails</label>
                                <span class="text-body-secondary">Sent to users when they successfully register a new account.</span>
                            </div>
                        </div>

                        {{-- Forgot Password Email --}}
                        <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                            <div class="d-flex align-items-center admin-height-5">
                                <input type="hidden" name="email_forgot_password" value="0">
                                <input type="checkbox" id="email_forgot_password" name="email_forgot_password" value="1"
                                       {{ filter_var($settings->get('email_forgot_password')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="form-check-input">
                            </div>
                            <div class="flex-grow-1">
                                <label for="email_forgot_password" class="fw-semibold text-body d-block admin-clickable">Forgot password emails</label>
                                <span class="text-body-secondary">Sent to users requesting a link to reset their account password.</span>
                            </div>
                        </div>

                        {{-- Order Update Email --}}
                        <div class="d-flex align-items-start gap-4 p-3 border border-light-subtle rounded-2">
                            <div class="d-flex align-items-center admin-height-5">
                                <input type="hidden" name="email_order_update" value="0">
                                <input type="checkbox" id="email_order_update" name="email_order_update" value="1"
                                       {{ filter_var($settings->get('email_order_update')?->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                                       class="form-check-input">
                            </div>
                            <div class="flex-grow-1">
                                <label for="email_order_update" class="fw-semibold text-body d-block admin-clickable">Order delivery & status updates</label>
                                <span class="text-body-secondary">Sent to customers when an administrator updates their order status (e.g. to Processing, Shipped, Delivered).</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 pt-4 border-top border-light-subtle mt-4 justify-content-end">
                    <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">
                        Save configurations
                    </button>
                </div>
            </div>

        </div>

    </form>

</x-admin-layout>
