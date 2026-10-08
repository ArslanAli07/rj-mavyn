<?php

namespace App\Services\Themes;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Services\Plugins\InstalledPluginCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mavyn\Core\Themes\ThemeCatalog;

final class ThemeManager
{
    public function __construct(private InstalledThemeCatalog $catalogs, private InstalledPluginCatalog $plugins, private ThemeSettings $settings) {}

    public function validate(string $id, ?array $enabled = null): void
    {
        $catalog = $this->catalogs->load();
        $this->catalogs->verify($id);
        app(ThemeAssets::class)->urls($catalog->chain($id));
        $enabled ??= Schema::hasTable('plugin_states') ? DB::table('plugin_states')->where('active', true)->sharedLock()->pluck('plugin_id')->all() : [];
        $versions = [];
        foreach ($enabled as $plugin) {
            $versions[$plugin] = $this->plugins->load()->get($plugin)['version'];
        }
        $catalog->validatePlugins($id, $versions);
        $this->settings->values($id);
    }

    public function guardPluginChange(array $enabled): void
    {
        if (! Schema::hasTable('theme_selection')) {
            return;
        }
        $active = DB::table('theme_selection')->where('id', 1)->lockForUpdate()->value('active_theme') ?? ThemeCatalog::DEFAULT;
        // A broken/missing active theme must not prevent recovery; otherwise honor its dependencies.
        $catalog = $this->catalogs->load();
        if (! isset($catalog->all()[$active])) {
            return;
        }
        $versions = [];
        foreach ($enabled as $id) {
            $versions[$id] = $this->plugins->load()->get($id)['version'];
        }
        $catalog->validatePlugins($active, $versions);
    }

    public function activate(string $id, ?Admin $actor = null): void
    {
        if ($actor) {
            abort_unless($actor->hasPermission('themes.manage'), 403);
        }
        try {
            DB::transaction(function () use ($id, $actor) {
                DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
                $current = DB::table('theme_selection')->where('id', 1)->lockForUpdate()->first();
                $this->validate($id);
                if ($current->active_theme === $id) {
                    return;
                }
                DB::table('theme_selection')->where('id', 1)->update(['active_theme' => $id, 'previous_theme' => $current->active_theme, 'updated_at' => now()]);
                DB::table('extension_runtime')->where('id', 1)->increment('revision');
                AdminAuditLog::create(['actor_id' => $actor?->id, 'actor_name' => $actor?->name ?? 'Console', 'route' => request()->route()?->getName() ?? 'console', 'action' => 'activated', 'entity_type' => 'themes', 'entity_id' => $id, 'changes' => ['active_theme' => ['before' => $current->active_theme, 'after' => $id]]]);
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['theme' => $exception->getMessage()]);
        } finally {
            app(ThemeRuntime::class)->forget();
        }
    }
}
