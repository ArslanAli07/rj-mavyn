<x-admin-layout title="Create Blog Post">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Add new blog post</h1>
            <p class="small text-body-secondary mt-1">Write a guide or article for the storefront blog.</p>
        </div>
        <a href="{{ route('admin.posts.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to posts
        </a>
    </div>

    <form action="{{ route('admin.posts.store') }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf

        @include('admin.posts._form', ['post' => null])

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.posts.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Save post</button>
        </div>
    </form>

</x-admin-layout>
