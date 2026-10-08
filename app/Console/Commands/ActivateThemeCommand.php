<?php

namespace App\Console\Commands;

use App\Services\Themes\ThemeManager;
use Illuminate\Console\Command;
use Throwable;

final class ActivateThemeCommand extends Command
{
    protected $signature = 'platform:themes:activate {theme}';

    protected $description = 'Validate and atomically activate an installed storefront theme';

    public function handle(ThemeManager $manager): int
    {
        try {
            $manager->activate($this->argument('theme'));
            $this->info('Theme activated.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
