@php($attributeFields = app(\App\Services\ProductAttributesService::class)->fields('product'))
@if($attributeFields)
<section class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4">
    <h3 class="font-heading font-bold text-sm">Product attributes</h3>
    @foreach($attributeFields as $entry)
        @php($field = $entry['field'])
        <div>
            <label for="{{ $field->key }}" class="label-luxury">{{ $field->label }}</label>
            @if($field->input === 'textarea')
                <textarea id="{{ $field->key }}" name="{{ $field->key }}" rows="2" maxlength="{{ $field->maxLength }}" class="input-luxury text-sm">{{ old($field->key, isset($product) ? $product->getAttribute($field->key) : null) }}</textarea>
            @else
                <input id="{{ $field->key }}" name="{{ $field->key }}" maxlength="{{ $field->maxLength }}" value="{{ old($field->key, isset($product) ? $product->getAttribute($field->key) : null) }}" class="input-luxury text-sm">
            @endif
            @error($field->key)<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
        </div>
    @endforeach
</section>
@endif
