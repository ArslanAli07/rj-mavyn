<x-admin-layout title="Edit Blog Post">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Edit blog post</h1>
            <p class="small text-body-secondary mt-1">
                @if($post->is_published && $post->published_at && $post->published_at->isPast())
                    Live at <a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener">{{ route('blog.show', $post->slug) }}</a>
                @else
                    Not yet visible on the storefront.
                @endif
            </p>
        </div>
        <a href="{{ route('admin.posts.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to posts
        </a>
    </div>

    <form action="{{ route('admin.posts.update', $post) }}" method="POST" class="bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-max-width-2xl admin-stack-4">
        @csrf
        @method('PUT')

        @include('admin.posts._form', ['post' => $post])

        <div class="d-flex gap-3 pt-3 border-top border-light-subtle justify-content-end">
            <a href="{{ route('admin.posts.index') }}" class="btn btn-outline-secondary py-2 px-4 small">Cancel</a>
            <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">Update post</button>
        </div>
    </form>

</x-admin-layout>
