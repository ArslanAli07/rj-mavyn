<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaLifecycleService;
use App\Services\Media\MediaPolicyRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class MediaController extends Controller
{
    public function __construct(private MediaLibraryService $library) {}

    public function index(Request $request)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');
        $filters = $request->validate([
            'q' => 'nullable|string|max:100', 'visibility' => 'nullable|in:public,private',
        ]);
        $assets = MediaAsset::where('status', '!=', 'uploading')->with('media')->withCount(['usages', 'retentionHolds'])
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('title', 'like', '%'.$term.'%'))
            ->when($filters['visibility'] ?? null, fn ($query, $visibility) => $query->where('visibility', $visibility))
            ->latest()->paginate(24)->withQueryString();

        return view('admin.media.index', compact('assets'));
    }

    public function picker(Request $request)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'type' => 'nullable|in:image']);
        $assets = MediaAsset::where('status', 'active')->where('visibility', 'public')->with('media')->latest()
            ->when(($filters['type'] ?? null) === 'image', fn ($query) => $query->whereHas('media', fn ($media) => $media->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('title', 'like', '%'.$term.'%'))
            ->paginate(24)->withQueryString();

        return response()->json([
            'data' => $assets->getCollection()->map(fn ($asset) => $this->library->present($asset, 'thumbnail')),
            'next_page_url' => $assets->nextPageUrl(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');
        $data = $request->validate([
            'file' => 'required|file', 'title' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255', 'caption' => 'nullable|string|max:2000',
            'visibility' => 'required|in:public,private',
        ]);
        $asset = $this->library->upload($request->file('file'), $data, $request->user('admin'));

        return redirect()->route('admin.media.show', $asset)->with('success', 'Media uploaded.');
    }

    public function show(Request $request, MediaAsset $asset)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');

        return view('admin.media.show', ['asset' => $asset->load(['media', 'usages', 'retentionHolds'])]);
    }

    public function update(Request $request, MediaAsset $asset)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');
        $data = $request->validate([
            'title' => 'required|string|max:255', 'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:2000',
        ]);
        abort_unless($asset->status === 'active', 409);
        // Visibility/file replacement are separate operations, never arbitrary metadata edits.
        $asset->update($data);

        return back()->with('success', 'Media details updated.');
    }

    public function destroy(Request $request, MediaAsset $asset)
    {
        $this->library->delete($asset, $request->user('admin'));

        return redirect()->route('admin.media.index')->with('success', 'Media deletion queued. Files are removed after cleanup succeeds.');
    }

    public function retry(Request $request, MediaAsset $asset)
    {
        $this->library->retryProcessing($asset, $request->user('admin'));

        return back()->with('success', 'Image processing queued.');
    }

    public function retryCleanup(Request $request, MediaAsset $asset)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');
        abort_unless($asset->status === 'deleting', 409);
        app(MediaLifecycleService::class)->requestDeletion($asset);

        return redirect()->route('admin.media.index')->with('success', 'Cleanup queued for retry.');
    }

    public function file(Request $request, MediaAsset $asset)
    {
        Gate::forUser($request->user('admin'))->authorize('manage-media');
        abort_unless($asset->status === 'active', 404);
        $media = $asset->getFirstMedia('original');
        abort_unless($media, 404);
        $data = $request->validate(['conversion' => 'nullable|string|max:100', 'responsive' => 'nullable|string|max:255']);
        $conversion = $data['conversion'] ?? '';
        if ($conversion !== '') {
            app(MediaPolicyRegistry::class)->profile($conversion);
            abort_unless($media->hasGeneratedConversion($conversion), 404);
        }
        $path = $media->getPath($conversion);
        if (! empty($data['responsive'])) {
            abort_unless(in_array($data['responsive'], $media->responsiveImages($conversion)->getFilenames(), true), 404);
            $directory = app(DefaultPathGenerator::class)->getPathForResponsiveImages($media);
            $path = Storage::disk($media->conversions_disk ?: $media->disk)->path($directory.$data['responsive']);
        }
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }
}
