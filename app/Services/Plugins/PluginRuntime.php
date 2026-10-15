<?php

namespace App\Services\Plugins;

use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\ProductDisplay;
use Mavyn\Core\Plugins\HookBus;
use Mavyn\Core\Plugins\ProviderRegistry;
use Mavyn\Core\Plugins\RuntimeContext;
use RuntimeException;

final class PluginRuntime
{
    private ?int $revision = null;

    private HookBus $hooks;

    private ProviderRegistry $providers;

    private array $contexts = [];

    public function __construct(private InstalledPluginCatalog $catalogs, private PluginSettings $settings) {}

    public function refresh(): void
    {
        $hasState = Schema::hasTable('extension_runtime');
        DB::transaction(function () use ($hasState) {
            app(PricingService::class)->lock();
            $revision = $hasState ? (int) DB::table('extension_runtime')->where('id', 1)->sharedLock()->value('revision') : 0;
            if ($this->revision === $revision) {
                return;
            }
            $catalog = $this->catalogs->load();
            $active = Schema::hasTable('plugin_states') ? DB::table('plugin_states')->where('active', true)->sharedLock()->pluck('plugin_id')->all() : [];
            $order = $catalog->order($active);
            $hooks = new HookBus;
            $hooks->define('filter', 'core.catalog.product.display', ProductDisplay::class);
            $hooks->define('action', 'core.catalog.product.displayed', ProductDisplay::class);
            $providers = new ProviderRegistry(fn ($class) => app()->make($class));
            $contexts = [];
            foreach ($order as $rank => $id) {
                $manifest = $catalog->get($id)['manifest'];
                foreach ($manifest['migrations'] ?? [] as $migration) {
                    if (! DB::table('migrations')->where('migration', pathinfo($migration, PATHINFO_FILENAME))->exists()) {
                        throw new RuntimeException('Apply deployment migrations before enabling plugin: '.$id);
                    }
                }
                $context = new RuntimeContext($id, $manifest, $hooks, $providers, fn ($plugin, $key) => $this->settings->get($plugin, $key), $rank);
                $plugin = app()->make($manifest['provider']);
                if (! $plugin instanceof Plugin) {
                    throw new RuntimeException('Invalid plugin entry point: '.$id);
                }
                $plugin->register($context);
                $contexts[$id] = $context;
            }
            $hooks->seal();
            $providers->seal();
            $this->hooks = $hooks;
            $this->providers = $providers;
            $this->contexts = $contexts;
            $this->revision = $revision;
        });
    }

    public function hooks(): HookBus
    {
        $this->refresh();

        return $this->hooks;
    }

    public function providers(): ProviderRegistry
    {
        $this->refresh();

        return $this->providers;
    }

    public function active(string $id): bool
    {
        $this->refresh();

        return isset($this->contexts[$id]);
    }

    public function context(string $id): RuntimeContext
    {
        $this->refresh();

        return $this->contexts[$id] ?? throw new RuntimeException('Plugin is disabled.');
    }

    public function candidateContext(string $id): RuntimeContext
    {
        $hooks = new HookBus;
        $hooks->define('filter', 'core.catalog.product.display', ProductDisplay::class);
        $hooks->define('action', 'core.catalog.product.displayed', ProductDisplay::class);
        $providers = new ProviderRegistry(fn ($class) => app()->make($class));

        return new RuntimeContext($id, $this->catalogs->load()->get($id)['manifest'], $hooks, $providers, fn ($plugin, $key) => $this->settings->get($plugin, $key), 0);
    }

    public function forget(): void
    {
        $this->revision = null;
    }
}
