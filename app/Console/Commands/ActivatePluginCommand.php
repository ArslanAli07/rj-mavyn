<?php

namespace App\Console\Commands;

use App\Services\Plugins\PluginManager;
use Illuminate\Console\Command;
use Throwable;

class ActivatePluginCommand extends Command
{
    protected $signature = 'platform:plugins:activate {plugin}';

    protected $description = 'Activate an installed plugin after dependency and contribution validation';

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->change($this->argument('plugin'), true);
            $this->info('Plugin activated.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
