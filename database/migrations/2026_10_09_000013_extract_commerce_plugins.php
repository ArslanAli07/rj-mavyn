<?php

use App\Services\Plugins\InstalledPluginCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Core\Plugins\PluginSetting;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the previously always-installed behavior; do not migrate/drop legacy tables or identifiers.
        $defaults = ['easypaisa_account' => 'site.payment.easypaisa.account', 'easypaisa_name' => 'site.payment.easypaisa.name', 'jazzcash_account' => 'site.payment.jazzcash.account', 'jazzcash_name' => 'site.payment.jazzcash.name', 'bank_name' => 'site.payment.bank.bank_name', 'bank_title' => 'site.payment.bank.title', 'bank_account' => 'site.payment.bank.account', 'bank_iban' => 'site.payment.bank.iban'];
        $ids = ['mavyn.local-delivery', 'mavyn.manual-payments', 'mavyn.food-products', 'mavyn.store-availability'];
        $catalog = app(InstalledPluginCatalog::class)->load();
        // Validate every imported value before MySQL performs nontransactional schema changes.
        $settings = [];
        foreach ($ids as $id) {
            $package = $catalog->get($id);
            foreach ($package['manifest']['settings'] ?? [] as $key => $definition) {
                $legacy = DB::table('settings')->where('key', $key)->first();
                $value = $legacy ? $legacy->value : (isset($defaults[$key]) ? config($defaults[$key], $definition['default']) : $definition['default']);
                if ($definition['type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
                if ($definition['type'] === 'time' && is_string($value) && strlen($value) === 8) {
                    $value = substr($value, 0, 5);
                }
                if (($value === null || $value === '') && ($definition['nullable'] ?? false)) {
                    $value = null;
                } elseif ($value === null && $definition['type'] === 'text') {
                    $value = '';
                }
                $value = (new PluginSetting($key, $definition))->normalize($value);
                $settings[] = ['plugin_id' => $id, 'key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'secret' => false, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->json('payment_instructions_snapshot')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('attributes_snapshot')->nullable();
        });
        DB::transaction(function () use ($catalog, $ids, $settings) {
            DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
            foreach ($settings as $setting) {
                DB::table('plugin_settings')->insertOrIgnore($setting);
            }
            foreach ($ids as $id) {
                DB::table('plugin_states')->insertOrIgnore(['plugin_id' => $id, 'active' => true, 'version' => $catalog->get($id)['version'], 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('extension_runtime')->where('id', 1)->increment('revision');
        });
    }

    public function down(): void
    {
        // Configuration and existing feature states/data remain retained on schema rollback.
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('attributes_snapshot');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_instructions_snapshot');
        });
    }
};
