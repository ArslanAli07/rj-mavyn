<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentService;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginRuntime;
use App\Services\Themes\InstalledThemeCatalog;
use App\Services\Themes\ThemeManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Contracts\Payments\GatewayProvider;
use Mavyn\Core\Themes\ThemeCatalog;
use Throwable;

class ValidatePluginsCommand extends Command
{
    protected $signature = 'platform:extensions:validate';

    protected $description = 'Validate the built catalog and current enabled plugin combination';

    public function handle(InstalledPluginCatalog $catalogs, PluginRuntime $runtime): int
    {
        try {
            $catalog = $catalogs->load();
            if (! is_file(config('extensions.catalog'))) {
                throw new \RuntimeException('Build the installed catalog first.');
            }
            if (Schema::hasTable('plugin_states')) {
                $runtime->refresh();
            }
            $themes = app(InstalledThemeCatalog::class);
            $themes->load();
            $id = Schema::hasTable('theme_selection') ? DB::table('theme_selection')->where('id', 1)->value('active_theme') : ThemeCatalog::DEFAULT;
            app(ThemeManager::class)->validate($id);
            $this->line('Active theme is valid: '.$id);
            if (Schema::hasTable('gateway_payment_attempts')) {
                foreach (DB::table('gateway_payment_attempts')->where(function ($query) {
                    $query->whereIn('status', PaymentService::RECONCILE)
                        ->orWhereExists(function ($refunds) {
                            $refunds->selectRaw('1')->from('gateway_refunds')->whereColumn('attempt_id', 'gateway_payment_attempts.id')->whereIn('status', ['created', 'pending']);
                        });
                })->get(['plugin_id', 'provider_class']) as $payment) {
                    $catalog->get($payment->plugin_id);
                    if (! is_subclass_of($payment->provider_class, GatewayProvider::class)) {
                        throw new \RuntimeException('Retain gateway code until pending payment/refund work is drained.');
                    }
                }
            }
            $this->info('Installed catalog and active plugins are valid.');
            foreach ($catalog->all() as $id => $package) {
                $active = Schema::hasTable('plugin_states') && DB::table('plugin_states')->where('plugin_id', $id)->where('active', true)->exists();
                $this->line($id.' '.$package['version'].' '.($active ? 'active' : 'disabled'));
                foreach ($package['manifest']['migrations'] ?? [] as $migration) {
                    $this->line('  Deployment migration: '.$migration);
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
