<?php

namespace App\Services\Plugins;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Services\Themes\ThemeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mavyn\Contracts\Plugins\Plugin;
use Mavyn\Contracts\Plugins\PluginLifecycle;
use RuntimeException;

final class PluginManager
{
    public function __construct(private InstalledPluginCatalog $catalogs, private PluginRuntime $runtime) {}

    public function change(string $id, bool $active, ?Admin $actor = null): void
    {
        if ($actor) {
            abort_unless($actor->hasPermission('extensions.manage'), 403);
        }
        try {
            DB::transaction(function () use ($id, $active, $actor) {
                DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
                $catalog = $this->catalogs->load();
                $package = $catalog->get($id);
                $current = DB::table('plugin_states')->where('plugin_id', $id)->lockForUpdate()->first();
                if ((bool) ($current->active ?? false) === $active) {
                    return;
                }
                $enabled = DB::table('plugin_states')->where('active', true)->lockForUpdate()->pluck('plugin_id')->all();
                $proposed = $active ? [...$enabled, $id] : array_values(array_diff($enabled, [$id]));
                $catalog->order($proposed);
                app(ThemeManager::class)->guardPluginChange($proposed);
                if ($active) {
                    foreach ($package['manifest']['migrations'] ?? [] as $migration) {
                        if (! DB::table('migrations')->where('migration', pathinfo($migration, PATHINFO_FILENAME))->exists()) {
                            throw new InvalidArgumentException('Apply deployment migrations before activation.');
                        }
                    }
                }
                $plugin = app()->make($package['manifest']['provider']);
                if (! $plugin instanceof Plugin) {
                    throw new RuntimeException('Invalid plugin entry point.');
                }
                if ($plugin instanceof PluginLifecycle) {
                    $context = $this->runtime->candidateContext($id);
                    if ($active) {
                        $plugin->activate($context);
                    } else {
                        $plugin->deactivate($context);
                    }
                }
                DB::table('plugin_states')->upsert([['plugin_id' => $id, 'active' => $active, 'version' => $package['version'], 'created_at' => $current?->created_at ?? now(), 'updated_at' => now()]], ['plugin_id'], ['active', 'version', 'updated_at']);
                DB::table('extension_runtime')->where('id', 1)->increment('revision');
                $this->runtime->forget();
                $this->runtime->refresh(); // Invalid contributions roll back activation.
                AdminAuditLog::create(['actor_id' => $actor?->id, 'actor_name' => $actor?->name ?? 'Console', 'route' => request()->route()?->getName() ?? 'console', 'action' => $active ? 'activated' : 'deactivated', 'entity_type' => 'plugins', 'entity_id' => $id, 'changes' => ['active' => ['before' => (bool) ($current->active ?? false), 'after' => $active], 'version' => $package['version']]]);
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['plugin' => $exception->getMessage()]);
        } finally {
            $this->runtime->forget();
        }
    }
}
