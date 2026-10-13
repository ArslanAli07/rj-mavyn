<x-admin-layout title="Media details">
    <a href="{{ route('admin.media.index') }}" class="small text-decoration-underline">Back to media library</a>
    <h1 class="my-4 admin-page-title">{{ $asset->title }}</h1>
    @if($errors->any())
        <p role="alert" class="mb-3 text-danger">{{ $errors->first() }}</p>
    @endif
    <p role="status" class="my-3 small">Asset: {{ ucfirst($asset->status) }} · Processing: {{ str_replace('_', ' ', ucfirst($asset->processing_status)) }}</p>
    @if($asset->processing_error)
        <p role="alert" class="text-danger small mb-3">{{ $asset->processing_error }}</p>
    @endif
    @if($asset->status === 'active' && $asset->processing_status !== 'not_applicable')
        <form action="{{ route('admin.media.retry', $asset) }}" method="POST" class="mb-4">
            @csrf
            <button class="btn btn-primary px-3 py-2">Retry image processing</button>
            <p class="small mt-2">Queued images use the original until processing finishes. A stalled queue can be retried after 10 minutes.</p>
        </form>
    @endif
    @if($asset->status === 'deleting')
        @php($cleanup = \App\Models\MediaCleanupTask::where('media_asset_id', $asset->id)->first())
        <p class="small mb-2">Cleanup: {{ $cleanup?->status ?? 'queued' }} · Attempts: {{ $cleanup?->attempts ?? 0 }}</p>
        @if($cleanup?->last_error)<p class="text-danger small mb-3">{{ $cleanup->last_error }}</p>@endif
        <form action="{{ route('admin.media.retry-cleanup', $asset) }}" method="POST" class="mb-4">
            @csrf <button class="btn btn-primary px-3 py-2">Retry cleanup</button>
        </form>
    @endif
    @php($file = $asset->getFirstMedia('original'))
    @if($file && $asset->status === 'active')
        @if(str_starts_with($file->mime_type, 'image/'))
            <img src="{{ $asset->visibility === 'private' ? route('admin.media.file', $asset) : $file->getAvailableUrl(['thumbnail']) }}"
                 alt="{{ $asset->alt_text ?? '' }}" class="admin-max-height-80 mw-100 object-fit-contain mb-4">
        @endif
        <p class="small mb-4">{{ $file->mime_type }} · {{ $file->human_readable_size }} · {{ ucfirst($asset->visibility) }}</p>
        @if($asset->visibility === 'public')
            <div x-data="{ copied: false, failed: false }" class="mb-4">
                <label class="d-block small mb-2" for="media-url">Public URL</label>
                <input id="media-url" x-ref="url" readonly value="{{ $file->getUrl() }}" class="form-control w-100 rounded-2 border mb-2" @click="$el.select()">
                <button type="button" class="btn btn-primary px-3 py-2"
                        @click="navigator.clipboard ? navigator.clipboard.writeText($refs.url.value).then(() => { copied = true; failed = false }).catch(() => { failed = true; $refs.url.select() }) : (failed = true, $refs.url.select())">Copy URL</button>
                <span x-show.important="copied" x-cloak role="status" class="small">URL copied.</span>
                <span x-show.important="failed" x-cloak role="status" class="small">Select and copy the URL manually.</span>
                <p class="small mt-2">URLs pasted outside managed fields are not tracked as usages.</p>
            </div>
        @else
            <a href="{{ route('admin.media.file', $asset) }}" class="text-decoration-underline d-block mb-4">View private file</a>
        @endif
    @endif
    @if($asset->status === 'active')
    <form action="{{ route('admin.media.update', $asset) }}" method="POST" class="d-grid gap-4 admin-max-width-2xl bg-white p-4 border rounded-2">
        @csrf @method('PUT')
        <label>Title<input name="title" value="{{ old('title', $asset->title) }}" required maxlength="255" class="form-control d-block w-100 rounded-2 border mt-1"></label>
        <label>Alternative text<input name="alt_text" value="{{ old('alt_text', $asset->alt_text) }}" maxlength="255" class="form-control d-block w-100 rounded-2 border mt-1"></label>
        <label>Caption<textarea name="caption" maxlength="2000" class="form-control d-block w-100 rounded-2 border mt-1">{{ old('caption', $asset->caption) }}</textarea></label>
        <button class="btn btn-primary px-3 py-2 admin-justify-self-start">Save details</button>
    </form>
    @endif
    <h2 class="mt-5 mb-3 admin-section-title">Tracked usages</h2>
    <ul class="small admin-stack-2">
        @forelse($asset->usages as $usage)
            <li>{{ $usage->mediable_type }} #{{ $usage->mediable_id }} — {{ $usage->slot }}</li>
        @empty
            <li>No managed references. External URL usage may still exist.</li>
        @endforelse
    </ul>
    <h2 class="mt-4 mb-3 admin-section-title">Historical retention</h2>
    <ul class="small admin-stack-2">
        @forelse($asset->retentionHolds as $hold)
            <li>{{ $hold->reference }} — {{ $hold->snapshot['title'] ?? 'Purchased image' }} (retained)</li>
        @empty
            <li>No historical holds.</li>
        @endforelse
    </ul>
    <form action="{{ route('admin.media.destroy', $asset) }}" method="POST" class="mt-5" onsubmit="return confirm('Delete this file? Any copied external URLs will stop working.')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger text-danger text-decoration-underline" @disabled($asset->status !== 'active' || $asset->usages->isNotEmpty() || $asset->retentionHolds->isNotEmpty())>delete unused media</button>
    </form>
</x-admin-layout>
