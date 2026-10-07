<x-admin-layout title="Media Library">
    <h1 class="text-2xl font-bold mb-2">Media Library</h1>
    <p class="text-sm text-espresso-500 mb-6">Upload once, reuse public files throughout your store, or copy their URL.</p>

    @if($errors->any())
        <div role="alert" class="mb-4 text-red-700">{{ $errors->first() }}</div>
    @endif
    <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data"
          class="bg-white border rounded-2xl p-5 mb-6 grid gap-4 sm:grid-cols-2">
        @csrf
        <label class="text-sm">File (up to 20 MB)
            <input type="file" name="file" required accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,audio/mpeg,audio/wav,video/mp4,video/webm" class="block w-full mt-1">
        </label>
        <label class="text-sm">Title
            <input name="title" value="{{ old('title') }}" maxlength="255" required class="block w-full rounded-lg border mt-1">
        </label>
        <label class="text-sm">Alternative text
            <input name="alt_text" value="{{ old('alt_text') }}" maxlength="255" class="block w-full rounded-lg border mt-1">
        </label>
        <label class="text-sm">Access
            <select name="visibility" class="block w-full rounded-lg border mt-1">
                <option value="public">Public — reusable URL</option>
                <option value="private" @selected(old('visibility') === 'private')>Private — authorized staff only</option>
            </select>
        </label>
        <button type="submit" class="btn-gold px-5 py-2 justify-self-start">Upload media</button>
    </form>

    <form method="GET" class="flex flex-wrap gap-3 mb-6">
        <input name="q" value="{{ request('q') }}" placeholder="Search media titles" aria-label="Search media titles" class="rounded-lg border">
        <select name="visibility" aria-label="Filter access" class="rounded-lg border">
            <option value="">All access types</option>
            <option value="public" @selected(request('visibility') === 'public')>Public</option>
            <option value="private" @selected(request('visibility') === 'private')>Private</option>
        </select>
        <button class="btn-gold px-4 py-2">Search</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        @forelse($assets as $asset)
            @php($file = $asset->getFirstMedia('original'))
            <a href="{{ route('admin.media.show', $asset) }}" class="bg-white rounded-xl border p-4">
                @if($asset->status === 'active' && $file && str_starts_with($file->mime_type, 'image/'))
                    <img src="{{ $asset->visibility === 'private' ? route('admin.media.file', $asset) : $file->getAvailableUrl(['thumbnail']) }}"
                         alt="{{ $asset->alt_text ?? '' }}" class="h-36 w-full object-contain mb-3" loading="lazy">
                @else
                    <div class="h-36 flex items-center justify-center text-sm mb-3">{{ $file?->mime_type ?? 'File unavailable' }}</div>
                @endif
                <p class="text-xs mb-2">{{ ucfirst($asset->status) }} · {{ str_replace('_', ' ', $asset->processing_status) }}</p>
                <p class="font-semibold break-words">{{ $asset->title }}</p>
                <p class="text-xs mt-2">{{ ucfirst($asset->visibility) }} · {{ $asset->usages_count }} usage(s) · {{ $asset->retention_holds_count }} historical hold(s)</p>
            </a>
        @empty
            <p class="text-sm">No media found. Upload your first file above.</p>
        @endforelse
    </div>
    {{ $assets->links() }}
</x-admin-layout>
