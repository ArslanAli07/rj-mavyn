<x-app-layout title="Secure Checkout">
    <style>
        /* ---------- Checkout: two-column layout (details left, summary right) ---------- */
        .ck-page {max-width: 1120px;margin: 0 auto;padding: 36px 20px 88px;}
        .ck-head {margin-bottom: 28px;}
        .ck-head h1 {font-size: clamp(24px, 3vw, 32px);font-weight: 800;letter-spacing: -0.01em;margin: 0 0 8px;color: #17120f;}
        .ck-head p {font-size: 13px;color: #6f6f6f;margin: 0;}
        #checkout-message:empty {display: none;}
        .ck-alert {border: 1px solid #ececec;border-left: 3px solid #17120f;background: #fafafa;padding: 12px 16px;font-size: 13px;line-height: 1.5;color: #17120f;margin-bottom: 24px;}
        .ck-alert a {color: #17120f;text-decoration: underline;text-underline-offset: 2px;}
        .ck-layout {display: grid;grid-template-columns: minmax(0, 1fr) 400px;gap: 48px;align-items: start;}

        /* ---------- Left column: details ---------- */
        .ck-section {margin-bottom: 36px;}
        .ck-section-title {font-size: 15px;font-weight: 700;letter-spacing: 0.01em;color: #17120f;margin: 0 0 14px;}
        .ck-note {font-size: 12px;color: #8a8a8a;margin: -8px 0 12px;}
        .ck-grid {display: grid;grid-template-columns: 1fr 1fr;gap: 14px;}
        .ck-field {display: flex;flex-direction: column;gap: 6px;}
        .ck-field--full {grid-column: 1 / -1;}
        .ck-field label {font-size: 11px;font-weight: 700;letter-spacing: 0.08em;text-transform: uppercase;color: #17120f;}
        .ck-input {width: 100%;height: 44px;padding: 0 12px;border: 1px solid #e5e5e5;border-radius: 0;background: #fff;font-size: 13px;font-family: inherit;color: #17120f;box-sizing: border-box;transition: border-color 0.15s ease;}
        textarea.ck-input {height: auto;min-height: 88px;padding: 10px 12px;resize: vertical;}
        select.ck-input {-webkit-appearance: none;appearance: none;cursor:pointer;}
        .ck-input:focus {outline: none;border-color: #17120f;}
        .ck-select-wrap {position: relative;}
        .ck-select-wrap svg {position: absolute;right: 12px;top: 50%;transform: translateY(-50%);width: 14px;height: 14px;pointer-events: none;color: #6f6f6f;}

        /* ---------- Option cards (shipping & payment) ---------- */
        .ck-options {display: flex;flex-direction: column;gap: 10px;}
        .ck-option {display: flex;align-items: center;gap: 12px;border: 1px solid #e5e5e5;background: #fff;padding: 14px 16px;cursor: pointer;transition: border-color 0.15s ease, box-shadow 0.15s ease;}
        .ck-option:hover {border-color: #17120f;}
        .ck-option:has(input:checked) {border-color: #17120f;box-shadow: inset 0 0 0 1px #17120f;}
        .ck-option input {-webkit-appearance: none;appearance: none;width: 16px;height: 16px;margin: 0;border: 1.5px solid #8a8a8a;border-radius: 50%;background: #fff;flex-shrink: 0;display: inline-grid;place-content: center;cursor: pointer;transition: border-color 0.15s ease;}
        .ck-option input::before {content: "";width: 8px;height: 8px;border-radius: 50%;background: #17120f;transform: scale(0);transition: transform 0.12s ease;}
        .ck-option input:checked {border-color: #17120f;}
        .ck-option input:checked::before {transform: scale(1);}
        .ck-option input:focus-visible {outline: 2px solid #17120f;outline-offset: 2px;}
        .ck-option__label {flex: 1;min-width: 0;font-size: 13px;font-weight: 600;color: #17120f;text-align: left;}
        .ck-option__price {flex-shrink: 0;font-size: 13px;font-weight: 700;color: #17120f;white-space: nowrap;}
        .ck-payment-instructions {font-size: 12.5px;line-height: 1.6;color: #6f6f6f;white-space: pre-line;margin-top: 4px;}
        .ck-payment-instructions:empty {display: none;}
        .ck-receipt {display: flex;flex-direction: column;gap: 6px;margin-top: 14px;}
        .ck-receipt[hidden] {display: none;}
        .ck-receipt label {font-size: 11px;font-weight: 700;letter-spacing: 0.08em;text-transform: uppercase;color: #17120f;}
        .ck-file {font-size: 13px;color: #17120f;}
        .ck-file::file-selector-button {border: 1px solid #e5e5e5;background: #fafafa;padding: 8px 14px;font-size: 12px;font-weight: 700;letter-spacing: 0.06em;text-transform: uppercase;cursor: pointer;margin-right: 12px;font-family: inherit;color: #17120f;}
        .ck-file::file-selector-button:hover {border-color: #17120f;}

        /* ---------- Right column: order summary ---------- */
        .ck-summary {position: sticky;top: 88px;background: #fafafa;border: 1px solid #ececec;padding: 24px;}
        .ck-summary-title {font-size: 15px;font-weight: 700;color: #17120f;margin: 0 0 18px;}
        .ck-items {display: flex;flex-direction: column;gap: 14px;margin-bottom: 18px;}
        .ck-item {display: flex;align-items: flex-start;gap: 12px;}
        .ck-item__thumb {position: relative;width: 56px;height: 68px;flex-shrink: 0;background: #fff;border: 1px solid #ececec;overflow: hidden;}
        .ck-item__thumb img {width: 100%;height: 100%;object-fit: cover;display: block;}
        .ck-item__qty {position: absolute;top: -1px;right: -1px;min-width: 18px;height: 18px;background: #17120f;color: #fff;font-size: 10px;font-weight: 700;display: flex;align-items: center;justify-content: center;padding: 0 4px;}
        .ck-item__info {flex: 1;min-width: 0;}
        .ck-item__title {font-size: 13px;font-weight: 700;color: #17120f;line-height: 1.35;margin: 0;overflow: hidden;display: -webkit-box;-webkit-line-clamp: 2;-webkit-box-orient: vertical;}
        .ck-item__meta {font-size: 12px;color: #8a8a8a;margin: 3px 0 0;}
        .ck-item__price {flex-shrink: 0;font-size: 13px;font-weight: 700;color: #17120f;white-space: nowrap;margin: 0;}
        .ck-coupon {display: flex;gap: 8px;margin-bottom: 18px;}
        .ck-coupon input {flex: 1;min-width: 0;height: 40px;padding: 0 12px;border: 1px solid #e5e5e5;background: #fff;font-size: 12.5px;font-family: inherit;color: #17120f;box-sizing: border-box;}
        .ck-coupon input:focus {outline: none;border-color: #17120f;}
        .ck-coupon button {height: 40px;padding: 0 16px;border: 1px solid #17120f;background: #fff;color: #17120f;font-size: 11px;font-weight: 700;letter-spacing: 0.08em;text-transform: uppercase;cursor: pointer;transition: background 0.15s ease, color 0.15s ease;font-family: inherit;flex-shrink: 0;}
        .ck-coupon button:hover {background: #17120f;color: #fff;}
        .ck-coupon-remove {background: none;border: none;color: #8a8a8a;font-size: 11px;font-weight: 700;letter-spacing: 0.08em;text-transform: uppercase;cursor: pointer;padding: 0;margin: 0 0 14px;text-decoration: underline;text-underline-offset: 2px;font-family: inherit;}
        .ck-coupon-remove:hover {color: #17120f;}
        .ck-rows {border-top: 1px solid #ececec;padding-top: 16px;display: flex;flex-direction: column;gap: 10px;}
        .ck-row {display: flex;align-items: baseline;justify-content: space-between;gap: 12px;font-size: 13px;color: #373737;}
        .ck-row span:last-child {font-weight: 600;color: #17120f;white-space: nowrap;}
        .ck-row--total {margin-top: 6px;padding-top: 14px;border-top: 1px solid #ececec;font-size: 14px;font-weight: 700;color: #17120f;}
        .ck-row--total span:last-child {font-size: 18px;font-weight: 800;}
        .ck-rows__note {font-size: 12px;color: #8a8a8a;margin: 2px 0 0;}
        .ck-actions {display: flex;flex-direction: column;gap: 10px;margin-top: 20px;}
        .ck-btn {width: 100%;height: 50px;border: 1px solid #17120f;font-size: 12px;font-weight: 700;letter-spacing: 0.12em;text-transform: uppercase;cursor: pointer;font-family: inherit;transition: background 0.15s ease, color 0.15s ease, opacity 0.15s ease;}
        .ck-btn--solid {background: #17120f;color: #fff;}
        .ck-btn--solid:hover:not(:disabled) {background: #000;}
        .ck-btn--solid:disabled {opacity: 0.45;cursor: not-allowed;}
        .ck-btn--ghost {background: #fff;color: #17120f;}
        .ck-btn--ghost:hover:not(:disabled) {background: #17120f;color: #fff;}
        .ck-btn--ghost:disabled {opacity: 0.45;cursor: not-allowed;}
        .ck-secure {display: flex;align-items: center;justify-content: center;gap: 6px;font-size: 11px;color: #8a8a8a;margin-top: 14px;}

        @media (max-width: 960px) {
            .ck-layout {grid-template-columns: 1fr;gap: 36px;}
            .ck-summary {position: static;order: -1;}
        }
        @media (max-width: 560px) {
            .ck-grid {grid-template-columns: 1fr;}
        }
    </style>

    <div class="ck-page">
        <header class="ck-head">
            <h1>Checkout</h1>
            <p>Selling currency: <strong>{{ $totals['currency'] }}</strong>. Review the final quote before confirming your order.</p>
        </header>
        <div id="checkout-message" role="alert" class="ck-alert" tabindex="-1"></div>
        @if($totals['has_unavailable'])
            <p role="alert" class="ck-alert">Some cart items are unavailable in {{ $totals['currency'] }}. <a href="{{ route('cart.index') }}">Review your cart</a>.</p>
        @endif

        <div class="ck-layout">
            {{-- ============ Left: Customer Details ============ --}}
            <form id="checkout-form" action="{{ route('checkout.place-order') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <x-honeypot />
                <input type="hidden" name="idempotency_key" value="{{ $checkoutKey }}">
                <input type="hidden" name="review_token" value="">

                {{-- Contact --}}
                <section class="ck-section">
                    <h2 class="ck-section-title">Contact</h2>
                    <div class="ck-grid">
                        <div class="ck-field"><label for="email">Email</label>
                            <input id="email" name="email" type="email" class="ck-input" required value="{{ old('email', auth('web')->user()?->email) }}"></div>
                        <div class="ck-field"><label for="phone">Phone</label>
                            <input id="phone" name="phone" type="text" class="ck-input" required value="{{ old('phone', $defaultAddr?->phone) }}"></div>
                    </div>
                </section>

                {{-- Delivery address --}}
                <section class="ck-section">
                    <h2 class="ck-section-title">Delivery address</h2>
                    <div class="ck-grid">
                        <div class="ck-field"><label for="first_name">First name</label>
                            <input id="first_name" name="first_name" type="text" class="ck-input" required value="{{ old('first_name', $defaultAddr?->first_name) }}"></div>
                        <div class="ck-field"><label for="last_name">Last name</label>
                            <input id="last_name" name="last_name" type="text" class="ck-input" required value="{{ old('last_name', $defaultAddr?->last_name) }}"></div>
                        <div class="ck-field ck-field--full"><label for="address_line">Delivery address</label>
                            <textarea id="address_line" name="address_line" class="ck-input" required>{{ old('address_line', $defaultAddr?->address_line) }}</textarea></div>
                        <div class="ck-field"><label for="city">City</label>
                            <input id="city" name="city" type="text" class="ck-input" required value="{{ old('city', $defaultAddr?->city) }}"></div>
                        <div class="ck-field"><label for="postal_code">Postal code</label>
                            <input id="postal_code" name="postal_code" type="text" class="ck-input" value="{{ old('postal_code') }}"></div>
                        <div class="ck-field ck-field--full"><label for="country">Country</label>
                            <input id="country" name="country" class="ck-input" required value="{{ old('country', setting('store_country_code', config('site.country_code', 'PK'))) }}"></div>
                    </div>
                </section>

                {{-- Shipping method --}}
                <section class="ck-section">
                    <h2 class="ck-section-title">Shipping method</h2>
                    <div id="shipping-options" class="ck-options" role="radiogroup" aria-label="Shipping method">
                        @foreach($shippingOptions as $option)
                            <label class="ck-option">
                                <input type="radio" name="shipping_option" value="{{ $option->id }}" required @checked((string) old('shipping_option', $defaultShippingOption) === (string) $option->id)>
                                <span class="ck-option__label">{{ $option->name }}</span>
                                <span class="ck-option__price">{{ format_price($option->delivery_fee) }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                {{-- Payment --}}
                <section class="ck-section">
                    <h2 class="ck-section-title">Payment</h2>
                    <p class="ck-note">All transactions are secure and encrypted.</p>
                    @if(! $paymentChoices)
                        <p role="alert" class="ck-alert">No payment method supports {{ $totals['currency'] }}. Please contact the store.</p>
                    @endif
                    <div id="payment-options" class="ck-options" role="radiogroup" aria-label="Payment method">
                        @foreach($paymentChoices as $method => $choice)
                            <label class="ck-option">
                                <input type="radio" name="payment_method" value="{{ $method }}" required @checked((string) old('payment_method') === (string) $method)>
                                <span class="ck-option__label">{{ $choice['instructions']->label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p id="payment-instructions" class="ck-payment-instructions"></p>
                    <div id="receipt-field" class="ck-receipt" hidden>
                        <label for="payment_receipt">Payment receipt</label>
                        <input id="payment_receipt" name="payment_receipt" type="file" accept="image/*" class="ck-file" disabled>
                    </div>
                </section>

                {{-- Order notes --}}
                <section class="ck-section">
                    <h2 class="ck-section-title">Order notes (optional)</h2>
                    <div class="ck-field ck-field--full">
                        <textarea id="order_notes" name="order_notes" class="ck-input"></textarea>
                    </div>
                </section>
            </form>

            {{-- ============ Right: Order Summary ============ --}}
            <aside class="ck-summary" aria-label="Order summary">
                <h2 class="ck-summary-title">Order summary</h2>
                <div class="ck-items">
                    @foreach($items as $item)
                        <div class="ck-item">
                            <div class="ck-item__thumb">
                                <img src="{{ $item->product?->main_image_url ?? asset('images/placeholder.svg') }}" alt="{{ $item->product?->title }}" loading="lazy">
                                <span class="ck-item__qty">{{ $item->quantity }}</span>
                            </div>
                            <div class="ck-item__info">
                                <p class="ck-item__title" title="{{ $item->product?->title }}">{{ $item->product?->title }}</p>
                                <p class="ck-item__meta">{{ $item->weight }} × {{ $item->quantity }}</p>
                            </div>
                            <p class="ck-item__price">{{ format_price($item->price, $item->currency) }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Discount code --}}
                <div class="ck-coupon">
                    <input id="coupon-code" placeholder="Discount code" value="{{ $totals['coupon']?->code }}">
                    <button id="apply-coupon" type="button">Apply</button>
                </div>
                @if($totals['coupon'])
                    <button id="remove-coupon" type="button" class="ck-coupon-remove">Remove discount code</button>
                @endif

                {{-- Totals (server-rendered, replaced by the live quote on review) --}}
                <div id="review-summary" aria-live="polite" class="ck-rows">
                    <div class="ck-row"><span>Subtotal</span><span>{{ format_price($totals['subtotal']) }}</span></div>
                    @if((float) $totals['discount'] > 0)
                        <div class="ck-row"><span>Discount</span><span>−{{ format_price($totals['discount']) }}</span></div>
                    @endif
                    <div class="ck-row"><span>Delivery</span><span>Select shipping</span></div>
                    <div class="ck-row"><span>Tax</span><span>{{ format_price($totals['tax']) }}</span></div>
                    <div class="ck-row ck-row--total"><span>Total</span><span>{{ format_price($totals['grand_total']) }}</span></div>
                    <p class="ck-rows__note">Review the final quote before confirming your order.</p>
                </div>

                {{-- Review / Confirm actions --}}
                <div class="ck-actions">
                    <button id="review-order" type="button" class="ck-btn ck-btn--ghost">Review final total</button>
                    <button id="confirm-order" type="submit" form="checkout-form" class="ck-btn ck-btn--solid" disabled>Confirm reviewed order</button>
                </div>
                <p class="ck-secure">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Secure 256-bit encrypted checkout
                </p>
            </aside>
        </div>
    </div>
    <script>
    (() => {
        const form = document.getElementById('checkout-form');
        const message = document.getElementById('checkout-message');
        const confirm = document.getElementById('confirm-order');
        const reviewButton = document.getElementById('review-order');
        const summary = document.getElementById('review-summary');
        const reviewToken = form.elements.review_token;
        let payments = @js(collect($paymentChoices)->map(fn ($choice, $method) => ['id' => $method, 'label' => $choice['instructions']->label, 'instructions' => $choice['instructions']->instructions, 'requires_receipt' => $choice['instructions']->requiresReceipt])->values());
        let busy = false, changeRevision = 0, reviewedForm = null, originalPlacement = null;
        const snapshot = () => JSON.stringify([...new FormData(form).entries()].filter(([key]) => !['review_token', 'payment_receipt', '_token'].includes(key)));
        const invalidate = () => {
            changeRevision++;
            reviewToken.value = '';
            confirm.disabled = !originalPlacement;
            reviewedForm = null;
        };
        const showError = error => { message.textContent = error.message; message.focus(); };
        const post = async (url, data) => {
            const response = await fetch(url, {method: 'POST', body: data, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const result = await response.json();
            if (!response.ok) { const error = new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Please review checkout again.'); error.status = response.status; throw error; }
            return result;
        };
        const paymentInfo = () => {
            const selected = payments.find(payment => payment.id === form.elements.payment_method.value);
            document.getElementById('payment-instructions').textContent = selected?.instructions || '';
            const receipt = form.elements.payment_receipt;
            receipt.disabled = !selected?.requires_receipt;
            receipt.required = !!selected?.requires_receipt;
            document.getElementById('receipt-field').hidden = receipt.disabled;
        };
        // Rebuild the shipping / payment option cards (keeps the current choice when still available).
        const renderOptions = (wrapId, name, values) => {
            const wrap = document.getElementById(wrapId);
            const previous = form.elements[name]?.value;
            wrap.replaceChildren();
            values.forEach(value => {
                const label = document.createElement('label'); label.className = 'ck-option';
                const input = document.createElement('input');
                input.type = 'radio'; input.name = name; input.value = value.id; input.required = true;
                input.checked = String(value.id) === String(previous);
                const text = document.createElement('span'); text.className = 'ck-option__label'; text.textContent = value.label;
                label.append(input, text);
                if (value.price) { const price = document.createElement('span'); price.className = 'ck-option__price'; price.textContent = value.price; label.append(price); }
                wrap.append(label);
            });
        };
        form.addEventListener('input', event => { if (event.target.name !== 'payment_receipt') invalidate(); });
        form.addEventListener('change', event => { if (!['payment_receipt', 'country', 'postal_code'].includes(event.target.name)) invalidate(); paymentInfo(); });
        const reviewData = () => { const data = new FormData(form); data.delete('payment_receipt'); return data; };
        const options = async () => {
            invalidate();
            const version = changeRevision;
            try {
                const data = await post(@js(route('checkout.options')), reviewData());
                if (version !== changeRevision) return;
                renderOptions('shipping-options', 'shipping_option', data.shipping.map(option => ({id: option.id, label: option.label, price: `${data.currency} ${(option.fee_minor / 100).toFixed(2)}`})));
                payments = data.payments;
                renderOptions('payment-options', 'payment_method', payments.map(payment => ({id: payment.id, label: payment.label})));
                paymentInfo();
                message.textContent = !payments.length ? `No payment method supports ${data.currency}. Please contact the store.` : (!data.shipping.length ? 'No delivery option is available for this destination.' : '');
            } catch (error) { showError(error); }
        };
        form.elements.country.addEventListener('change', options);
        form.elements.postal_code.addEventListener('change', options);
        reviewButton.addEventListener('click', async () => {
            if (originalPlacement) { showError(new Error('Retry the original confirmation or reload to check your existing order first.')); return; }
            // Receipt is required for placement, not for a price review.
            const receipt = form.elements.payment_receipt;
            const required = receipt.required; receipt.required = false;
            const valid = form.reportValidity(); receipt.required = required;
            if (busy || !valid) return;
            invalidate();
            const version = changeRevision;
            busy = true; reviewButton.disabled = true; message.textContent = '';
            try {
                const result = await post(@js(route('checkout.quote')), reviewData());
                if (version !== changeRevision) return;
                const quote = result.quote;
                payments = payments.map(payment => payment.id === form.elements.payment_method.value ? {...payment, ...quote.payment.instructions} : payment);
                paymentInfo();
                summary.replaceChildren();
                [['Subtotal', quote.subtotal_minor], ['Discount', -quote.discount_minor], ['Delivery', quote.shipping.fee_minor], ['Tax', quote.tax_minor], ['Total', quote.total_minor]].forEach(([label, minor]) => {
                    const row = document.createElement('div'); row.className = label === 'Total' ? 'ck-row ck-row--total' : 'ck-row';
                    const left = document.createElement('span'); left.textContent = label;
                    const right = document.createElement('span'); right.textContent = `${quote.currency} ${(minor / 100).toFixed(2)}`;
                    row.append(left, right);
                    summary.append(row);
                });
                reviewToken.value = result.review_token;
                reviewedForm = snapshot();
                confirm.disabled = false;
                message.textContent = 'Review the total above, then confirm your order.';
            } catch (error) { showError(error); }
            finally { busy = false; reviewButton.disabled = false; }
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            if (!originalPlacement && (!reviewToken.value || snapshot() !== reviewedForm)) { invalidate(); showError(new Error('Review the final total before confirming.')); return; }
            busy = true; confirm.disabled = true;
            // Retain the exact confirmed request after an uncertain network outcome.
            originalPlacement ||= new FormData(form);
            try {
                const result = await post(form.action, originalPlacement);
                if (result.payment_action?.type === 'redirect') {
                    const payment = new URL(result.payment_action.url, location.origin);
                    if (payment.origin !== location.origin) throw new Error('Invalid payment continuation URL.');
                    location.assign(payment.href);
                    return;
                }
                form.hidden = true;
                message.replaceChildren(document.createTextNode('Order placed. '));
                const tracking = new URL(result.tracking_url, location.origin);
                if (tracking.origin === location.origin) { const link = document.createElement('a'); link.href = tracking.href; link.textContent = 'View your order'; message.append(link); }
            } catch (error) {
                showError(error);
                // A known server rejection allows a fresh review; a network error keeps the exact request for replay.
                if (error.status && error.status < 500) { originalPlacement = null; invalidate(); confirm.textContent = 'Confirm reviewed order'; }
                else { confirm.textContent = 'Retry original confirmation'; message.textContent += ' The confirmation outcome is unknown. Retry the original request or reload to check your existing order.'; }
            } finally { busy = false; confirm.disabled = !originalPlacement && !reviewToken.value; }
        });
        document.getElementById('apply-coupon').addEventListener('click', async () => {
            invalidate();
            const data = new FormData(form); data.set('code', document.getElementById('coupon-code').value);
            try { const result = await post(@js(route('cart.coupon')), data); if (!result.success) throw new Error(result.message); if(result.idempotency_key) form.elements.idempotency_key.value = result.idempotency_key; message.textContent = 'Coupon applied. Request a fresh review.'; }
            catch(error) { showError(error); }
        });
        document.getElementById('remove-coupon')?.addEventListener('click', async () => {
            invalidate(); const data = new FormData(form); data.set('_method', 'DELETE');
            try { const result = await post(@js(route('cart.coupon.remove')), data); if(result.idempotency_key) form.elements.idempotency_key.value = result.idempotency_key; document.getElementById('coupon-code').value = ''; message.textContent = 'Coupon removed. Request a fresh review.'; }
            catch(error) { showError(error); }
        });
        paymentInfo();
    })();
    </script>
</x-app-layout>
