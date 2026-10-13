@props([
    'all' => false,
    'value' => null,
    'label' => 'Select row',
])

<input
    type="checkbox"
    @if($all)
        data-bulk-select-all
        aria-label="Select all rows"
    @else
        data-bulk-row
        value="{{ $value }}"
        aria-label="{{ $label }}"
    @endif
    class="form-check-input admin-clickable"
    {{ $attributes }}
>
