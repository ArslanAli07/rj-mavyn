<?php

namespace App\Services\Media;

use App\Models\Admin;
use App\Models\MediaAsset;
use App\Models\MediaRetentionHold;
use App\Models\MediaUsage;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Throwable;

/** Core adapter: consumers never need Spatie storage internals. */
class MediaLibraryService
{
    public function __construct(private MediaPolicyRegistry $policies, private MediaLifecycleService $lifecycle) {}

    public function upload(UploadedFile $file, array $metadata, Admin $actor): MediaAsset
    {
        Gate::forUser($actor)->authorize('manage-media');
        Validator::make(['file' => $file] + $metadata, [
            'file' => 'required|file|max:20480|mimes:jpg,jpeg,png,webp,gif,pdf,mp3,wav,mp4,webm',
            'visibility' => 'required|in:public,private',
            'title' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:2000',
        ])->validate();

        $dimensions = in_array($file->getMimeType(), MediaPolicyRegistry::IMAGE_MIMES, true) ? @getimagesize($file->getRealPath()) : null;
        if (in_array($file->getMimeType(), MediaPolicyRegistry::IMAGE_MIMES, true)
            && (! $dimensions || $dimensions[0] > 8192 || $dimensions[1] > 8192 || $dimensions[0] * $dimensions[1] > 40000000)) {
            throw ValidationException::withMessages(['file' => 'Images must be valid, at most 8192 pixels per side and 40 megapixels.']);
        }
        $asset = MediaAsset::create([
            'status' => 'uploading', 'processing_status' => $dimensions ? 'pending' : 'not_applicable',
            'processing_generation' => 1,

            'uploaded_by' => $actor->id,
            'title' => $metadata['title'],
            'alt_text' => $metadata['alt_text'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'visibility' => $metadata['visibility'],
        ]);
        $disk = $asset->visibility === 'private' ? 'media_private' : 'media_public';

        try {
            $asset->addMedia($file)->usingName($asset->title)
                ->withCustomProperties(['width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null])
                ->storingConversionsOnDisk($disk)->toMediaCollection('original', $disk);
        } catch (Throwable $exception) {
            // Preserve failed-upload cleanup intent; the reconciler recovers interrupted attempts.
            $this->lifecycle->requestDeletion($asset);
            throw $exception;
        }

        $asset->update(['status' => 'active']);

        return $asset->fresh(['media', 'usages']);
    }

    public function attach(MediaAsset $asset, Model $target, string $slot, Admin $actor, array $metadata = []): MediaUsage
    {
        Gate::forUser($actor)->authorize('manage-media');
        $type = $target->getMorphClass();
        if (! $target->exists || Relation::getMorphedModel($type) !== $target::class) {
            throw ValidationException::withMessages(['media' => 'An existing, registered media target is required.']);
        }
        Validator::make(['slot' => $slot, 'metadata' => $metadata], [
            'slot' => 'required|string|max:100|regex:/^[a-z0-9][a-z0-9._-]+$/',
            'metadata' => 'array:alt_text,caption',
            'metadata.alt_text' => 'nullable|string|max:255',
            'metadata.caption' => 'nullable|string|max:2000',
        ])->validate();

        $policy = $this->policies->slot($slot, $target);

        return DB::transaction(function () use ($asset, $target, $type, $slot, $metadata, $policy) {
            $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());
            $locked = MediaAsset::lockForUpdate()->with('media')->findOrFail($asset->id);
            if (! $this->isPublicImage($locked) || $locked->getFirstMedia('original')->size > $policy['max_bytes']) {
                throw ValidationException::withMessages(['media' => 'Select an active public image within this slot’s file limit.']);
            }
            $existing = MediaUsage::where('mediable_type', $type)->where('mediable_id', $target->getKey())->where('slot', $slot);
            if (! (clone $existing)->where('media_asset_id', $locked->id)->exists() && $existing->count() >= $policy['max_count']) {
                throw ValidationException::withMessages(['media' => 'This image slot is full. Replace or detach a selection first.']);
            }
            $prior = (clone $existing)->where('media_asset_id', $locked->id)->first();
            if ($prior) {
                $prior->update(['metadata' => $metadata]);

                return $prior;
            }

            return MediaUsage::updateOrCreate([
                'media_asset_id' => $locked->id, 'mediable_type' => $type,
                'mediable_id' => $target->getKey(), 'slot' => $slot,
            ], ['metadata' => $metadata, 'sort_order' => ((clone $existing)->max('sort_order') ?? -1) + 1]);
        });
    }

    /** Rules shared by picker-backed image fields, including legacy upload conflicts. */
    public function imageRules(?string $uploadField = null): array
    {
        return ['nullable', 'integer', function ($attribute, $value, $fail) use ($uploadField) {
            if ($uploadField && request()->hasFile($uploadField)) {
                $fail('Choose a library image or a legacy upload, one at a time.');

                return;
            }
            $asset = MediaAsset::with('media')->find($value);
            if (! $this->isPublicImage($asset)) {
                $fail('Select a public JPEG, PNG, WebP, or GIF image.');
            }
        }];
    }

    private function isPublicImage(?MediaAsset $asset): bool
    {
        return $asset && $asset->status === 'active' && $asset->visibility === 'public'
            && in_array($asset->getFirstMedia('original')?->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    /** Replace a typed image slot atomically; originals remain owned by the library. */
    public function syncImages(Model $target, string $slot, array $assetIds, Admin $actor, string $field = 'media'): void
    {
        Gate::forUser($actor)->authorize('manage-media');
        $policy = $this->policies->slot($slot, $target, $field);
        $limit = $policy['max_count'];
        Validator::make([$field => $assetIds], [
            $field => ['array', 'max:'.$limit], $field.'.*' => ['required', 'integer', 'distinct'],
        ])->validate();
        DB::transaction(function () use ($target, $slot, $assetIds, $actor, $field, $policy) {
            $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());
            $usages = $target->mediaUsages()->where('slot', $slot)->get();
            $lockIds = $usages->pluck('media_asset_id')->merge($assetIds)->unique()->sort()->values();
            $assets = MediaAsset::whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->with('media')->get()->keyBy('id');
            foreach ($assetIds as $id) {
                if (! $this->isPublicImage($assets->get($id)) || $assets->get($id)->getFirstMedia('original')->size > $policy['max_bytes']) {
                    throw ValidationException::withMessages([$field => 'Select a public JPEG, PNG, WebP, or GIF image.']);
                }
            }
            $metadata = $usages->keyBy('media_asset_id')->map(fn ($usage) => $usage->metadata ?? []);
            $target->mediaUsages()->where('slot', $slot)->delete();
            foreach (array_values($assetIds) as $order => $id) {
                $usage = $this->attach($assets->get($id), $target, $slot, $actor, $metadata->get($id, []));
                $usage->update(['sort_order' => $order]);
            }
        });
    }

    public function setProductMainImage(Product $product, ?int $assetId, Admin $actor): void
    {
        $this->syncImages($product, 'catalog.product.main', $assetId ? [$assetId] : [], $actor, 'main_image_asset_id');
    }

    public function detach(MediaUsage $usage, Admin $actor): void
    {
        Gate::forUser($actor)->authorize('manage-media');
        DB::transaction(function () use ($usage) {
            MediaAsset::lockForUpdate()->findOrFail($usage->media_asset_id);
            $usage->delete();
        });
    }

    /** Public presentation only: private URL generation must pass through authorized delivery. */
    public function present(MediaAsset $asset, string $conversion = ''): array
    {
        $asset = $asset->fresh(['media']);
        abort_unless($asset && $asset->status === 'active' && $asset->visibility === 'public', 403);
        $media = $asset->getFirstMedia('original');
        abort_unless($media, 404);
        if ($conversion !== '') {
            $this->policies->profile($conversion);
        }
        $ready = $conversion === '' || ($asset->processing_status === 'ready' && $media->hasGeneratedConversion($conversion));
        $dimensions = $media->custom_properties;

        return [
            'id' => $asset->id, 'title' => $asset->title, 'alt_text' => $asset->alt_text ?? '',
            'caption' => $asset->caption, 'mime_type' => $ready && $conversion !== '' ? 'image/webp' : $media->mime_type,
            'size' => $media->size, 'url' => $media->getUrl($ready ? $conversion : ''),
            'width' => $dimensions['width'] ?? null, 'height' => $dimensions['height'] ?? null,
            'srcset' => $ready && $conversion !== '' ? $media->getSrcset($conversion) : '',
            'conversion_ready' => $ready, 'processing_status' => $asset->processing_status,
        ];
    }

    public function retryProcessing(MediaAsset $asset, Admin $actor): void
    {
        Gate::forUser($actor)->authorize('manage-media');
        $this->queueProcessing($asset);
    }

    /** Internal maintenance entry point; HTTP callers use the authorized retry operation. */
    public function queueProcessing(MediaAsset $asset): void
    {
        $lock = Cache::lock('media-operation-'.$asset->id, 300);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['media' => 'This file is already processing.']);
        }
        try {
            DB::transaction(function () use ($asset) {
                $asset = MediaAsset::lockForUpdate()->with('media')->findOrFail($asset->id);
                $media = $asset->getFirstMedia('original');
                if ($asset->status !== 'active' || ! $media || ! in_array($media->mime_type, MediaPolicyRegistry::IMAGE_MIMES, true)) {
                    throw ValidationException::withMessages(['media' => 'Only active image assets can be processed.']);
                }
                if (in_array($asset->processing_status, ['pending', 'processing'], true) && $asset->updated_at->gt(now()->subMinutes(10))) {
                    throw ValidationException::withMessages(['media' => 'Processing is already queued. Retry after 10 minutes if it remains stalled.']);
                }
                $asset->update(['processing_generation' => $asset->processing_generation + 1, 'processing_status' => 'pending', 'processing_error' => null, 'processing_started_at' => null]);
                // Dispatch after releasing the operation lock (also safe with a synchronous queue).

            });
        } finally {
            $lock->release();
        }
        DB::afterCommit(function () use ($asset) {
            $media = $asset->fresh()->getFirstMedia('original');
            app(FileManipulator::class)->createDerivedFiles($media, queueAll: true);
        });
    }

    public function delete(MediaAsset $asset, Admin $actor): void
    {
        Gate::forUser($actor)->authorize('manage-media');
        $this->lifecycle->requestDeletion($asset);
    }

    /** Orders snapshot public originals and retain them independently of live product usages. */
    public function retainOrderImage(OrderItem $item, Product $product): void
    {
        DB::transaction(function () use ($item, $product) {
            Product::lockForUpdate()->findOrFail($product->id);
            $item = OrderItem::lockForUpdate()->findOrFail($item->id);
            if ($item->media_asset_id) {
                return;
            }
            $id = $product->main_image_asset_id;
            if (! $id) {
                return;
            }
            $asset = MediaAsset::lockForUpdate()->with('media')->findOrFail($id);
            if (! $this->isPublicImage($asset)) {
                throw ValidationException::withMessages(['cart' => 'The product image changed. Review the cart and retry.']);
            }
            $media = $asset->getFirstMedia('original');
            $snapshot = ['title' => $asset->title, 'alt_text' => $asset->alt_text ?? '', 'caption' => $asset->caption,
                'mime_type' => $media->mime_type, 'width' => $media->getCustomProperty('width'), 'height' => $media->getCustomProperty('height')];
            $item->update(['media_asset_id' => $id, 'media_snapshot' => $snapshot]);
            MediaRetentionHold::firstOrCreate(['reference' => 'order-item-'.$item->id], [
                'media_asset_id' => $id, 'order_item_id' => $item->id, 'snapshot' => $snapshot,
            ]);
        });
    }
}
