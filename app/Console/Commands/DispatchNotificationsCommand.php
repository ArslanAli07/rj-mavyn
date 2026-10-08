<?php

namespace App\Console\Commands;

use App\Models\NotificationOutbox;
use App\Services\NotificationOutboxService;
use Illuminate\Console\Command;

class DispatchNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch {--limit=100 : Maximum entries to queue} {--report : Report only}';

    protected $description = 'Queue due order notifications and recover interrupted dispatches';

    public function handle(NotificationOutboxService $outbox): int
    {
        $this->info('Pending: '.NotificationOutbox::whereNull('sent_at')->count().'; sent: '.NotificationOutbox::whereNotNull('sent_at')->count().'.');
        if ($this->option('report')) {
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');

            return self::FAILURE;
        }
        $counts = $outbox->dispatchPending($limit);
        $this->info('Queued: '.$counts['queued'].'; dispatch failures: '.$counts['failed'].'.');

        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
