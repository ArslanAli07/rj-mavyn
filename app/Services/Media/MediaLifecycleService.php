<?php

namespace App\Services\Media;

use App\Jobs\Media\CleanupMedia;
use App\Models\MediaAsset;
use App\Models\MediaCleanupTask;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaLifecycleService
{
    public function requestDeletion(MediaAsset $asset, array $extraPaths = []): MediaCleanupTask
    {
        return DB::transaction(function () use ($asset, $extraPaths) {
            $asset = MediaAsset::lockForUpdate()->findOrFail($asset->id);
            $this->assertUnreferenced($asset);
            $paths = $extraPaths;
            foreach ($asset->media()->get() as $media) {
                foreach (array_unique([$media->disk, $media->conversions_disk ?: $media->disk]) as $disk) {
                    $paths[] = ['disk' => $disk, 'directory' => dirname($media->getPathRelativeToRoot())];
                }
            }
            $task = MediaCleanupTask::firstOrNew(['media_asset_id' => $asset->id]);
            $paths = collect(array_merge($task->paths ?? [], $paths))->unique(fn ($path) => $path['disk'].'/'.$path['directory'])->values()->all();
            foreach ($paths as $path) {
                $this->assertSafePath($path);
            }
            $task->fill(['paths' => $paths, 'status' => 'queued', 'last_error' => null])->save();
            $asset->update(['status' => 'deleting']);
            DB::afterCommit(fn () => $this->dispatchCleanup($task));

            return $task;
        });
    }

    public function assertUnreferenced(MediaAsset $asset): void
    {
        if ($asset->usages()->exists() || $asset->retentionHolds()->exists() || OrderItem::where('media_asset_id', $asset->id)->exists()) {
            throw ValidationException::withMessages(['media' => 'This file is in use or retained for order history. It cannot be deleted.']);
        }
    }

    public function dispatchCleanup(MediaCleanupTask $task): void
    {
        try {
            CleanupMedia::dispatch($task->id)->afterCommit();
        } catch (Throwable $exception) {
            // Intent is already durable. Reconciliation can dispatch again after queue recovery.
            report($exception);
        }
    }

    private function assertSafePath(array $path): void
    {
        if (! in_array($path['disk'] ?? '', ['media_public', 'media_private'], true)
            || ! preg_match('/^[1-9][0-9]*$/', $path['directory'] ?? '')) {
            throw new RuntimeException('Cleanup requires a dedicated media disk and an isolated numeric media directory.');
        }
    }

    public function cleanup(int $taskId): void
    {
        $task = MediaCleanupTask::findOrFail($taskId);
        if ($task->status === 'completed') {
            return;
        }
        $lock = Cache::lock('media-operation-'.$task->media_asset_id, 300);
        if (! $lock->get()) {
            throw new RuntimeException('Media is still processing; cleanup will retry.');
        }
        try {
            $task->refresh();
            if ($task->status === 'completed') {
                return;
            }
            $asset = MediaAsset::find($task->media_asset_id);
            if ($asset) {
                if ($asset->status !== 'deleting') {
                    throw new RuntimeException('Media was not approved for deletion.');
                }
                $this->assertUnreferenced($asset);
            }
            $task->update(['status' => 'processing', 'attempts' => $task->attempts + 1]);
            // No filesystem operations inside a database transaction. The committed manifest survives failures.
            foreach ($task->paths as $path) {
                $this->assertSafePath($path);
                $disk = Storage::disk($path['disk']);
                if ($disk->directoryExists($path['directory']) && ! $disk->deleteDirectory($path['directory'])) {
                    throw new RuntimeException('Storage cleanup failed.');
                }
                if ($disk->directoryExists($path['directory'])) {
                    throw new RuntimeException('Storage directory still exists.');
                }
            }
            DB::transaction(function () use ($task) {
                $asset = MediaAsset::lockForUpdate()->find($task->media_asset_id);
                if ($asset) {
                    $this->assertUnreferenced($asset);
                    $asset->media()->delete(); // Files were removed already; bypass Spatie's immediate remover.
                    $asset->deletePreservingMedia();
                }
                $task->update(['status' => 'completed', 'last_error' => null, 'completed_at' => now()]);
            });
        } catch (Throwable $exception) {
            $task->update(['status' => 'failed', 'last_error' => 'Cleanup failed. Inspect storage access and retry cleanup.']);
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
