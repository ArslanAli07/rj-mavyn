<?php

namespace App\Services\Plugins;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mavyn\Core\Plugins\PluginSetting;

final class PluginSettings
{
    public function __construct(private InstalledPluginCatalog $catalogs) {}

    public function definitions(string $id): array
    {
        $result = [];
        foreach ($this->catalogs->load()->get($id)['manifest']['settings'] ?? [] as $key => $definition) {
            $result[$key] = new PluginSetting($key, $definition);
        }

        return $result;
    }

    public function get(string $id, string $key): mixed
    {
        $definition = $this->definitions($id)[$key] ?? throw new InvalidArgumentException('Unknown plugin setting.');
        $row = DB::table('plugin_settings')->where('plugin_id', $id)->where('key', $key)->sharedLock()->first();
        if (! $row) {
            return $definition->normalize($definition->definition['default']);
        }
        $value = $row->secret ? Crypt::decryptString($row->value) : $row->value;

        return $definition->normalize(json_decode($value, true, 512, JSON_THROW_ON_ERROR));
    }

    public function publicValues(string $id): array
    {
        $values = [];
        foreach ($this->definitions($id) as $key => $definition) {
            $values[$key] = $definition->secret() ? null : $this->get($id, $key);
        }

        return $values;
    }

    public function save(string $id, array $values, Admin $actor): void
    {
        abort_unless($actor->hasPermission('extensions.manage'), 403);
        DB::transaction(function () use ($id, $values, $actor) {
            app(PricingService::class)->lock(true);
            DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
            abort_unless(DB::table('plugin_states')->where('plugin_id', $id)->where('active', true)->lockForUpdate()->first(['plugin_id']) !== null, 409, 'Plugin is disabled.');
            $definitions = $this->definitions($id);
            $normalized = [];
            $errors = [];
            foreach ($values as $key => $value) {
                $definition = $definitions[$key] ?? null;
                if (! $definition) {
                    $errors['settings.'.$key] = 'Unknown setting.';

                    continue;
                }
                abort_unless($actor->hasPermission($definition->definition['permission'] ?? 'extensions.manage'), 403);
                // Empty password fields mean preserve, not erase. Null is an explicit nullable clear through API.
                if ($definition->secret() && ($value === '' || ($value === null && ! request()->isJson()))) {
                    continue;
                }
                try {
                    $normalized[$key] = $definition->normalize($value);
                } catch (InvalidArgumentException $exception) {
                    $errors['settings.'.$key] = $exception->getMessage();
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            foreach ($normalized as $key => $value) {
                $secret = $definitions[$key]->secret();
                $json = json_encode($value, JSON_THROW_ON_ERROR);
                DB::table('plugin_settings')->upsert([['plugin_id' => $id, 'key' => $key, 'value' => $secret ? Crypt::encryptString($json) : $json, 'secret' => $secret, 'created_at' => now(), 'updated_at' => now()]], ['plugin_id', 'key'], ['value', 'secret', 'updated_at']);
            }
            if ($normalized) {
                DB::table('extension_runtime')->where('id', 1)->increment('revision');
                AdminAuditLog::create(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'route' => request()->route()?->getName() ?? 'console', 'action' => 'settings_updated', 'entity_type' => 'plugins', 'entity_id' => $id, 'changes' => ['keys' => array_keys($normalized)]]);
            }
        });
        app(PluginRuntime::class)->forget();
    }
}
