<?php

namespace App\Services\Media;

use App\Models\LegacyMediaImport;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\FileManipulator;

class LegacyMediaImporter
{
    public function __construct(private LegacyMediaSource $sources) {}

    public function image(array $source, string $title): MediaAsset
    {
        try {
            return $this->importImage($source, $title);
        } catch (\Throwable $exception) {
            LegacyMediaImport::where('source_key', $this->sources->key($source, 'public'))->update(['last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function importImage(array $source, string $title): MediaAsset
    {
        if ($source['disk'] === 'public' && str_starts_with($source['path'], 'receipts/')) {
            throw new RuntimeException('A receipt cannot be imported as public content.');
        }
        $file = $this->sources->inspect($source);
        $journal = LegacyMediaImport::firstOrCreate(['source_key' => $this->sources->key($source, 'public')], [
            'source_disk' => $source['disk'], 'source_path' => $source['path'],
            'checksum' => $file['checksum'], 'visibility' => 'public',
        ]);
        if (! hash_equals($journal->checksum, $file['checksum'])) {
            throw new RuntimeException('The legacy source changed after import. Reconcile it manually.');
        }

        // Compatible public images share originals. Private receipts never participate.
        if (! $journal->media_asset_id && $journal->status === 'pending') {
            $prior = LegacyMediaImport::where('visibility', 'public')->where('checksum', $file['checksum'])
                ->where('status', 'imported')->whereNotNull('media_asset_id')->first();
            if ($prior) {
                $this->sources->verify(['disk' => $prior->destination_disk, 'path' => $prior->destination_path], $prior->checksum);
                $journal->update(['media_asset_id' => $prior->media_asset_id, 'destination_disk' => $prior->destination_disk,
                    'destination_path' => $prior->destination_path, 'status' => 'imported']);
            }
        }
        if (! $journal->media_asset_id && $journal->status === 'imported') {
            throw new RuntimeException('The imported asset was deleted. Reconcile this source manually.');
        }
        if (! $journal->media_asset_id) {
            DB::transaction(function () use ($journal, $title) {
                $asset = MediaAsset::create(['title' => mb_substr($title, 0, 255), 'visibility' => 'public',
                    'status' => 'uploading', 'processing_status' => 'pending', 'processing_generation' => 1]);
                $journal->update(['media_asset_id' => $asset->id]);
            });
        }
        $asset = MediaAsset::with('media')->findOrFail($journal->media_asset_id);
        if (! in_array($asset->status, ['active', 'uploading'], true) || $asset->visibility !== 'public') {
            throw new RuntimeException('Imported asset is unavailable. Reconcile it before resuming.');
        }
        $media = $asset->getFirstMedia('original');
        $recovering = $media && $asset->status === 'uploading' && $journal->status === 'pending';
        if (! $media) {
            $extension = match ($file['mime']) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            };
            $media = $asset->addMedia($file['path'])->preservingOriginal()->usingName($asset->title)
                ->usingFileName('original.'.$extension)
                ->withCustomProperties(['width' => $file['width'], 'height' => $file['height'], 'legacy_checksum' => $file['checksum']])
                ->storingConversionsOnDisk('media_public')->toMediaCollection('original', 'media_public');
        }
        // Includes recovery after a crash between copying and journal completion.
        $destination = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
        if ($recovering) {
            // An interrupted Spatie copy may leave its media row and a partial/missing original.
            $lock = Cache::lock('media-operation-'.$asset->id, 300);
            if (! $lock->get()) {
                throw new RuntimeException('Image processing is still running. Retry migration after it finishes.');
            }
            $stream = null;
            try {
                $this->sources->verify($source, $journal->checksum);
                $stream = fopen($file['path'], 'rb');
                if (! Storage::disk($destination['disk'])->put($destination['path'], $stream)) {
                    throw new RuntimeException('Interrupted original could not be restored. Rerun migration.');
                }
                $asset->update(['processing_generation' => $asset->processing_generation + 1, 'processing_status' => 'pending']);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $lock->release();
            }
        }
        $this->sources->verify($destination, $journal->checksum);
        $this->sources->verify($source, $journal->checksum);
        DB::transaction(function () use ($asset, $journal, $destination) {
            $locked = MediaAsset::lockForUpdate()->findOrFail($asset->id);
            if (! in_array($locked->status, ['active', 'uploading'], true)) {
                throw new RuntimeException('Imported asset entered deletion; references were not changed.');
            }
            $locked->update(['status' => 'active']);
            $journal->update(['destination_disk' => $destination['disk'], 'destination_path' => $destination['path'],
                'status' => 'imported', 'last_error' => null]);
        });
        if ($recovering) {
            // Old queued jobs may refer to the incomplete copy; generation prevents stale writes.
            app(FileManipulator::class)->createDerivedFiles($media->fresh(), queueAll: true);
        }

        return $asset->fresh(['media']);
    }
}
