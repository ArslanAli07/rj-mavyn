<x-admin-layout title="Media details">
    <a href="{{ route('admin.media.index') }}" class="text-sm underline">Back to Media Library</a>
    <h1 class="text-2xl font-bold my-5">{{ $asset->title }}</h1>
    @if($errors->any())
        <p role="alert" class="mb-4 text-red-700">{{ $errors->first() }}</p>
    @endif
    <p role="status" class="my-3 text-sm">Asset: {{ ucfirst($asset->status) }} · Processing: {{ str_replace('_', ' ', ucfirst($asset->processing_status)) }}</p>
    @if($asset->processing_error)
        <p role="alert" class="text-red-700 text-sm mb-3">{{ $asset->processing_error }}</p>
    @endif
    @if($asset->status === 'active' && $asset->processing_status !== 'not_applicable')
        <form action="{{ route('admin.media.retry', $asset) }}" method="POST" class="mb-5">
            @csrf
            <button class="btn-gold px-4 py-2">Retry image processing</button>
            <p class="text-xs mt-2">Queued images use the original until processing finishes. A stalled queue can be retried after 10 minutes.</p>
        </form>
    @endif
    @if($asset->status === 'deleting')
        @php($cleanup = \App\Models\MediaCleanupTask::where('media_asset_id', $asset->id)->first())
        <p class="text-sm mb-2">Cleanup: {{ $cleanup?->status ?? 'queued' }} · Attempts: {{ $cleanup?->attempts ?? 0 }}</p>
        @if($cleanup?->last_error)<p class="text-red-700 text-sm mb-3">{{ $cleanup->last_error }}</p>@endif
        <form action="{{ route('admin.media.retry-cleanup', $asset) }}" method="POST" class="mb-5">
            @csrf <button class="btn-gold px-4 py-2">Retry cleanup</button>
        </form>
    @endif
    @php($file = $asset->getFirstMedia('original'))
    @if($file && $asset->status === 'active')
        @if(str_starts_with($file->mime_type, 'image/'))
            <img src="{{ $asset->visibility === 'private' ? route('admin.media.file', $asset) : $file->getAvailableUrl(['thumbnail']) }}"
                 alt="{{ $asset->alt_text ?? '' }}" class="max-h-80 max-w-full object-contain mb-5">
        @endif
        <p class="text-sm mb-5">{{ $file->mime_type }} · {{ $file->human_readable_size }} · {{ ucfirst($asset->visibility) }}</p>
        @if($asset->visibility === 'public')
            <div x-data="{ copied: false, failed: false }" class="mb-6">
                <label class="block text-sm mb-2" for="media-url">Public URL</label>
                <input id="media-url" x-ref="url" readonly value="{{ $file->getUrl() }}" class="w-full rounded-lg border mb-2" @click="$el.select()">
                <button type="button" class="btn-gold px-4 py-2"
                        @click="navigator.clipboard ? navigator.clipboard.writeText($refs.url.value).then(() => { copied = true; failed = false }).catch(() => { failed = true; $refs.url.select() }) : (failed = true, $refs.url.select())">Copy URL</button>
                <span x-show="copied" x-cloak role="status" class="text-sm">URL copied.</span>
                <span x-show="failed" x-cloak role="status" class="text-sm">Select and copy the URL manually.</span>
                <p class="text-xs mt-2">URLs pasted outside managed fields are not tracked as usages.</p>
            </div>
        @else
            <a href="{{ route('admin.media.file', $asset) }}" class="underline block mb-5">View private file</a>
        @endif
    @endif
    @if($asset->status === 'active')
    <form action="{{ route('admin.media.update', $asset) }}" method="POST" class="grid gap-4 max-w-2xl bg-white p-5 border rounded-xl">
        @csrf @method('PUT')
        <label>Title<input name="title" value="{{ old('title', $asset->title) }}" required maxlength="255" class="block w-full rounded-lg border mt-1"></label>
        <label>Alternative text<input name="alt_text" value="{{ old('alt_text', $asset->alt_text) }}" maxlength="255" class="block w-full rounded-lg border mt-1"></label>
        <label>Caption<textarea name="caption" maxlength="2000" class="block w-full rounded-lg border mt-1">{{ old('caption', $asset->caption) }}</textarea></label>
        <button class="btn-gold px-4 py-2 justify-self-start">Save details</button>
    </form>
    @endif
    <h2 class="font-semibold mt-8 mb-3">Tracked usages</h2>
    <ul class="text-sm space-y-2">
        @forelse($asset->usages as $usage)
            <li>{{ $usage->mediable_type }} #{{ $usage->mediable_id }} — {{ $usage->slot }}</li>
        @empty
            <li>No managed references. External URL usage may still exist.</li>
        @endforelse
    </ul>
    <h2 class="font-semibold mt-6 mb-3">Historical retention</h2>
    <ul class="text-sm space-y-2">
        @forelse($asset->retentionHolds as $hold)
            <li>{{ $hold->reference }} — {{ $hold->snapshot['title'] ?? 'Purchased image' }} (retained)</li>
        @empty
            <li>No historical holds.</li>
        @endforelse
    </ul>
    <form action="{{ route('admin.media.destroy', $asset) }}" method="POST" class="mt-8" onsubmit="return confirm('Delete this file? Any copied external URLs will stop working.')">
        @csrf @method('DELETE')
        <button class="text-red-700 underline" @disabled($asset->status !== 'active' || $asset->usages->isNotEmpty() || $asset->retentionHolds->isNotEmpty())>Delete unused media</button>
    </form>
</x-admin-layout>
