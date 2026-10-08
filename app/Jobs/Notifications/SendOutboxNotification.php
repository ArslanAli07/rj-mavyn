<?php

namespace App\Jobs\Notifications;

use App\Services\NotificationOutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOutboxNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $outboxId) {}

    public function handle(NotificationOutboxService $outbox): void
    {
        $outbox->deliver($this->outboxId);
    }
}
