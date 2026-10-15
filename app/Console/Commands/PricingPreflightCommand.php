<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Services\Pricing\PricingBackfill;
use App\Services\Pricing\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Mavyn\Core\Currencies;

class PricingPreflightCommand extends Command
{
    protected $signature = 'platform:pricing:preflight {--resolve=* : Explicit source=ISO provenance resolution} {--evidence= : Recorded evidence for these sources} {--admin= : Authorizing administrator ID} {--backfill : Resume proven source backfill}';

    protected $description = 'Report price provenance, record operator resolutions, and resume idempotent currency backfill';

    public function handle(PricingService $pricing, PricingBackfill $backfill): int
    {
        if ($this->option('resolve')) {
            $actor = Admin::find($this->option('admin'));
            if (! $actor || ! $actor->is_active || ! $actor->hasPermission('currencies.manage') || strlen(trim((string) $this->option('evidence'))) < 10) {
                $this->error('Resolution requires an active authorized --admin and meaningful --evidence.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($pricing, $actor) {
                $pricing->lock(true);
                $resolutions = [];
                foreach ($this->option('resolve') as $resolution) {
                    [$source, $currency] = array_pad(explode('=', $resolution, 2), 2, null);
                    $row = DB::table('price_provenance')->where('source', $source)->first();
                    if (! $row || ! isset(Currencies::SYMBOLS[$currency]) || ($row->currency !== null && $row->currency !== $currency)) {
                        throw new \InvalidArgumentException('Unknown source/currency or attempt to overwrite established denomination: '.$resolution);
                    }
                    $resolutions[$source] = $currency;
                }
                foreach ($resolutions as $source => $currency) {
                    DB::table('price_provenance')->where('source', $source)->update(['currency' => $currency, 'evidence' => $this->option('evidence'), 'actor_id' => $actor->id, 'resolved_at' => now()]);
                }
                AdminAuditLog::create(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'route' => 'console.platform:pricing:preflight',
                    'action' => 'provenance_resolved', 'entity_type' => 'pricing', 'entity_id' => 'sources',
                    'changes' => ['sources' => $resolutions, 'evidence' => $this->option('evidence')]]);
                $pricing->bump();
            });
        }
        if ($this->option('backfill')) {
            $backfill->run();
        }
        $this->table(['Source', 'Currency', 'Evidence'], DB::table('price_provenance')->get()->map(fn ($row) => [$row->source, $row->currency ?? 'UNRESOLVED', $row->evidence])->all());
        foreach ([...$backfill->historicalIssues(), ...$backfill->monetaryIssues()] as $issue) {
            $this->error($issue);
        }
        $context = $pricing->context();
        $this->info('Selected: '.$context->currency.'; revision: '.$context->revision.'; selling ready: '.($context->ready ? 'yes' : 'NO'));
        $this->line('Legacy payment providers without CurrencyPaymentProvider need an explicit policy; no new currency support is inferred.');

        return $context->ready ? self::SUCCESS : self::FAILURE;
    }
}
