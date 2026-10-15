@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between border-t border-cream-200 px-4 py-3 sm:px-6 mt-8">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center rounded-full border border-cream-200 bg-white px-4 py-2 text-sm font-medium text-espresso-400 cursor-not-allowed">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center rounded-full border border-cream-200 bg-white px-4 py-2 text-sm font-medium text-espresso-750 hover:bg-cream-50">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-full border border-cream-200 bg-white px-4 py-2 text-sm font-medium text-espresso-750 hover:bg-cream-50">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative ml-3 inline-flex items-center rounded-full border border-cream-200 bg-white px-4 py-2 text-sm font-medium text-espresso-400 cursor-not-allowed">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-espresso-500">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-medium text-espresso-800">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-medium text-espresso-800">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-medium text-espresso-800">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rounded-full shadow-sm gap-1 bg-white p-1 border border-cream-100">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center justify-center rounded-full w-9 h-9 text-espresso-300 cursor-not-allowed" aria-hidden="true">
                                <svg class="h-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center justify-center rounded-full w-9 h-9 text-espresso-600 hover:bg-cream-100 transition-colors" aria-label="{{ __('pagination.previous') }}">
                            <svg class="h-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span class="relative inline-flex items-center justify-center rounded-full w-9 h-9 text-espresso-400 select-none cursor-default" aria-disabled="true">
                                <span>{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center justify-center rounded-full w-9 h-9 bg-primary-500 text-white text-sm font-semibold shadow-md">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center justify-center rounded-full w-9 h-9 text-espresso-600 hover:bg-cream-100 text-sm font-medium transition-colors" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center justify-center rounded-full w-9 h-9 text-espresso-600 hover:bg-cream-100 transition-colors" aria-label="{{ __('pagination.next') }}">
                            <svg class="h-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center justify-center rounded-full w-9 h-9 text-espresso-300 cursor-not-allowed" aria-hidden="true">
                                <svg class="h-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
