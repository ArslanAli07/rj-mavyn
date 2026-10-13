@props([
    'title' => 'Filters',
    'active' => false,
])

<div
    {{ $attributes->class('bg-white border border-light-subtle rounded-4 mb-4 shadow-sm') }}
    x-data="{ filtersOpen: {{ $active ? 'true' : 'false' }} }"
>
    <button
        type="button"
        class="btn btn-sm btn-outline-secondary d-lg-none w-100 d-flex align-items-center gap-3 px-3 py-3 text-start"
        @click="filtersOpen = !filtersOpen"
        :aria-expanded="filtersOpen.toString()"
    >
        <svg class="admin-width-4 admin-height-4 text-body-secondary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        <span class="fw-semibold text-body">{{ $title }}</span>
        @if($active)
            <span class="small fw-semibold admin-letter-spacing bg-primary-subtle text-primary px-2 py-1 rounded-pill">On</span>
        @endif
        <svg
            class="admin-width-5 admin-height-5 text-body-secondary ms-auto flex-shrink-0"
            :class="filtersOpen && 'admin-chevron-open'"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-cloak class="admin-filters-panel px-3 pb-3 pt-3 p-lg-3 border-top border-light-subtle admin-border-lg-0"
        :class="{ 'd-block': filtersOpen, 'd-none': !filtersOpen }"
    >
        {{ $slot }}
    </div>
</div>
