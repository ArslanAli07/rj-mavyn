<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Services\Pricing\PricingBackfill;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PricingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function legacy(): void
    {
        foreach (['currency_runtime', 'price_provenance', 'currency_prices', 'payment_currency_policies', 'pricing_operations'] as $table) {
            Schema::drop($table);
        }
        foreach (['products', 'product_variants', 'product_option_values', 'coupons', 'delivery_zones'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('price_currency'));
        }
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropColumn(['currency', 'pricing_revision']));
        Schema::table('checkout_attempts', fn (Blueprint $table) => $table->dropColumn(['review_quote', 'review_token_hash', 'review_expires_at']));
        app(PricingService::class)->forget();
    }

    public function test_upgrade_stops_for_provenance_and_retains_known_pkr_delivery_fees(): void
    {
        $this->legacy();
        DB::table('settings')->insert(['key' => 'store_currency', 'value' => 'USD', 'type' => 'text', 'group' => 'store']);
        $category = Category::create(['name' => 'Legacy']);
        $product = Product::create(['title' => 'Legacy jacket', 'category_id' => $category->id, 'price' => '150.35']);
        $variant = $product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.35', 'is_default' => true, 'is_active' => true]);
        DB::table('delivery_zones')->insert(['name' => 'Legacy zone', 'delivery_fee' => '300.50', 'is_active' => true]);
        $actor = Admin::create(['name' => 'Migration owner', 'email' => 'migration@example.test', 'password' => 'fixture-password', 'role' => 'super_admin', 'is_active' => true]);
        $before = $actor->getRawOriginal('password');
        (require database_path('migrations/2026_10_15_000001_create_currency_pricing.php'))->up();
        $this->assertDatabaseHas('currency_runtime', ['currency' => 'USD', 'ready' => false]);
        $this->assertDatabaseHas('price_provenance', ['source' => 'zone', 'currency' => 'PKR']);
        $this->assertDatabaseHas('price_provenance', ['source' => 'product', 'currency' => null]);
        $this->artisan('platform:pricing:preflight')->assertFailed();
        $this->artisan('platform:pricing:preflight', ['--resolve' => ['product=PKR', 'variant=PKR'], '--admin' => $actor->id,
            '--evidence' => 'Verified the original deployment price book and source invoices.', '--backfill' => true])->assertSuccessful();
        $this->assertDatabaseHas('currency_prices', ['kind' => 'variant', 'entity_id' => $variant->id, 'currency' => 'PKR', 'amount_minor' => 15035]);
        $this->assertDatabaseHas('currency_prices', ['kind' => 'zone', 'currency' => 'PKR', 'amount_minor' => 30050]);
        $this->assertDatabaseMissing('currency_prices', ['kind' => 'zone', 'currency' => 'USD']);
        $this->assertSame($before, $actor->fresh()->getRawOriginal('password'));
        $this->assertDatabaseHas('currency_runtime', ['currency' => 'USD', 'ready' => true]);
        DB::table('currency_prices')->where('kind', 'variant')->where('entity_id', $variant->id)->update(['amount_minor' => 15199]);
        app(PricingBackfill::class)->run();
        $this->assertDatabaseHas('currency_prices', ['kind' => 'variant', 'entity_id' => $variant->id, 'amount_minor' => 15199]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'provenance_resolved', 'actor_id' => $actor->id]);
    }

    public function test_automatic_rollback_is_rejected_without_dropping_prices(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be automatically rolled back');
        (require database_path('migrations/2026_10_15_000001_create_currency_pricing.php'))->down();
    }

    public function test_invalid_legacy_money_blocks_readiness_without_a_partial_import(): void
    {
        DB::table('delivery_zones')->insert(['name' => 'Unreviewed legacy fee', 'delivery_fee' => '-0.01', 'is_active' => true]);
        $report = app(PricingBackfill::class)->run();
        $this->assertTrue($report['amounts']['unresolved']);
        $this->assertDatabaseHas('currency_runtime', ['ready' => false]);
        $this->assertDatabaseCount('currency_prices', 0);
        DB::table('delivery_zones')->where('name', 'Unreviewed legacy fee')->update(['delivery_fee' => '0.00']);
        app(PricingBackfill::class)->run();
        $this->assertDatabaseHas('currency_runtime', ['ready' => true]);
        $this->assertDatabaseHas('currency_prices', ['kind' => 'zone', 'currency' => 'PKR', 'amount_minor' => 0]);
    }
}
