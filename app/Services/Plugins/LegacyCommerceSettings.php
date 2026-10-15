<?php

namespace App\Services\Plugins;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Core\Plugins\PluginSetting;

/** Explicit legacy aliases only. New plugins cannot claim arbitrary core keys. */
final class LegacyCommerceSettings
{
    private ?array $fields = null;

    private const OWNERS = ['mavyn.manual-payments', 'mavyn.local-delivery', 'mavyn.store-availability'];

    public function __construct(private InstalledPluginCatalog $catalogs) {}

    public function fields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }
        $this->fields = [];
        foreach ($this->catalogs->load()->all() as $id => $plugin) {
            if (! in_array($id, self::OWNERS, true)) {
                continue;
            }
            foreach ($plugin['manifest']['settings'] ?? [] as $key => $definition) {
                $this->fields[$key] = ['plugin' => $id, 'definition' => new PluginSetting($key, $definition)];
            }
        }

        return $this->fields;
    }

    public function owns(string $key): bool
    {
        return isset($this->fields()[$key]) && Schema::hasTable('plugin_settings');
    }

    public function read(string $key, mixed $default = null): mixed
    {
        $field = $this->fields()[$key];
        $row = DB::table('plugin_settings')->where('plugin_id', $field['plugin'])->where('key', $key)->sharedLock()->first();
        if (! $row) {
            return $default;
        }

        return $field['definition']->normalize(json_decode($row->value, true, 512, JSON_THROW_ON_ERROR));
    }

    public function write(string $key, mixed $value, ?Admin $actor = null): void
    {
        $field = $this->fields()[$key];
        $definition = $field['definition'];
        if ($actor) {
            abort_unless($actor->hasPermission('settings.manage'), 403);
        }
        if ($definition->definition['type'] === 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($value === '' && ($definition->definition['nullable'] ?? false)) {
            $value = null;
        }
        $value = $definition->normalize($value);
        DB::transaction(function () use ($field, $key, $value, $actor) {
            app(PricingService::class)->lock(true);
            DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
            if ($actor) {
                abort_unless(DB::table('plugin_states')->where('plugin_id', $field['plugin'])->where('active', true)->lockForUpdate()->first() !== null, 409, 'Plugin is disabled.');
            }
            DB::table('plugin_settings')->upsert([['plugin_id' => $field['plugin'], 'key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'secret' => false, 'created_at' => now(), 'updated_at' => now()]], ['plugin_id', 'key'], ['value', 'secret', 'updated_at']);
            Setting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (string) (int) $value : $value, 'type' => is_bool($value) ? 'boolean' : 'text', 'group' => 'commerce']);
            DB::table('extension_runtime')->where('id', 1)->increment('revision');
        });
        Cache::forget('all_settings');
        app(PluginRuntime::class)->forget();
    }

    public function schema(): array
    {
        $schema = [];
        foreach ($this->fields() as $key => $field) {
            $def = $field['definition']->definition;
            $type = $def['type'];
            $rules = ($def['nullable'] ?? false) ? ['nullable'] : ['required'];
            $rules = [...$rules, ...match ($type) {
                'boolean' => ['boolean'], 'time' => ['date_format:H:i'], 'decimal' => ['numeric', 'min:0', 'max:9999999999', 'decimal:0,2'], default => ['string', 'max:500']
            }];
            $schema[$key] = ['default' => $def['default'], 'type' => $type === 'boolean' ? 'boolean' : 'text', 'group' => 'commerce', 'rules' => $rules, 'plugin' => $field['plugin']];
        }

        return $schema;
    }
}
