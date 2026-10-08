<x-app-layout :title="setting('site_name', config('site.name', 'Store'))" page-key="home">
    <section class="max-w-7xl mx-auto px-4 py-12" data-child-theme-example>
        <h1 class="text-3xl font-bold">{{ theme_setting('welcome_text') }}</h1>
        <p class="mt-3">A fresh look for your favorites.</p>
        <a class="btn-primary mt-6" href="{{ route('shop') }}">Browse menu</a>
        @foreach($homeCategories as $category)
            <h2 class="text-2xl font-bold mt-10">{{ $category->name }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-5">@foreach($category->products as $product)<x-product-card :product="$product" />@endforeach</div>
        @endforeach
    </section>
</x-app-layout>
