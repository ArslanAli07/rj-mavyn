<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LegacyMediaSource
{
    public function resolve(string $path, bool $documentRoot = true): array
    {
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
            $documentRoot = false;
        }
        $disk = $documentRoot && (str_starts_with($path, 'images/') || str_starts_with($path, 'assets/'))
            ? 'legacy_assets' : 'public';

        return ['disk' => $disk, 'path' => $path];
    }

    public function key(array $source, string $visibility): string
    {
        return hash('sha256', $visibility."\0".$source['disk']."\0".$source['path']);
    }

    /** Refuse traversal, URLs, generated media folders, and symlink escapes. */
    public function path(array $source, bool $mustExist = true): string
    {
        $disk = $source['disk'];
        $path = $source['path'];
        if (! in_array($disk, ['public', 'legacy_assets', 'local', 'media_public'], true)
            || ! is_string($path) || $path === '' || strlen($path) > 1024
            || preg_match('~[\\\\\x00-\x1f:]|^/|(^|/)\.{1,2}(/|$)|//~', $path)
            || ($disk === 'public' && str_starts_with($path, 'media/'))
            || ($disk === 'legacy_assets' && ! str_starts_with($path, 'images/') && ! str_starts_with($path, 'assets/'))) {
            throw new RuntimeException('Unsupported or unsafe legacy path.');
        }
        if (config('filesystems.disks.'.$disk.'.driver') !== 'local') {
            throw new RuntimeException('Legacy migration requires local source and destination disks.');
        }
        $root = realpath(Storage::disk($disk)->path(''));
        if (! $root) {
            throw new RuntimeException('Storage root does not exist.');
        }
        $candidate = $root;
        foreach (explode('/', $path) as $segment) {
            $candidate .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($candidate)) {
                throw new RuntimeException('Symlinked legacy paths require manual reconciliation.');
            }
        }
        if ($mustExist && ! is_file($candidate)) {
            throw new RuntimeException('Missing original; restore it from backup.');
        }

        return $candidate;
    }

    public function inspect(array $source, bool $image = true): array
    {
        $path = $this->path($source);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = filesize($path);
        $dimensions = @getimagesize($path);
        if ($size === false || $size < 1 || $size > 20971520
            || (! in_array($mime, MediaPolicyRegistry::IMAGE_MIMES, true) && ($image || $mime !== 'application/pdf'))
            || (in_array($mime, MediaPolicyRegistry::IMAGE_MIMES, true)
                && (! $dimensions || $dimensions[0] > 8192 || $dimensions[1] > 8192 || $dimensions[0] * $dimensions[1] > 40000000))) {
            throw new RuntimeException('Legacy file violates the type, size, or image dimension policy.');
        }

        return ['path' => $path, 'checksum' => hash_file('sha256', $path), 'mime' => $mime,
            'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null, 'size' => $size];
    }

    public function verify(array $source, string $checksum): void
    {
        if (! hash_equals($checksum, hash_file('sha256', $this->path($source)))) {
            throw new RuntimeException('Checksum mismatch; restore the original or reconcile the changed source manually.');
        }
    }

    public function assertPrivateStorage(): void
    {
        $privateRoot = realpath(Storage::disk('local')->path(''));
        if (! $privateRoot || config('filesystems.disks.local.driver') !== 'local') {
            throw new RuntimeException('Private receipt storage requires an existing local private root.');
        }
        foreach ([public_path(), Storage::disk('public')->path('')] as $publicRoot) {
            $publicRoot = realpath($publicRoot);
            if ($publicRoot && ($privateRoot === $publicRoot || str_starts_with($privateRoot, $publicRoot.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Private receipt storage is inside a public storage root. Fix storage configuration first.');
            }
        }
    }
}
