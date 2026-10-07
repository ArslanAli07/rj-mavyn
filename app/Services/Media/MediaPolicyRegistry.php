<?php

namespace App\Services\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Trusted providers register bounded policies; request data never defines processing instructions. */
class MediaPolicyRegistry
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private array $slots = [];

    private array $profiles = [];

    public function __construct()
    {
        foreach (['thumbnail' => [320, false], 'product-card' => [640, true], 'product-detail' => [1280, true], 'hero' => [1920, true]] as $name => [$size, $responsive]) {
            $this->registerProfile($name, $size, $size, $responsive);
        }
        foreach ([
            'catalog.product.main' => ['core.product', 1, null],
            'catalog.product.gallery' => ['core.product', 30, null],
            'catalog.category.hero' => ['core.category', 1, null],
            'content.hero.desktop' => ['core.home-slide', 1, null],
            'content.hero.mobile' => ['core.home-slide', 1, null],
            'store.logo' => ['core.setting', 1, 'site_logo'],
            'store.favicon' => ['core.setting', 1, 'site_favicon'],
            'content.gallery' => ['core.setting', 30, 'homepage_gallery'],
        ] as $slot => [$alias, $count, $key]) {
            $this->registerSlot($slot, $alias, $count, $key);
        }
    }

    public function registerSlot(string $id, string $alias, int $maxCount, ?string $settingKey = null, int $maxBytes = 20971520): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $id) || isset($this->slots[$id])
            || ! Relation::getMorphedModel($alias) || $maxCount < 1 || $maxCount > 100 || $maxBytes < 1 || $maxBytes > 20971520) {
            throw new InvalidArgumentException('Invalid or duplicate media slot.');
        }
        $this->slots[$id] = ['alias' => $alias, 'max_count' => $maxCount, 'setting_key' => $settingKey, 'max_bytes' => $maxBytes];
    }

    public function slot(string $id, Model $target, string $field = 'media'): array
    {
        $policy = $this->slots[$id] ?? null;
        if (! $policy || $target->getMorphClass() !== $policy['alias'] || ($policy['setting_key'] && $target->key !== $policy['setting_key'])) {
            throw ValidationException::withMessages([$field => 'Unsupported image slot or target.']);
        }

        return $policy;
    }

    public function registerProfile(string $id, int $width, int $height, bool $responsive = false): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $id) || isset($this->profiles[$id])
            || count($this->profiles) >= 8 || $width < 1 || $width > 4096 || $height < 1 || $height > 4096) {
            throw new InvalidArgumentException('Invalid or duplicate media conversion profile.');
        }
        $this->profiles[$id] = compact('width', 'height', 'responsive');
    }

    public function profiles(): array
    {
        return $this->profiles;
    }

    public function profile(string $id): array
    {
        return $this->profiles[$id] ?? throw ValidationException::withMessages(['conversion' => 'Unknown media conversion.']);
    }
}
