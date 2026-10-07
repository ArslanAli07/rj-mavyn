<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Throwable;

class MediaPreflightCommand extends Command
{
    protected $signature = 'media:preflight';

    protected $description = 'Verify required media encoders, storage access and queue configuration';

    public function handle(): int
    {
        $valid = extension_loaded('gd') && function_exists('imagewebp') && (imagetypes() & IMG_WEBP);
        $this->line('GD WebP encoding: '.($valid ? 'available' : 'MISSING'));
        if (config('media-library.path_generator') !== DefaultPathGenerator::class || config('media-library.prefix') || config('media-library.custom_path_generators')) {
            $this->error('Durable cleanup requires the default numeric path generator without a prefix or custom generators.');
            $valid = false;
        }
        foreach (['media_public', 'media_private'] as $name) {
            $path = 'preflight-'.bin2hex(random_bytes(8));
            try {
                $disk = Storage::disk($name);
                if (! $disk->put($path, 'media-preflight') || $disk->get($path) !== 'media-preflight' || ! $disk->delete($path)) {
                    throw new \RuntimeException('Storage roundtrip failed.');
                }
                $this->line($name.': read/write/delete available');
            } catch (Throwable $exception) {
                $this->error($name.': storage roundtrip failed');
                $valid = false;
            }
        }
        $queue = config('queue.default');
        $this->line('Queue connection: '.$queue.'. Run a shared-cache conversion/cleanup worker for asynchronous queues.');
        if (in_array(config('cache.default'), ['array', 'null'], true) && $queue !== 'sync') {
            $this->error('Asynchronous media jobs require a shared cache lock store.');
            $valid = false;
        }
        $this->line('Optional optimizers (missing binaries degrade compression only):');
        foreach (['jpegoptim', 'optipng', 'pngquant', 'gifsicle', 'cwebp'] as $binary) {
            $found = false;
            foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
                if (is_executable($directory.'/'.$binary)) {
                    $found = true;
                }
            }
            $this->line($binary.': '.($found ? 'available' : 'not installed'));
        }

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
