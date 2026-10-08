<?php

namespace App\Services\Themes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mavyn\Core\Themes\ThemeCatalog;
use Throwable;

/** Scoped state; presentation never changes commerce authorization/actions. */
final class ThemeRuntime
{
    private ?array $state = null;

    private ?array $preview = null;

    public function __construct(private InstalledThemeCatalog $catalogs, private ThemeSettings $settings, private ThemeManager $manager) {}

    public function preview(string $id, array $draft): void
    {
        $this->manager->validate($id);
        $this->preview = ['id' => $id, 'draft' => $this->settings->normalize($id, $draft)];
        $this->forget();
    }

    public function reset(): void
    {
        $this->preview = null;
        $this->state = null;
    }

    public function forget(): void
    {
        $this->state = null;
    }

    public function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        return $this->state = DB::transaction(function () {
            if (Schema::hasTable('extension_runtime')) {
                DB::table('extension_runtime')->where('id', 1)->sharedLock()->first();
            }
            $id = $this->preview['id'] ?? (Schema::hasTable('theme_selection') ? DB::table('theme_selection')->where('id', 1)->sharedLock()->value('active_theme') : null) ?? ThemeCatalog::DEFAULT;
            $fallback = false;
            try {
                $this->manager->validate($id);
            } catch (Throwable $exception) {
                if ($this->preview || $id === ThemeCatalog::DEFAULT) {
                    throw $exception;
                }
                Log::warning('Active theme unavailable; using default fallback.', ['theme' => $id, 'reason' => $exception->getMessage()]);
                $id = ThemeCatalog::DEFAULT;
                $fallback = true;
                $this->manager->validate($id);
            }
            $chain = $this->catalogs->load()->chain($id);

            return ['id' => $id, 'chain' => $chain, 'settings' => array_replace($this->settings->values($id), $this->preview['draft'] ?? []), 'assets' => app(ThemeAssets::class)->urls($chain), 'preview' => $this->preview !== null, 'fallback' => $fallback];
        });
    }

    public function template(string $name): string
    {
        $catalog = $this->catalogs->load();
        foreach ($this->state()['chain'] as $id) {
            $package = $catalog->get($id);
            if ($path = $package['manifest']['templates'][$name] ?? null) {
                return $this->catalogs->resource($package, $path);
            }
        }
        throw new \RuntimeException('Theme template not provided: '.$name);
    }
}
