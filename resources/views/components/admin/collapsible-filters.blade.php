@props([
    'title' => 'Filters',
    'active' => false,
])

<div
    {{ $attributes->class('bg-white border border-cream-150 rounded-2xl mb-6 shadow-sm') }}
    x-data="{ filtersOpen: {{ $active ? 'true' : 'false' }} }"
>
    <button
        type="button"
        class="lg:hidden w-full flex items-center gap-3 px-4 py-3 text-left"
        @click="filtersOpen = !filtersOpen"
        :aria-expanded="filtersOpen.toString()"
    >
        <svg class="w-4 h-4 text-espresso-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        <span class="text-sm font-semibold text-espresso-800">{{ $title }}</span>
        @if($active)
            <span class="text-[10px] font-bold uppercase tracking-wide bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full">On</span>
        @endif
        <svg
            class="w-5 h-5 text-espresso-400 ml-auto shrink-0 transition-transform duration-200"
            :class="filtersOpen && 'rotate-180'"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        class="admin-filters-panel hidden px-4 pb-4 pt-3 lg:p-4 border-t border-cream-100 lg:border-0"
        :class="{ 'block': filtersOpen, 'hidden': !filtersOpen }"
    >
        {{ $slot }}
    </div>
</div>
