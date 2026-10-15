@php
    $pricingContext = app(\Mavyn\Contracts\Pricing\PriceBook::class)->context();
    $legacyCurrency = ($product ?? $coupon ?? $deliveryZone ?? null)?->getRawOriginal('price_currency') ?? $pricingContext->currency;
@endphp
<input type="hidden" name="pricing_revision" value="{{ $pricingContext->revision }}">
<input type="hidden" name="price_currency" value="{{ $legacyCurrency }}">
<p class="small text-muted">Price currency: {{ $legacyCurrency }}. Other currencies are edited through currency management.</p>
