<x-app-layout title="About us" page-key="about">
    <article class="max-w-3xl mx-auto px-4 py-12"><h1 class="text-3xl font-bold">About us</h1><p class="mt-6 whitespace-pre-line">{{ setting('about_text', 'Welcome to our store. Contact us to learn more about our products.') }}</p><a class="btn-secondary mt-6" href="{{ route('contact') }}">Contact us</a></article>
</x-app-layout>
