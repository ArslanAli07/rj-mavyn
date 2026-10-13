@props([
    'action',
])

<div data-bulk-root {{ $attributes }}>
    <form method="POST" action="{{ $action }}" data-bulk-form>
        @csrf
        <div data-bulk-toolbar class="d-none mb-3 d-flex flex-column flex-sm-row align-items-sm-center gap-3 p-3 bg-body-tertiary border border-light-subtle rounded-2">
            <p data-bulk-count class="fw-semibold text-body flex-shrink-0">0 selected</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                {{ $actions }}
            </div>
        </div>
    </form>

    {{ $slot }}
</div>
