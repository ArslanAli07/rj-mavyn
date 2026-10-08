<?php

namespace App\Console\Commands;

use App\Services\Media\LegacyMediaMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MigrateLegacyMediaCommand extends Command
{
    protected $signature = 'media:migrate-legacy {--apply : Import references and move public receipts into private storage}';

    protected $description = 'Inventory and verify legacy media; report only unless --apply is supplied';

    public function handle(LegacyMediaMigrationService $migration): int
    {
        $lock = Cache::lock('media-legacy-migration', 86400);
        if (! $lock->get()) {
            $this->error('A legacy media migration is already running.');

            return self::FAILURE;
        }
        try {
            $this->info($this->option('apply') ? 'Applying resumable legacy media migration.' : 'Report only; files and references will not be changed.');
            $counts = $migration->run((bool) $this->option('apply'), function ($message, $warning) {
                $warning ? $this->warn($message) : $this->line($message);
            });
            $this->info(collect($counts)->map(fn ($value, $key) => $key.': '.$value)->implode('; '));

            return $counts['errors'] ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
