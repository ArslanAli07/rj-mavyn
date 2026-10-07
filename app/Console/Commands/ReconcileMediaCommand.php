<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\MediaCleanupTask;
use App\Services\Media\MediaLibraryService;
use App\Services\Media\MediaLifecycleService;
use App\Services\Media\MediaPolicyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReconcileMediaCommand extends Command
{
    protected $signature = 'media:reconcile {--apply : Queue known stale uploads, cleanup tasks and stalled conversions}';

    protected $description = 'Report media lifecycle gaps; unknown storage folders are never automatically deleted';

    public function handle(MediaLifecycleService $lifecycle, MediaLibraryService $library): int
    {
        $apply = $this->option('apply');
        $staleUploads = MediaAsset::where('status', 'uploading')->where('created_at', '<', now()->subHours(24))->get();
        $tasks = MediaCleanupTask::whereIn('status', ['queued', 'processing', 'failed'])->where('updated_at', '<', now()->subMinutes(10))->get();
        $stalled = MediaAsset::where('status', 'active')->whereIn('processing_status', ['pending', 'processing'])
            ->where('updated_at', '<', now()->subMinutes(10))->with('media')->get();
        $this->info("Stale uploads: {$staleUploads->count()}; retryable cleanup tasks: {$tasks->count()}; stalled processing: {$stalled->count()}.");
        foreach ($staleUploads as $asset) {
            if ($apply) {
                $this->attempt(fn () => $lifecycle->requestDeletion($asset), 'upload #'.$asset->id);
            }
        }
        foreach ($tasks as $task) {
            if ($apply) {
                $lifecycle->dispatchCleanup($task);
            }
        }
        foreach ($stalled as $asset) {
            $media = $asset->getFirstMedia('original');
            if ($media && in_array($media->mime_type, MediaPolicyRegistry::IMAGE_MIMES, true)) {
                if ($apply) {
                    $this->attempt(fn () => $library->queueProcessing($asset), 'processing #'.$asset->id);
                }
            }
        }
        foreach (['media_public', 'media_private'] as $diskName) {
            $known = Media::where('disk', $diskName)->orWhere('conversions_disk', $diskName)->pluck('id')->map(fn ($id) => (string) $id)->all();
            $pending = MediaCleanupTask::where('status', '!=', 'completed')->get()->flatMap(fn ($task) => collect($task->paths)->where('disk', $diskName)->pluck('directory'))->all();
            foreach (Storage::disk($diskName)->directories() as $directory) {
                if (! in_array($directory, $known, true) && ! in_array($directory, $pending, true)) {
                    $this->warn("Unrecognized folder on {$diskName}: {$directory}. Retained for manual reconciliation.");
                }
            }
        }
        MediaAsset::where('status', 'active')->with('media')->chunkById(100, function ($assets) use ($apply) {
            foreach ($assets as $asset) {
                $media = $asset->getFirstMedia('original');
                if (! $media || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                    $this->warn('Missing original for asset #'.$asset->id.'. Restore from backup; derivatives are not a replacement.');
                    if ($apply) {
                        $asset->update(['processing_status' => 'failed', 'processing_error' => 'Original missing. Restore from backup before retrying.']);
                    }
                }
            }
        });

        return self::SUCCESS;
    }

    private function attempt(callable $operation, string $label): void
    {
        try {
            $operation();
        } catch (\Throwable $exception) {
            report($exception);
            $this->warn('Could not queue '.$label.'. Inspect logs; the next reconciliation can retry.');
        }
    }
}
