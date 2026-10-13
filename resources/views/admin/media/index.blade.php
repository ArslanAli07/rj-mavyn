<x-admin-layout title="Media library">
    <h1 class="mb-2 admin-page-title">Media library</h1>
    <p class="small text-body-secondary mb-4">Upload once, reuse public files throughout your store, or copy their URL.</p>

    @if($errors->any())
        <div role="alert" class="mb-3 text-danger">{{ $errors->first() }}</div>
    @endif
    <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data"
          class="bg-white border rounded-2 p-4 mb-4 d-grid gap-4 admin-cols-sm-2">
        @csrf
        <label class="small">File (up to 20 MB)
            <input type="file" name="file" required accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,audio/mpeg,audio/wav,video/mp4,video/webm" class="form-control d-block w-100 mt-1">
        </label>
        <label class="small">Title
            <input name="title" value="{{ old('title') }}" maxlength="255" required class="form-control d-block w-100 rounded-2 border mt-1">
        </label>
        <label class="small">Alternative text
            <input name="alt_text" value="{{ old('alt_text') }}" maxlength="255" class="form-control d-block w-100 rounded-2 border mt-1">
        </label>
        <label class="small">Access
            <select name="visibility" class="form-select d-block w-100 rounded-2 border mt-1">
                <option value="public">Public — reusable URL</option>
                <option value="private" @selected(old('visibility') === 'private')>Private — authorized staff only</option>
            </select>
        </label>
        <button type="submit" class="btn btn-primary px-4 py-2 admin-justify-self-start">Upload media</button>
    </form>

    <form method="GET" class="d-flex flex-wrap gap-3 mb-4">
        <input name="q" value="{{ request('q') }}" placeholder="Search media titles" aria-label="Search media titles" class="form-control rounded-2 border">
        <select name="visibility" aria-label="Filter access" class="form-select rounded-2 border">
            <option value="">All access types</option>
            <option value="public" @selected(request('visibility') === 'public')>Public</option>
            <option value="private" @selected(request('visibility') === 'private')>Private</option>
        </select>
        <button class="btn btn-primary px-3 py-2">Search</button>
    </form>

    <div class="d-grid gap-4 admin-cols-sm-2 admin-cols-lg-4 mb-4">
        @forelse($assets as $asset)
            @php($file = $asset->getFirstMedia('original'))
            <a href="{{ route('admin.media.show', $asset) }}" class="bg-white rounded-2 border p-3">
                @if($asset->status === 'active' && $file && str_starts_with($file->mime_type, 'image/'))
                    <img src="{{ $asset->visibility === 'private' ? route('admin.media.file', $asset) : $file->getAvailableUrl(['thumbnail']) }}"
                         alt="{{ $asset->alt_text ?? '' }}" class="admin-product-preview w-100 object-fit-contain mb-3" loading="lazy">
                @else
                    <div class="admin-height-36 d-flex align-items-center justify-content-center mb-3">{{ $file?->mime_type ?? 'File unavailable' }}</div>
                @endif
                <p class="small mb-2">{{ ucfirst($asset->status) }} · {{ str_replace('_', ' ', $asset->processing_status) }}</p>
                <p class="fw-semibold text-break">{{ $asset->title }}</p>
                <p class="small mt-2">{{ ucfirst($asset->visibility) }} · {{ $asset->usages_count }} usage(s) · {{ $asset->retention_holds_count }} historical hold(s)</p>
            </a>
        @empty
            <p class="small">No media found. Upload your first file above.</p>
        @endforelse
    </div>
    {{ $assets->links('pagination::bootstrap-5') }}
</x-admin-layout>
