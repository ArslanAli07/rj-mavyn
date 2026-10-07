@props([])

@php
    $nameField = config('honeypot.name_field', 'website');
    $timeField = config('honeypot.valid_from_field', '_form_started_at');
    $startedAt = \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp);
@endphp

{{-- Honeypot: hidden from humans, filled/stripped by many bots --}}
<div
    class="hp-field"
    aria-hidden="true"
    style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;"
>
    <label for="{{ $nameField }}">Website</label>
    <input
        type="text"
        id="{{ $nameField }}"
        name="{{ $nameField }}"
        value=""
        tabindex="-1"
        autocomplete="off"
    >
    <input
        type="text"
        name="{{ $timeField }}"
        value="{{ $startedAt }}"
        tabindex="-1"
        autocomplete="off"
    >
</div>
