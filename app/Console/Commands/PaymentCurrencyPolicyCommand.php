<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mavyn\Contracts\Pricing\PriceBook;

class PaymentCurrencyPolicyCommand extends Command
{
    protected $signature = 'platform:pricing:payment-policy {provider} {currencies*} {--admin=} {--evidence=} {--revision=}';

    protected $description = 'Record verified currency eligibility for a legacy manual payment provider';

    public function handle(PriceBook $prices): int
    {
        $actor = Admin::find($this->option('admin'));
        if (! $actor || ! $actor->is_active || ! $actor->hasPermission('currencies.manage') || $this->option('revision') === null) {
            $this->error('Supply an authorized --admin and the current --revision.');

            return self::FAILURE;
        }
        auth('admin')->setUser($actor);
        try {
            $result = $prices->paymentPolicy($this->argument('provider'), $this->argument('currencies'), (string) $this->option('evidence'), (int) $this->option('revision'), (string) Str::uuid());
            $this->info('Policy recorded at revision '.$result->revision);
        } finally {
            auth('admin')->forgetUser();
        }

        return self::SUCCESS;
    }
}
