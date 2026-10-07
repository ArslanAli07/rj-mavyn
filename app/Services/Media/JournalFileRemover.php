<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;

/** Capture cleanup when Spatie removes a failed upload's file metadata. */
class JournalFileRemover extends DefaultFileRemover
{
    public function removeAllFiles(Media $media): void
    {
        if ($media->model_type !== 'core.media-asset') {
            parent::removeAllFiles($media);

            return;
        }
        $asset = MediaAsset::findOrFail($media->model_id);
        $paths = [];
        foreach (array_unique([$media->disk, $media->conversions_disk ?: $media->disk]) as $disk) {
            $paths[] = ['disk' => $disk, 'directory' => dirname($media->getPathRelativeToRoot())];
        }
        app(MediaLifecycleService::class)->requestDeletion($asset, $paths);
    }
}
