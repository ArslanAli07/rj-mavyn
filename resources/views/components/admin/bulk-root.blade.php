@props([
    'action',
])

<div data-bulk-root {{ $attributes }}>
    <form method="POST" action="{{ $action }}" data-bulk-form>
        @csrf
        <div data-bulk-toolbar class="hidden mb-4 flex flex-col sm:flex-row sm:items-center gap-3 p-3 bg-cream-50 border border-cream-200 rounded-xl">
            <p data-bulk-count class="text-xs font-semibold text-espresso-800 shrink-0">0 selected</p>
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        </div>
    </form>

    {{ $slot }}
</div>
