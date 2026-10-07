<?php

namespace App\Jobs\Media;

use App\Services\Media\MediaLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $taskId) {}

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(MediaLifecycleService $lifecycle): void
    {
        $lifecycle->cleanup($this->taskId);
    }
}
