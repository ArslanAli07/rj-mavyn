<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use App\Models\MediaUsage;
use App\Services\Media\MediaLibraryService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

trait HasLibraryMedia
{
    public static function bootHasLibraryMedia(): void
    {
        static::deleting(function ($model) {
            DB::transaction(function () use ($model) {
                foreach ($model->mediaUsages()->orderBy('media_asset_id')->get() as $usage) {
                    MediaAsset::lockForUpdate()->findOrFail($usage->media_asset_id);
                    $usage->delete();
                }
            });
        });
    }

    public function mediaUsages(): MorphMany
    {
        return $this->morphMany(MediaUsage::class, 'mediable');
    }

    public function libraryAssetIds(string $slot): array
    {
        return $this->mediaUsages()->where('slot', $slot)->orderBy('sort_order')->orderBy('id')
            ->pluck('media_asset_id')->all();
    }

    /** Resolve current media even when the owning content record came from a page cache. */
    public function libraryImages(string $slot, string $conversion = ''): array
    {
        return $this->mediaUsages()->where('slot', $slot)->orderBy('sort_order')->orderBy('id')
            ->with('asset.media')->get()->filter(fn ($usage) => $usage->asset?->status === 'active' && $usage->asset?->visibility === 'public'
                && in_array($usage->asset->getFirstMedia('original')?->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true))
            ->map(function ($usage) use ($conversion) {
                $image = app(MediaLibraryService::class)->present($usage->asset, $conversion);
                $image['alt_text'] = $usage->metadata['alt_text'] ?? $image['alt_text'];
                $image['caption'] = $usage->metadata['caption'] ?? $image['caption'];

                return $image;
            })->values()->all();
    }
}
