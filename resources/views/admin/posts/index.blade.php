<x-admin-layout title="Manage Blog Posts">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
        <div>
            <h1 class="text-body admin-page-title">Blog posts</h1>
            <p class="small text-body-secondary mt-1">Guides and articles shown on the storefront blog.</p>
        </div>
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.posts.create'))
<a href="{{ route('admin.posts.create') }}" class="btn btn-primary py-2 px-4 small fw-semibold">
            + Add post
        </a>
@endif
    </div>

    {{-- Posts List --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        @if($posts->isEmpty())
            <p class="small text-body-secondary text-center py-5">No blog posts yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Published</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($posts as $post)
                            @php $live = $post->is_published && $post->published_at && $post->published_at->isPast(); @endphp
                            <tr>
                                <td class="fw-semibold text-body admin-max-width-sm text-truncate">{{ $post->title }}</td>
                                <td class="text-body-secondary admin-max-width-xs text-truncate">{{ $post->slug }}</td>
                                <td class="text-body-secondary">{{ $post->published_at?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    <span class="badge small px-2 py-1 rounded-pill fw-semibold {{ $live ? 'bg-success-subtle text-success' : 'bg-body-secondary text-body-secondary' }}">
                                        {{ $live ? 'Live' : ($post->is_published ? 'Scheduled' : 'Draft') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                        @if($live)
                                            <a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">View</a>
                                        @endif
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.posts.edit'))
<a href="{{ route('admin.posts.edit', $post) }}" class="px-2 py-2 bg-body-tertiary rounded-2 fw-semibold">Edit</a>
@endif
                                        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.posts.destroy'))
                                        <form action="{{ route('admin.posts.destroy', $post) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this post?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-2 bg-danger-subtle text-danger rounded-2 fw-semibold">Delete</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{ $posts->links('pagination::bootstrap-5') }}
        @endif
    </div>

</x-admin-layout>
