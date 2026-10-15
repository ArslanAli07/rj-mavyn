<x-app-layout title="Secure Checkout">
    <section class="max-w-5xl mx-auto px-4 py-12">
        <h1 class="font-heading text-3xl font-bold mb-6">Checkout</h1>
        <p class="mb-6">Selling currency: <strong>{{ $totals['currency'] }}</strong>. Review the final quote before confirming your order.</p>
        <div id="checkout-message" role="alert" class="mb-6 text-red-700" tabindex="-1"></div>
        @if($totals['has_unavailable'])
            <p role="alert">Some cart items are unavailable in {{ $totals['currency'] }}. <a href="{{ route('cart.index') }}">Review your cart</a>.</p>
        @endif
        <form id="checkout-form" action="{{ route('checkout.place-order') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <x-honeypot />
            <input type="hidden" name="idempotency_key" value="{{ $checkoutKey }}">
            <input type="hidden" name="review_token" value="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach(['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'City'] as $name => $label)
                    <div><label for="{{ $name }}" class="label-luxury">{{ $label }}</label>
                    <input id="{{ $name }}" name="{{ $name }}" type="{{ $name === 'email' ? 'email' : 'text' }}" class="input-luxury" required
                        value="{{ old($name, $name === 'email' ? auth('web')->user()?->email : ($defaultAddr?->$name ?? '')) }}"></div>
                @endforeach
                <div><label for="country" class="label-luxury">Country</label>
                    <input id="country" name="country" class="input-luxury" required value="{{ old('country', setting('store_country_code', config('site.country_code', 'PK'))) }}"></div>
                <div><label for="postal_code" class="label-luxury">Postal code</label><input id="postal_code" name="postal_code" class="input-luxury" value="{{ old('postal_code') }}"></div>
            </div>
            <div><label for="address_line" class="label-luxury">Delivery address</label>
                <textarea id="address_line" name="address_line" class="input-luxury" required>{{ old('address_line', $defaultAddr?->address_line) }}</textarea></div>
            <div><label for="shipping_option" class="label-luxury">Delivery option</label>
                <select id="shipping_option" name="shipping_option" class="input-luxury" required>
                    <option value="">Choose delivery</option>
                    @foreach($shippingOptions as $option)<option value="{{ $option->id }}">{{ $option->name }} — {{ format_price($option->delivery_fee) }}</option>@endforeach
                </select></div>
            <div><label for="payment_method" class="label-luxury">Payment method</label>
                <select id="payment_method" name="payment_method" class="input-luxury" required>
                    <option value="">Choose payment</option>
                    @foreach($paymentChoices as $method => $choice)<option value="{{ $method }}">{{ $choice['instructions']->label }}</option>@endforeach
                </select>
                <p id="payment-instructions" class="text-sm mt-2" style="white-space:pre-line"></p>
                @if(! $paymentChoices)<p role="alert">No payment method supports {{ $totals['currency'] }}. Please contact the store.</p>@endif
            </div>
            <div id="receipt-field" hidden><label for="payment_receipt" class="label-luxury">Payment receipt</label>
                <input id="payment_receipt" name="payment_receipt" type="file" accept="image/*" class="input-luxury" disabled></div>
            <div><label for="order_notes" class="label-luxury">Order notes (optional)</label><textarea id="order_notes" name="order_notes" class="input-luxury"></textarea></div>
            <div class="flex gap-2">
                <label for="coupon-code" class="label-luxury">Coupon</label><input id="coupon-code" class="input-luxury" value="{{ $totals['coupon']?->code }}">
                <button id="apply-coupon" type="button" class="btn-secondary">Apply</button><button id="remove-coupon" type="button" class="btn-secondary">Remove</button>
            </div>
            <div class="bg-white border rounded-2xl p-6 space-y-3">
                <h2 class="font-heading text-xl font-bold">Your items</h2>
                @foreach($items as $item)
                    <p>{{ $item->product?->title }} · {{ $item->weight }} × {{ $item->quantity }} — {{ format_price($item->price, $item->currency) }}</p>
                @endforeach
                <div id="review-summary" aria-live="polite"><p>Request a quote to review delivery, discounts, tax, and the final total.</p></div>
            </div>
            <button id="review-order" type="button" class="btn-secondary">Review final total</button>
            <button id="confirm-order" type="submit" class="btn-gold" disabled>Confirm reviewed order</button>
        </form>
    </section>
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
        form.addEventListener('input', event => { if (event.target.name !== 'payment_receipt') invalidate(); });
        form.addEventListener('change', event => { if (!['payment_receipt', 'country', 'postal_code'].includes(event.target.name)) invalidate(); paymentInfo(); });
        const reviewData = () => { const data = new FormData(form); data.delete('payment_receipt'); return data; };
        const options = async () => {
            invalidate();
            const version = changeRevision;
            try {
                const data = await post(@js(route('checkout.options')), reviewData());
                if (version !== changeRevision) return;
                const shipping = form.elements.shipping_option;
                shipping.replaceChildren(new Option('Choose delivery', ''));
                data.shipping.forEach(option => shipping.add(new Option(`${option.label} — ${data.currency} ${(option.fee_minor / 100).toFixed(2)}`, option.id)));
                payments = data.payments;
                const method = form.elements.payment_method;
                method.replaceChildren(new Option('Choose payment', ''));
                payments.forEach(payment => method.add(new Option(payment.label, payment.id)));
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
                    const row = document.createElement('p'); row.textContent = `${label}: ${quote.currency} ${(minor / 100).toFixed(2)}`; summary.append(row);
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
        document.getElementById('remove-coupon').addEventListener('click', async () => {
            invalidate(); const data = new FormData(form); data.set('_method', 'DELETE');
            try { const result = await post(@js(route('cart.coupon.remove')), data); if(result.idempotency_key) form.elements.idempotency_key.value = result.idempotency_key; document.getElementById('coupon-code').value = ''; message.textContent = 'Coupon removed. Request a fresh review.'; }
            catch(error) { showError(error); }
        });
        paymentInfo();
    })();
    </script>
</x-app-layout>
