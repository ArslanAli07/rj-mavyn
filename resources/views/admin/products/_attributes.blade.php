@php($attributeFields = app(\App\Services\ProductAttributesService::class)->fields('product'))
@if($attributeFields)
<section class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
    <h3 class="admin-section-title">Product attributes</h3>
    @foreach($attributeFields as $entry)
        @php($field = $entry['field'])
        <div>
            <label for="{{ $field->key }}" class="form-label">{{ $field->label }}</label>
            @if($field->input === 'textarea')
                <textarea id="{{ $field->key }}" name="{{ $field->key }}" rows="2" maxlength="{{ $field->maxLength }}" class="form-control small">{{ old($field->key, isset($product) ? $product->getAttribute($field->key) : null) }}</textarea>
            @else
                <input id="{{ $field->key }}" name="{{ $field->key }}" maxlength="{{ $field->maxLength }}" value="{{ old($field->key, isset($product) ? $product->getAttribute($field->key) : null) }}" class="form-control small">
            @endif
            @error($field->key)<p class="text-danger small">{{ $message }}</p>@enderror
        </div>
    @endforeach
</section>
@endif
