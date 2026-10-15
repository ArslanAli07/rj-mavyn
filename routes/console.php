<?php

use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('media:reconcile --apply')->hourly()->withoutOverlapping();

Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('platform:payments:reconcile {--limit=50}', function (PaymentService $payments) {
    if (! Schema::hasTable('gateway_payment_attempts')) {
        $this->error('Apply gateway payment migrations first.');

        return 1;
    }
    $this->line(json_encode($payments->processDue((int) $this->option('limit')), JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Process durable gateway initiation, reconciliation and refund operations');

Schedule::command('platform:payments:reconcile')->everyMinute()->withoutOverlapping();
