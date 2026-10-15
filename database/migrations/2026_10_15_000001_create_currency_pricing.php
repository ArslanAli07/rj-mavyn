<?php

use App\Services\Pricing\PricingBackfill;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Core\Currencies;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_runtime', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('currency', 3);
            $table->json('enabled');
            $table->unsignedBigInteger('revision')->default(1);
            $table->boolean('ready')->default(false);
        });
        Schema::create('price_provenance', function (Blueprint $table) {
            $table->string('source', 20)->primary();
            $table->char('currency', 3)->nullable();
            $table->text('evidence');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
        });
        Schema::create('currency_prices', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->unsignedBigInteger('entity_id');
            $table->char('currency', 3);
            $table->bigInteger('amount_minor')->nullable();
            $table->bigInteger('sale_minor')->nullable();
            $table->bigInteger('minimum_minor')->nullable();
            $table->bigInteger('maximum_minor')->nullable();
            $table->unique(['kind', 'entity_id', 'currency']);
            $table->index(['kind', 'currency', 'amount_minor']);
        });
        Schema::create('payment_currency_policies', function (Blueprint $table) {
            $table->string('provider_key')->primary();
            $table->json('currencies');
            $table->text('evidence');
            $table->unsignedBigInteger('actor_id');
        });
        Schema::create('pricing_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('actor_id');
            $table->string('fingerprint', 64);
            $table->unsignedBigInteger('revision');
        });
        foreach (['products', 'product_variants', 'product_option_values', 'coupons', 'delivery_zones'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->char('price_currency', 3)->nullable());
        }
        Schema::table('orders', fn (Blueprint $table) => $table->string('payment_method', 100)->default('cod')->change());
        Schema::table('cart_items', function (Blueprint $table) {
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('pricing_revision')->nullable();
        });
        Schema::table('checkout_attempts', function (Blueprint $table) {
            $table->json('review_quote')->nullable();
            $table->string('review_token_hash', 64)->nullable();
            $table->timestamp('review_expires_at')->nullable();
        });
        $currency = DB::table('settings')->where('key', 'store_currency')->value('value') ?? config('site.currency', 'PKR');
        // An unsupported legacy setting blocks preflight; never silently substitute PKR.
        $valid = isset(Currencies::SYMBOLS[$currency]);
        DB::table('currency_runtime')->insert(['id' => 1, 'currency' => $currency, 'enabled' => json_encode($valid ? [$currency] : []), 'revision' => 1, 'ready' => false]);
        foreach (PricingService::TABLES as $kind => $name) {
            $empty = ! DB::table($name)->exists();
            $known = $kind === 'zone' ? 'PKR' : ($empty && $valid ? $currency : null);
            DB::table('price_provenance')->insert(['source' => $kind, 'currency' => $known,
                'evidence' => $kind === 'zone' ? 'Legacy EloquentDeliveryZoneStore explicitly denominates fees in PKR.' : ($empty ? 'Empty source at migration; no legacy amounts to infer.' : 'Operator evidence required for populated legacy source.'),
                'resolved_at' => $known ? now() : null]);
        }
        app(PricingBackfill::class)->run();
    }

    public function down(): void
    {
        throw new RuntimeException('Currency pricing cannot be automatically rolled back. Restore a reviewed pre-upgrade backup or deploy a forward fix; price records and historical currency must be retained.');
    }
};
