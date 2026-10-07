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
    class="w-4 h-4 rounded border-cream-300 text-primary-500 focus:ring-primary-300 cursor-pointer"
    {{ $attributes }}
>
