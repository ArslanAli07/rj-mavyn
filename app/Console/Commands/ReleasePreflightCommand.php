<?php

namespace App\Console\Commands;

use App\Services\Deployment\ReleasePreflight;
use Illuminate\Console\Command;
use Throwable;

final class ReleasePreflightCommand extends Command
{
    protected $signature = 'platform:release:check {--manifest=} {--backup=} {--record}';

    protected $description = 'Verify release, isolated client runtime, migrations and a restored backup; optionally record the result';

    public function handle(ReleasePreflight $preflight): int
    {
        $report = $preflight->check($this->option('manifest') ?: config('platform.manifest'), $this->option('backup') ?: '');
        if ($this->option('record')) {
            try {
                $preflight->record($report);
            } catch (Throwable) {
                $report['outcome'] = 'failed';
                $report['checks']['record'] = false;
            }
        }
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));

        return $report['outcome'] === 'verified' ? self::SUCCESS : self::FAILURE;
    }
}
