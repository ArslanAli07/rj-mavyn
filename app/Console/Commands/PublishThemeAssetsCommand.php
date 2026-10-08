<?php

namespace App\Console\Commands;

use App\Services\Themes\ThemeAssets;
use Illuminate\Console\Command;
use Throwable;

final class PublishThemeAssetsCommand extends Command
{
    protected $signature = 'platform:themes:assets';

    protected $description = 'Publish checked theme assets into immutable namespaced public paths';

    public function handle(ThemeAssets $assets): int
    {
        try {
            $assets->publish();
            $this->info('Theme assets published.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
