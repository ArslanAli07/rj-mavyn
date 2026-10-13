@php
    $variantLabel = $item->variant_label ?? null;
    $options = $item->options_snapshot ?? [];
    $specialRequest = $item->special_request ?? null;
    $attributes = $item->attributes_snapshot ?? [];
@endphp

@if($variantLabel || !empty($options) || $specialRequest || $attributes)
    <div class="{{ $class ?? 'mt-1 space-y-0.5' }}">
        @if($variantLabel)
            <p class="{{ $labelClass ?? 'text-xs text-espresso-600' }}">
                <span class="{{ $titleClass ?? 'font-semibold text-espresso-800' }}">{{ $variantTitle ?? 'Variant' }}:</span>
                {{ $variantLabel }}
            </p>
        @endif

        @foreach($options as $option)
            <p class="{{ $optionClass ?? 'text-xs text-espresso-600' }}">
                <span class="{{ $titleClass ?? 'font-semibold text-espresso-800' }}">{{ $option['group_name'] ?? 'Option' }}:</span>
                {{ $option['value_name'] ?? '' }}
                @if(!empty($option['price_adjustment']) && (float) $option['price_adjustment'] != 0)
                    <span class="{{ $adjustmentClass ?? 'text-espresso-500' }}">
                        ({{ (float) $option['price_adjustment'] > 0 ? '+' : '' }}{{ format_price($option['price_adjustment']) }})
                    </span>
                @endif
            </p>
        @endforeach

        @foreach($attributes as $providerAttributes)
            @foreach($providerAttributes as $attribute)
                <p class="{{ $optionClass ?? 'text-xs text-espresso-600' }}">
                    <span class="{{ $titleClass ?? 'font-semibold' }}">{{ $attribute['label'] }}:</span> {{ $attribute['value'] }}
                </p>
            @endforeach
        @endforeach

        @if($specialRequest)
            <p class="{{ $optionClass ?? 'text-xs text-espresso-600' }}">
                <span class="{{ $titleClass ?? 'font-semibold text-espresso-800' }}">Note:</span>
                {{ $specialRequest }}
            </p>
        @endif
    </div>
@endif
