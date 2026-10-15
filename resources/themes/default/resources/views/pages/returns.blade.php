<x-app-layout title="Returns and refunds" page-key="returns">
    <article class="max-w-3xl mx-auto px-4 py-12"><h1 class="text-3xl font-bold">Returns and refunds</h1><p class="mt-6 whitespace-pre-line">{{ setting('returns_policy', 'Contact us with your order number for help with returns or refunds.') }}</p><a class="btn-secondary mt-6" href="{{ route('contact') }}">Contact us</a></article>
</x-app-layout>
