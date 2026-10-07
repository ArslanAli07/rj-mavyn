<?php

namespace App\Jobs\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class ProcessMediaConversions extends PerformConversionsJob
{
    public int $tries = 3;

    public int $timeout = 120;

    private int $generation;

    public function __construct(ConversionCollection $conversions, Media $media, bool $onlyMissing = false)
    {
        parent::__construct($conversions, $media, $onlyMissing);
        $this->generation = MediaAsset::findOrFail($media->model_id)->processing_generation;
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(FileManipulator $fileManipulator): bool
    {
        $lock = Cache::lock('media-operation-'.$this->media->model_id, 300);
        if (! $lock->get()) {
            if ($this->job) {
                $this->release(300);
            }

            return false;
        }
        try {
            $asset = MediaAsset::find($this->media->model_id);
            if (! $asset || $asset->status === 'deleting' || $asset->processing_generation !== $this->generation) {
                return true;
            }
            $asset->update(['processing_status' => 'processing', 'processing_error' => null, 'processing_started_at' => now(), 'processing_attempts' => $asset->processing_attempts + 1]);
            if (! Storage::disk($this->media->disk)->exists($this->media->getPathRelativeToRoot())) {
                throw new RuntimeException('Media original is missing.');
            }
            $dimensions = @getimagesize($this->media->getPath());
            if (! $dimensions || $dimensions[0] > 8192 || $dimensions[1] > 8192 || $dimensions[0] * $dimensions[1] > 40000000) {
                throw new RuntimeException('Invalid or oversized original image.');
            }
            $this->media->setCustomProperty('width', $dimensions[0]);
            $this->media->setCustomProperty('height', $dimensions[1]);
            $this->media->save();
            parent::handle($fileManipulator);
            $media = $this->media->fresh();
            foreach ($this->conversions as $conversion) {
                if (! $media->hasGeneratedConversion($conversion->getName())
                    || ! Storage::disk($media->conversions_disk ?: $media->disk)->exists($media->getPathRelativeToRoot($conversion->getName()))) {
                    throw new RuntimeException('Conversion output is missing.');
                }
            }
            $asset->update(['processing_status' => 'ready', 'processing_error' => null, 'processing_started_at' => null]);

            return true;
        } catch (Throwable $exception) {
            MediaAsset::where('id', $this->media->model_id)->where('processing_generation', $this->generation)
                ->update(['processing_status' => 'failed', 'processing_error' => 'Image processing failed. Restore the original if missing, then retry.', 'processing_started_at' => null]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        MediaAsset::where('id', $this->media->model_id)->where('processing_generation', $this->generation)->where('status', '!=', 'deleting')
            ->update(['processing_status' => 'failed', 'processing_error' => 'Image processing failed or timed out. Retry processing.', 'processing_started_at' => null]);
    }
}
