@php
    $attributeFields = app(\App\Services\ProductAttributesService::class)->fields('product');
@endphp
@if($attributeFields)
<section class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
    <h3 class="admin-section-title">Product attributes</h3>
    @foreach($attributeFields as $entry)
        @php
            $field = $entry['field'];
            $inputId = $entry['legacy'] ? $field->key : 'attribute-'.str_replace('.', '-', $entry['owner']).'-'.$field->key;
            $inputName = $entry['legacy'] ? $field->key : 'plugin_attributes['.$entry['owner'].']['.$field->key.']';
            $oldKey = $entry['legacy'] ? $field->key : 'plugin_attributes.'.str_replace('.', '\\.', $entry['owner']).'.'.$field->key;
            $attributeValue = old($oldKey, isset($product) ? app(\App\Services\ProductAttributesService::class)->value($product, $entry) : null);
        @endphp
        <div>
            <label for="{{ $inputId }}" class="form-label">{{ $field->label }}</label>
            @if($field->input === 'textarea')
                <textarea id="{{ $inputId }}" name="{{ $inputName }}" rows="2" maxlength="{{ $field->maxLength }}" class="form-control small">{{ $attributeValue }}</textarea>
            @else
                <input id="{{ $inputId }}" name="{{ $inputName }}" maxlength="{{ $field->maxLength }}" value="{{ $attributeValue }}" class="form-control small">
            @endif
            @error($oldKey)<p class="text-danger small">{{ $message }}</p>@enderror
        </div>
    @endforeach
</section>
@endif
