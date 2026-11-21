<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CheckoutAttemptService;
use App\Services\OrderStateService;
use App\Services\Payments\PaymentService;
use App\Services\Plugins\PluginRoutes;
use App\Services\Plugins\PluginRuntime;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\Payments;
use Tests\Fixtures\Gateway\FakeGateway;
use Tests\TestCase;

/**
 * A store with only a card gateway (like Stripe) and no fulfillment plugin: checkout must still work,
 * with the built-in "ships after production" option at no charge and no delivery choice for the customer.
 */
class StripeOnlyCheckoutTest extends TestCase
{
    private string $catalogPath;

    private string $themesPath;

    private Admin $actor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force' => true]);
        $this->catalogPath = tempnam(sys_get_temp_dir(), 'mavyn-cardonly-plugins-');
        $this->themesPath = tempnam(sys_get_temp_dir(), 'mavyn-cardonly-themes-');
        $base = ['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock'))];
        $manifest = json_decode(file_get_contents(base_path('tests/Fixtures/CardOnly/composer.json')), true)['extra']['mavyn'];
        file_put_contents($this->catalogPath, json_encode([...$base, 'packages' => [['package' => 'test/card-only', 'version' => '1.0.0', 'path' => 'tests/Fixtures/CardOnly', 'manifest' => $manifest]]], JSON_THROW_ON_ERROR));
        $theme = json_decode(file_get_contents(base_path('resources/themes/default/composer.json')), true);
        file_put_contents($this->themesPath, json_encode([...$base, 'packages' => [['package' => $theme['name'], 'version' => '0.1.0', 'path' => 'resources/themes/default', 'manifest' => $theme['extra']['mavyn']]]], JSON_THROW_ON_ERROR));
        config(['extensions.catalog' => $this->catalogPath, 'extensions.live_manifests' => false, 'themes.catalog' => $this->themesPath]);
        DB::table('plugin_states')->insert(['plugin_id' => 'test.cardonly', 'version' => '1.0.0', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('extension_runtime')->where('id', 1)->increment('revision');
        app(PluginRuntime::class)->forget();
        app(PluginRoutes::class)->register();
        Route::getRoutes()->refreshNameLookups();
        $this->actor = Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'fixture-only-password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($this->actor, 'admin');
        auth()->shouldUse('web');
        $category = Category::create(['name' => 'Race suits', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Custom race suit', 'price' => '150.00', 'is_active' => true, 'in_stock' => true]);
        $this->product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
        FakeGateway::$sessions = FakeGateway::$calls = FakeGateway::$states = FakeGateway::$refunds = [];
        FakeGateway::$timeout = false;
    }

    protected function tearDown(): void
    {
        unlink($this->catalogPath);
        unlink($this->themesPath);
        parent::tearDown();
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['idempotency_key' => app(CheckoutAttemptService::class)->issue(),
            'first_name' => 'Test', 'last_name' => 'Rider', 'email' => 'rider@example.test', 'phone' => '+1 555 0100',
            'country' => 'United States', 'city' => 'Austin', 'postal_code' => '73301', 'address_line' => '1 Main Street',
            'payment_method' => 'fixture_card'], $overrides);
    }

    public function test_checkout_without_fulfillment_plugins_offers_only_free_built_in_dispatch(): void
    {
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])->assertOk();

        $this->assertTrue(app(ShippingService::class)->usesBuiltInDispatch());
        $this->postJson(route('checkout.options'), ['country' => 'Germany'])->assertOk()
            ->assertJsonPath('shipping.0.id', 'core.dispatch:standard')
            ->assertJsonPath('shipping.0.fee_minor', 0)
            ->assertJsonCount(1, 'shipping')
            ->assertJsonPath('payments.0.id', 'fixture_card');

        $page = $this->get(route('checkout.index'))->assertOk();
        $page->assertSee('id="shipping-section"', false)->assertSee('Ships after production');
        $this->assertMatchesRegularExpression('/<section class="ck-section" id="shipping-section"\s+hidden\s*>/', $page->getContent());
        $this->get(route('cart.index'))->assertOk()->assertDontSee('Depends on location');
    }

    public function test_card_only_order_is_charged_merchandise_only_paid_and_managed_in_admin(): void
    {
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 2])->assertOk();
        // The customer never chooses delivery: no shipping_option is sent at all.
        $input = $this->input();
        $quote = $this->postJson(route('checkout.quote'), $input)->assertOk()
            ->assertJsonPath('quote.shipping.fee_minor', 0)
            ->assertJsonPath('quote.total_minor', 30000);
        $input['review_token'] = $quote->json('review_token');
        $response = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()
            ->assertJsonPath('payment_action.type', 'redirect');

        $order = Order::findOrFail($response->json('order_id'));
        $this->assertSame('300.00', $order->grand_total);
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('core.dispatch', $order->shipping_snapshot['provider_key']);
        $this->assertSame('Ships after production', $order->delivery_method_label);
        $this->assertSame('Free', $order->delivery_fee_label);
        $this->assertSame('United States', $order->delivery_country);
        $this->assertSame('1 Main Street, Austin 73301, United States', $order->full_delivery_address);

        // Gateway charges exactly the reviewed total, then a verified payment marks the order paid.
        $attempt = app(PaymentService::class)->forOrder($order->id);
        $this->assertSame(30000, (int) $attempt->amount_minor);
        $this->postJson(route('payments.start', $order))->assertOk()->assertJsonPath('payment_action.type', 'redirect');
        $context = app(Payments::class)->context($attempt->id);
        app(Payments::class)->apply('test.cardonly.card', new PaymentResult($attempt->id, 'paid-event', 'paid', $context->amountMinor, $context->currency, $context->reference ?? 'payment-'.$attempt->id));
        $this->assertSame('paid', $order->fresh()->payment_status);

        // Staff then run the normal fulfilment workflow; dashboard counts it as collected revenue.
        foreach (['confirmed', 'preparing', 'out_for_delivery', 'delivered'] as $status) {
            app(OrderStateService::class)->updateMany([$order->id], ['status' => $status, 'payment_status' => 'paid'], $this->actor);
        }
        $this->assertSame('delivered', $order->fresh()->status);
        $this->get(route('admin.dashboard', ['currency' => $order->currency]))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['collected'] === '300.00' && $stats['awaiting_payment'] === '0.00');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Ships after production')->assertSee('Austin 73301');
        $this->get(route('admin.orders.invoice', $order))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_unpaid_card_order_cannot_be_dispatched_and_counts_as_awaiting_payment(): void
    {
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])->assertOk();
        $input = $this->input();
        $input['review_token'] = $this->postJson(route('checkout.quote'), $input)->assertOk()->json('review_token');
        $order = Order::findOrFail($this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()->json('order_id'));

        $this->get(route('admin.dashboard', ['currency' => $order->currency]))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['awaiting_payment'] === '150.00' && $stats['collected'] === '0.00');

        try {
            app(OrderStateService::class)->updateMany([$order->id], ['status' => 'preparing', 'payment_status' => 'pending'], $this->actor);
            $this->fail('An unpaid card order must not move into fulfilment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_explicit_option_from_a_removed_delivery_plugin_is_rejected_not_swapped(): void
    {
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])->assertOk();
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($this->input(['shipping_option' => 'mavyn.home-delivery.standard:home'])))
            ->assertUnprocessable()->assertJsonValidationErrors('shipping_option');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }
}
