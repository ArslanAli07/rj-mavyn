<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\CheckoutAttemptService;
use App\Services\PaymentMethodService;
use App\Services\Plugins\PluginRoutes;
use App\Services\Plugins\PluginRuntime;
use App\Services\Pricing\PricingBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Mavyn\Contracts\Pricing\PriceBook;
use Tests\Fixtures\Pricing\LegacyPayment;
use Tests\Fixtures\Pricing\PricingPayment;
use Tests\Fixtures\Pricing\PricingPlugin;
use Tests\TestCase;

class MultiCurrencyPricingTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogPath;

    private string $themesPath;

    private Admin $actor;

    private Product $product;

    private ProductVariant $variant;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogPath = tempnam(sys_get_temp_dir(), 'mavyn-pricing-plugins-');
        $this->themesPath = tempnam(sys_get_temp_dir(), 'mavyn-pricing-themes-');
        file_put_contents($this->catalogPath, json_encode(['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => [[
            'package' => 'test/pricing', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Pricing', 'manifest' => [
                'kind' => 'plugin', 'id' => 'test.pricing', 'name' => 'Pricing fixture', 'provider' => PricingPlugin::class,
                'capabilities' => ['payment.provider', 'fulfillment.provider'], 'permissions' => ['test.pricing.manage' => 'Manage prices'], 'routes' => ['admin' => 'routes/admin.php'],
            ],
        ]]], JSON_THROW_ON_ERROR));
        $theme = json_decode(file_get_contents(base_path('resources/themes/default/composer.json')), true);
        file_put_contents($this->themesPath, json_encode(['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => [[
            'package' => $theme['name'], 'version' => '0.1.0', 'path' => 'resources/themes/default', 'manifest' => $theme['extra']['mavyn'],
        ]]], JSON_THROW_ON_ERROR));
        config(['extensions.catalog' => $this->catalogPath, 'themes.catalog' => $this->themesPath]);
        DB::table('plugin_states')->insert(['plugin_id' => 'test.pricing', 'version' => '1.0.0', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('extension_runtime')->where('id', 1)->increment('revision');
        app(PluginRuntime::class)->forget();
        app(PluginRoutes::class)->register();
        Route::getRoutes()->refreshNameLookups();
        $this->actor = Admin::create(['name' => 'Pricing owner', 'email' => 'pricing@example.test', 'password' => 'fixture-password-only', 'role' => 'super_admin', 'is_active' => true]);
        $category = Category::create(['name' => 'Jackets', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Leather jacket', 'price' => '150.00', 'is_active' => true, 'in_stock' => true]);
        $this->variant = $this->product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
        $this->zone = DeliveryZone::create(['name' => 'Fixture zone', 'delivery_fee' => '5.00', 'is_active' => true]);
        $this->actingAs($this->actor, 'admin');
        auth()->shouldUse('web');
    }

    protected function tearDown(): void
    {
        unlink($this->catalogPath);
        unlink($this->themesPath);
        parent::tearDown();
    }

    private function prices(): PriceBook
    {
        return app(PriceBook::class);
    }

    private function enable(): void
    {
        $this->prices()->enable(['PKR', 'USD', 'EUR', 'GBP'], $this->prices()->context()->revision, (string) Str::uuid());
    }

    private function savePrice(string $currency, int $minor = 15000): void
    {
        $this->prices()->save($currency, [
            ['kind' => 'variant', 'entity_id' => $this->variant->id, 'amount_minor' => $minor],
            ['kind' => 'zone', 'entity_id' => $this->zone->id, 'amount_minor' => 500],
        ], $this->prices()->context()->revision, (string) Str::uuid());
    }

    private function select(string $currency): void
    {
        $this->prices()->select($currency, $this->prices()->context()->revision, (string) Str::uuid());
    }

    private function checkout(): array
    {
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])->assertOk();

        return [
            'idempotency_key' => app(CheckoutAttemptService::class)->issue(),
            'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '03001234567',
            'country' => 'PK', 'city' => 'City', 'address_line' => 'Fixture street', 'shipping_option' => 'test.pricing.delivery:standard', 'payment_method' => 'fixture_manual',
        ];
    }

    private function review(array $input): array
    {
        $response = $this->postJson(route('checkout.quote'), $input)->assertOk();

        return [...$input, 'review_token' => $response->json('review_token')];
    }

    public function test_independent_currency_prices_flow_through_catalog_cart_quote_and_order(): void
    {
        $this->enable();
        foreach (['USD' => 15000, 'EUR' => 14500, 'GBP' => 12500] as $code => $minor) {
            $this->savePrice($code, $minor);
            $this->select($code);
            $this->get('/shop?min_price='.($minor / 100).'&max_price='.($minor / 100))->assertOk()->assertSee('Leather jacket');
            $input = $this->review($this->checkout());
            $response = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk();
            $this->assertDatabaseHas('orders', ['id' => $response->json('order_id'), 'currency' => $code, 'subtotal' => $minor / 100]);
        }
        $this->assertSame(15000, $this->prices()->read('variant', [$this->variant->id], 'USD')[$this->variant->id]['amount_minor']);
    }

    public function test_equal_numeric_prices_do_not_authorize_a_currency_switch_between_review_and_placement(): void
    {
        $this->enable();
        $this->savePrice('USD');
        $this->savePrice('EUR');
        $this->select('USD');
        $input = $this->review($this->checkout());
        $this->select('EUR');
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $fresh = $this->review($input);
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($fresh))->assertOk();
    }

    public function test_shipping_and_tax_changes_require_a_new_review_without_order_side_effects(): void
    {
        $input = $this->review($this->checkout());
        $this->zone->update(['delivery_fee' => '8.00']);
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertUnprocessable();
        $input = $this->review($input);
        Setting::set('store_tax_rate', '10.00');
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $input = $this->review($input);
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk();
        $this->assertDatabaseHas('orders', ['grand_total' => '173.00', 'tax_amount' => '15.00', 'delivery_fee' => '8.00']);
    }

    public function test_coupon_rule_changes_invalidate_a_quote_with_the_same_coupon_code(): void
    {
        $coupon = Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => '10.00', 'minimum_order' => 0, 'is_active' => true]);
        $input = $this->checkout();
        session(['coupon_code' => 'SAVE']);
        $input = $this->review($input);
        $coupon->update(['amount' => '20.00']);
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
    }

    public function test_committed_retry_survives_expiry_switch_and_plugin_removal(): void
    {
        $this->enable();
        $this->savePrice('USD');
        $input = $this->review($this->checkout());
        $first = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk();
        $this->select('USD');
        DB::table('plugin_states')->where('plugin_id', 'test.pricing')->update(['active' => false]);
        DB::table('extension_runtime')->where('id', 1)->increment('revision');
        $this->travel(20)->minutes();
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()->assertJsonPath('order_id', $first->json('order_id'))->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['id' => $first->json('order_id'), 'currency' => 'PKR']);
    }

    public function test_token_tampering_and_expiry_fail_closed(): void
    {
        $input = $this->review($this->checkout());
        $this->postJson(route('checkout.place-order'), $this->withHoneypot([...$input, 'review_token' => 'tampered']))->assertUnprocessable();
        $this->travel(16)->minutes();
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_prices_are_unavailable_and_zero_is_explicit(): void
    {
        $this->enable();
        $this->select('USD');
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])->assertUnprocessable();
        $this->savePrice('USD', 0);
        $input = $this->review($this->checkout());
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk();
        $this->assertDatabaseHas('orders', ['currency' => 'USD', 'subtotal' => '0.00', 'grand_total' => '5.00']);
    }

    public function test_public_plugin_editor_is_reachable_and_atomic_with_stale_revision_protection(): void
    {
        $this->get(route('plugin.test.pricing.admin.index'))->assertOk()->assertJsonPath('items.0.id', $this->variant->id);
        $this->get(route('admin.extensions.index'))->assertOk()->assertSee('Manage Pricing fixture');
        $revision = $this->prices()->context()->revision;
        $input = ['currency' => 'PKR', 'revision' => $revision, 'operation_id' => (string) Str::uuid(), 'prices' => [
            ['kind' => 'variant', 'entity_id' => $this->variant->id, 'amount_minor' => 11000],
            ['kind' => 'variant', 'entity_id' => 999999, 'amount_minor' => 100],
        ]];
        $before = AdminAuditLog::count();
        $this->postJson(route('plugin.test.pricing.admin.save'), $input)->assertUnprocessable();
        $this->assertSame($before, AdminAuditLog::count());
        $this->assertSame(15000, $this->prices()->read('variant', [$this->variant->id], 'PKR')[$this->variant->id]['amount_minor']);
        $input['prices'] = [$input['prices'][0]];
        $this->postJson(route('plugin.test.pricing.admin.save'), $input)->assertOk();
        $this->postJson(route('plugin.test.pricing.admin.save'), $input)->assertOk(); // same operation replay
        $input['operation_id'] = (string) Str::uuid();
        $this->postJson(route('plugin.test.pricing.admin.save'), $input)->assertConflict();
    }

    public function test_management_deactivation_preserves_core_price_interpretation(): void
    {
        $this->enable();
        $this->savePrice('USD');
        $this->select('USD');
        DB::table('plugin_states')->where('plugin_id', 'test.pricing')->update(['active' => false]);
        DB::table('extension_runtime')->where('id', 1)->increment('revision');
        $this->get(route('plugin.test.pricing.admin.index'))->assertNotFound();
        $this->get('/shop')->assertOk()->assertSee('Leather jacket')->assertSee('$ 150');
        $this->assertSame('USD', $this->prices()->context()->currency);
    }

    public function test_unauthorized_plugin_writes_are_rejected_by_core_contract(): void
    {
        $this->actor->update(['role' => 'custom', 'permissions' => ['test.pricing.manage']]);
        $this->postJson(route('plugin.test.pricing.admin.save'), ['currency' => 'PKR', 'revision' => $this->prices()->context()->revision,
            'operation_id' => (string) Str::uuid(), 'prices' => [['kind' => 'variant', 'entity_id' => $this->variant->id, 'amount_minor' => 1]]])->assertForbidden();
    }

    public function test_manual_currency_eligibility_rejects_an_enabled_unsupported_method(): void
    {
        $this->prices()->enable(['PKR', 'AUD'], $this->prices()->context()->revision, (string) Str::uuid());
        $this->savePrice('AUD');
        $this->select('AUD');
        $this->assertSame([], app(PaymentMethodService::class)->choices());
        $input = $this->checkout();
        $this->postJson(route('checkout.quote'), $input)->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }

    public function test_legacy_provider_requires_explicit_policy_and_does_not_gain_a_new_currency(): void
    {
        $this->app->bind(PricingPayment::class, LegacyPayment::class);
        app(PluginRuntime::class)->forget();
        $this->assertSame([], app(PaymentMethodService::class)->choices());
        $this->prices()->paymentPolicy('test.pricing.manual', ['PKR'], 'Verified provider contract only accepts the original PKR currency.', $this->prices()->context()->revision, (string) Str::uuid());
        $this->assertArrayHasKey('legacy_manual', app(PaymentMethodService::class)->choices());
        $this->enable();
        $this->savePrice('USD');
        $this->select('USD');
        $this->assertSame([], app(PaymentMethodService::class)->choices());
    }

    public function test_owner_and_destination_changes_cannot_reuse_a_review(): void
    {
        $input = $this->review($this->checkout());
        $this->postJson(route('checkout.place-order'), $this->withHoneypot([...$input, 'address_line' => 'Different street']))->assertUnprocessable();
        $this->withSession(['cart_session_id' => 'different-guest'])->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_signed_and_missing_option_amounts_are_not_implicitly_zero(): void
    {
        $group = $this->product->optionGroups()->create(['name' => 'Finish', 'selection_type' => 'single', 'min_selections' => 1, 'max_selections' => 1, 'is_active' => true]);
        $option = $group->values()->create(['name' => 'Standard finish', 'price_adjustment' => '0.00', 'is_active' => true]);
        $this->enable();
        $this->savePrice('USD');
        $this->select('USD');
        $payload = ['product_id' => $this->product->id, 'quantity' => 1, 'option_value_ids' => [$option->id]];
        $this->postJson(route('cart.add'), $payload)->assertUnprocessable();
        $this->prices()->save('USD', [['kind' => 'option', 'entity_id' => $option->id, 'amount_minor' => -1000]], $this->prices()->context()->revision, (string) Str::uuid());
        $this->postJson(route('cart.add'), $payload)->assertOk();
        $this->assertDatabaseHas('cart_items', ['currency' => 'USD', 'price' => '140.00']);
        $this->prices()->save('USD', [['kind' => 'option', 'entity_id' => $option->id, 'amount_minor' => -20000]], $this->prices()->context()->revision, (string) Str::uuid());
        $this->postJson(route('cart.add'), $payload)->assertUnprocessable();
    }

    public function test_product_alias_and_default_variant_sale_agree_with_filters_and_checkout(): void
    {
        $this->enable();
        $this->savePrice('USD');
        $this->select('USD');
        $this->prices()->save('USD', [['kind' => 'product', 'entity_id' => $this->product->id, 'amount_minor' => 17000, 'sale_minor' => 13000]], $this->prices()->context()->revision, (string) Str::uuid());
        $this->assertSame(13000, $this->prices()->read('variant', [$this->variant->id], 'USD')[$this->variant->id]['sale_minor']);
        $this->get('/shop?min_price=130&max_price=130')->assertOk()->assertSee('Leather jacket')->assertSee('$ 130');
        $this->get('/shop?min_price=140')->assertOk()->assertDontSee('Leather jacket');
        $this->review($this->checkout());
        $this->assertDatabaseHas('cart_items', ['currency' => 'USD', 'price' => '130.00']);
        $catalog = $this->prices()->catalog('product', 'USD', 1, 1, ['max_minor' => 13000, 'sort' => 'price_asc']);
        $this->assertSame(1, $catalog['total']);
        $this->assertSame(13000, $catalog['items'][0]['price']['sale_minor']);
    }

    public function test_historical_dashboard_and_queued_notifications_keep_transaction_currencies(): void
    {
        $this->enable();
        $this->savePrice('USD', 15000);
        $this->savePrice('EUR', 14500);
        $ids = [];
        foreach (['USD', 'EUR'] as $currency) {
            $this->select($currency);
            $input = $this->review($this->checkout());
            $ids[$currency] = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()->json('order_id');
        }
        $this->get(route('admin.dashboard', ['currency' => 'USD']))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['total_sales'] == 155 && $stats['currency'] === 'USD');
        $this->get(route('admin.dashboard', ['currency' => 'EUR']))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['total_sales'] == 150 && $stats['currency'] === 'EUR');
        $this->get(route('admin.orders.show', $ids['USD']))->assertOk()->assertSee('$ 155')->assertDontSee('€ 155');
        foreach ($ids as $currency => $id) {
            $payload = json_decode(DB::table('notification_outbox')->where('order_id', $id)->value('payload'), true);
            $this->assertSame($currency, $payload['order']['currency']);
        }
        $this->get(route('admin.chart.data', ['currency' => 'USD']))->assertOk()->assertJsonPath('6.currency', 'USD')->assertJsonPath('6.revenue', '155');
    }

    public function test_bundled_checkout_renders_the_server_review_workflow(): void
    {
        $this->checkout();
        $this->get(route('checkout.index'))->assertOk()->assertSee('Review final total')->assertSee('Confirm reviewed order')
            ->assertSee('name="review_token"', false)->assertSee('Review the total above, then confirm your order.');
    }

    public function test_every_bundled_template_compiles_with_the_current_core(): void
    {
        $package = json_decode(file_get_contents(base_path('resources/themes/default/composer.json')), true);
        foreach ($package['extra']['mavyn']['templates'] as $path) {
            $compiled = Blade::compileString(file_get_contents(base_path('resources/themes/default/'.$path)));
            $this->assertNotEmpty(\PhpToken::tokenize($compiled, TOKEN_PARSE), $path);
        }
    }

    public function test_missing_queued_currency_blocks_selling_without_rewriting_history_or_hiding_receipts(): void
    {
        $this->enable();
        $this->savePrice('USD');
        $this->select('USD');
        $input = $this->review($this->checkout());
        $id = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()->json('order_id');
        $entry = DB::table('notification_outbox')->where('order_id', $id)->first();
        $payload = json_decode($entry->payload, true);
        unset($payload['order']['currency']);
        $stored = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::table('notification_outbox')->where('id', $entry->id)->update(['payload' => $stored]);
        app(PricingBackfill::class)->run();
        $this->assertDatabaseHas('currency_runtime', ['ready' => false]);
        $this->assertSame($stored, DB::table('notification_outbox')->where('id', $entry->id)->value('payload'));
        $order = Order::findOrFail($id);
        $this->get(route('orders.track-guest', $order->tracking_token))->assertOk()->assertSee('$ 155');
        $this->assertDatabaseHas('orders', ['id' => $id, 'currency' => 'USD']);
    }

    public function test_historical_option_adjustments_use_the_order_currency(): void
    {
        $this->enable();
        $this->select('EUR');
        $order = new Order(['currency' => 'USD', 'currency_symbol' => '$']);
        $item = new OrderItem(['options_snapshot' => [['group_name' => 'Finish', 'value_name' => 'Premium', 'price_adjustment' => '5.00']]]);
        $item->setRelation('order', $order);
        $html = view('components.item-configuration', compact('item', 'order'))->render();
        $this->assertStringContainsString('$ 5', $html);
        $this->assertStringNotContainsString('€ 5', $html);
    }
}
