@props(['items' => []])

<nav class="flex px-4 py-3 text-espresso-500 bg-white border border-cream-100 rounded-2xl max-w-7xl mx-auto mb-6 px-6 sm:px-8" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2 text-xs">
        <li class="inline-flex items-center">
            <a href="{{ route('home') }}" class="inline-flex items-center text-espresso-500 hover:text-primary-600 transition-colors">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Home
            </a>
        </li>
        @foreach($items as $label => $url)
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-cream-400 mx-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    @if($loop->last)
                        <span class="ml-1 text-espresso-800 font-medium md:ml-2 truncate max-w-[200px]" aria-current="page">{{ $label }}</span>
                    @else
                        <a href="{{ $url }}" class="ml-1 text-espresso-500 hover:text-primary-600 transition-colors md:ml-2">{{ $label }}</a>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</nav>
