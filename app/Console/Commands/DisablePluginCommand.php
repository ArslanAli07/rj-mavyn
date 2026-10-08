<?php

namespace App\Console\Commands;

use App\Services\Plugins\PluginManager;
use Illuminate\Console\Command;
use Throwable;

class DisablePluginCommand extends Command
{
    protected $signature = 'platform:plugins:disable {plugin}';

    protected $description = 'Deactivate a plugin while preserving settings and historical data';

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->change($this->argument('plugin'), false);
            $this->info('Plugin disabled; data retained.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
