<?php

namespace App\Console\Commands;

use App\Services\Deployment\ReleaseManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class BuildReleaseManifestCommand extends Command
{
    protected $signature = 'platform:release:manifest {release} {--output=}';

    protected $description = 'Build a deterministic source/version inventory without client data or secrets';

    public function handle(ReleaseManifest $manifests): int
    {
        try {
            $manifest = $manifests->build($this->argument('release'));
            $path = $this->option('output') ?: config('platform.manifest');
            if (file_exists($path)) {
                throw new RuntimeException('Choose a new manifest path; existing release inventories are immutable.');
            }
            File::ensureDirectoryExists(dirname($path));
            $temporary = tempnam(dirname($path), 'release-');
            try {
                if (file_put_contents($temporary, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false || ! rename($temporary, $path)) {
                    throw new RuntimeException('Could not write release inventory.');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $this->line(json_encode(['release' => $manifest['release'], 'source_hash' => $manifest['source_hash'], 'files' => count($manifest['files'])], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
