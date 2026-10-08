<x-app-layout title="Shipping and delivery" page-key="shipping">
    <article class="max-w-3xl mx-auto px-4 py-12"><h1 class="text-3xl font-bold">Shipping and delivery</h1><p class="mt-6 whitespace-pre-line">{{ setting('shipping_policy', 'Delivery availability and charges are shown at checkout. Contact us with delivery questions.') }}</p><a class="btn-secondary mt-6" href="{{ route('contact') }}">Contact us</a></article>
</x-app-layout>
