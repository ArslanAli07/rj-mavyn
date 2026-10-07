<?php

namespace App\Models;

use App\Services\Media\MediaLifecycleService;
use App\Services\Media\MediaPolicyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['uploaded_by', 'title', 'alt_text', 'caption', 'visibility', 'status', 'processing_status', 'processing_error', 'processing_generation', 'processing_attempts', 'processing_started_at'];

    protected function casts(): array
    {
        return ['processing_generation' => 'integer', 'processing_attempts' => 'integer', 'processing_started_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(function (MediaAsset $asset) {
            if ($asset->status !== 'deleting') {
                throw new \LogicException('Media deletion must use the lifecycle service.');
            }
            app(MediaLifecycleService::class)->assertUnreferenced($asset);
        });
    }

    public function shouldDeletePreservingMedia(): bool
    {
        return true;
    }

    public function retentionHolds(): HasMany
    {
        return $this->hasMany(MediaRetentionHold::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('original')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media && ! in_array($media->mime_type, MediaPolicyRegistry::IMAGE_MIMES, true)) {
            return;
        }
        foreach (app(MediaPolicyRegistry::class)->profiles() as $name => $profile) {
            $conversion = $this->addMediaConversion($name)->fit(Fit::Max, $profile['width'], $profile['height'])
                ->format('webp')->quality(82)->queued()->performOnCollections('original');
            if ($profile['responsive']) {
                $conversion->withResponsiveImages();
            }
        }
    }
}
