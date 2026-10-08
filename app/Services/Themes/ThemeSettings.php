<?php

namespace App\Services\Themes;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ThemeSettings
{
    public function __construct(private InstalledThemeCatalog $catalogs) {}

    public function normalize(string $id, array $values): array
    {
        $fields = $this->catalogs->load()->settings($id);
        $result = [];
        foreach ($values as $key => $value) {
            try {
                if (! isset($fields[$key])) {
                    throw new InvalidArgumentException('Unknown theme setting.');
                }
                $result[$key] = $fields[$key]->normalize($value);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['settings.'.$key => $exception->getMessage()]);
            }
        }

        return $result;
    }

    public function values(string $id): array
    {
        $catalog = $this->catalogs->load();
        $values = [];
        foreach (array_reverse($catalog->chain($id)) as $owner) {
            foreach ($catalog->get($owner)['manifest']['settings'] ?? [] as $key => $definition) {
                $values[$key] = $definition['default'];
            }
            if (Schema::hasTable('theme_settings')) {
                $fields = $catalog->settings($id);
                foreach (DB::table('theme_settings')->where('theme_id', $owner)->sharedLock()->get() as $row) {
                    if (isset($fields[$row->key])) {
                        $values[$row->key] = $fields[$row->key]->normalize(json_decode($row->value, true, 512, JSON_THROW_ON_ERROR));
                    }
                }
            }
        }

        return $values;
    }

    public function save(string $id, array $values, ?Admin $actor = null): void
    {
        if ($actor) {
            abort_unless($actor->hasPermission('themes.manage'), 403);
        }
        $values = $this->normalize($id, $values);
        DB::transaction(function () use ($id, $values, $actor) {
            DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
            foreach ($values as $key => $value) {
                DB::table('theme_settings')->upsert([['theme_id' => $id, 'key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]], ['theme_id', 'key'], ['value', 'updated_at']);
            }
            DB::table('extension_runtime')->where('id', 1)->increment('revision');
            AdminAuditLog::create(['actor_id' => $actor?->id, 'actor_name' => $actor?->name ?? 'Console', 'route' => request()->route()?->getName() ?? 'console', 'action' => 'configured', 'entity_type' => 'themes', 'entity_id' => $id, 'changes' => ['keys' => array_keys($values)]]);
        });
        app(ThemeRuntime::class)->forget();
    }
}
