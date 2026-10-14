<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginManager;
use App\Services\ProductAttributesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Plugins\FixtureShippingPlugin;
use Tests\TestCase;

class SelectableShippingTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogPath;

    protected function setUp(): void
    {
        parent::setUp();
        $packages = array_values(app(InstalledPluginCatalog::class)->load()->all());
        $packages[] = ['package' => 'test/shipping', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Plugins', 'manifest' => [
            'kind' => 'plugin', 'id' => 'test.shipping', 'name' => 'Shipping fixture',
            'provider' => FixtureShippingPlugin::class, 'capabilities' => ['fulfillment.provider', 'product.attributes'],
        ]];
        $this->catalogPath = tempnam(sys_get_temp_dir(), 'mavyn-shipping-');
        file_put_contents($this->catalogPath, json_encode(['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'packages' => $packages]));
        config(['extensions.catalog' => $this->catalogPath]);
        app(PluginManager::class)->change('mavyn.local-delivery', false);
        app(PluginManager::class)->change('test.shipping', true);
        Setting::set('ordering_enabled', true);
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        Setting::set('email_order_confirmation', false, type: 'boolean');
        $category = Category::create(['name' => 'Shipping']);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Shipping product', 'price' => '10.00', 'is_active' => true, 'in_stock' => true]);
        $product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => '10.00', 'is_default' => true, 'is_active' => true]);
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    }

    protected function tearDown(): void
    {
        unlink($this->catalogPath);
        parent::tearDown();
    }

    private function checkout(array $overrides = []): array
    {
        return $this->withHoneypot(array_replace([
            'first_name' => 'Test', 'last_name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '12345',
            'country' => 'Pakistan', 'city' => 'Test city', 'address_line' => 'Test street',
            'shipping_option' => 'test.shipping.shipping:standard', 'payment_method' => 'cod',
        ], $overrides));
    }

    public function test_checkout_uses_another_shipping_plugin_without_local_delivery(): void
    {
        $this->get(route('checkout.index'))->assertOk()->assertSee('Fixture shipping')->assertSee('name="shipping_option"', false);
        $data = $this->checkout(['delivery_fee' => 0]);
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $order = Order::sole();
        $this->assertNull($order->delivery_zone_id);
        $this->assertSame('2.50', $order->delivery_fee);
        $this->assertSame('12.50', $order->grand_total);
        $this->assertSame('test.shipping.shipping', $order->shipping_snapshot['provider_key']);
        app(PluginManager::class)->change('test.shipping', false);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame('Fixture shipping', $order->fresh()->shipping_snapshot['label']);
    }

    public function test_unknown_or_disabled_provider_rejects_purchase_without_losing_cart(): void
    {
        $this->postJson(route('checkout.place-order'), $this->checkout(['shipping_option' => 'test.shipping.shipping:unknown']))->assertUnprocessable()->assertJsonValidationErrors('shipping_option');
        app(PluginManager::class)->change('test.shipping', false);
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertUnprocessable()->assertJsonValidationErrors('shipping_option');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_namespaced_product_and_variant_attributes_save_and_survive_plugin_disable(): void
    {
        $product = Product::sole();
        $variant = $product->variants()->sole();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'attributes@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $data = ['title' => $product->title, 'category_id' => $product->category_id, 'display_order' => 0, 'stock_quantity' => 0, 'price' => '10.00', 'in_stock' => 1, 'is_active' => 1,
            'plugin_attributes' => ['test.shipping.attributes' => ['material' => 'Recycled paper']],
            'variants' => [['id' => $variant->id, 'name' => 'Standard', 'price' => '10.00', 'is_active' => 1, 'is_default' => 1, 'plugin_attributes' => ['test.shipping.attributes' => ['warranty' => 'One year']]]]];
        $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame('Recycled paper', $product->fresh()->plugin_attributes['test.shipping.attributes']['material']);
        $this->assertSame('One year', $variant->fresh()->plugin_attributes['test.shipping.attributes']['warranty']);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Recycled paper')->assertSee('Material')->assertSee('Warranty');
        auth()->shouldUse('web');
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertOk();
        $snapshot = Order::sole()->items()->sole()->attributes_snapshot;
        $this->assertSame('Recycled paper', $snapshot['test.shipping.attributes']['material']['value']);
        $this->assertSame('One year', $snapshot['test.shipping.attributes']['warranty']['value']);
        app(PluginManager::class)->change('test.shipping', false);
        $this->actingAs($admin, 'admin')->putJson(route('admin.products.update', $product), $data)->assertUnprocessable();
        $this->assertSame('Recycled paper', $product->fresh()->plugin_attributes['test.shipping.attributes']['material']);
        $this->assertSame($snapshot, Order::sole()->items()->sole()->attributes_snapshot);
    }

    public function test_unknown_attribute_fields_are_rejected_before_catalog_writes(): void
    {
        $service = app(ProductAttributesService::class);
        $this->expectException(ValidationException::class);
        $service->rules(Request::create('/', 'POST', ['plugin_attributes' => ['test.shipping.attributes' => ['price' => '0']]]));
    }

    public function test_currency_tax_and_brand_snapshots_survive_later_configuration_changes(): void
    {
        // Provision a separate synthetic USD client; public currency changes are tested below.
        Setting::set('store_currency', 'USD');
        Setting::set('store_currency_symbol', '$');
        Setting::set('store_tax_rate', '17.50');
        Setting::set('site_name', 'Paper Trail');
        $this->postJson(route('checkout.place-order'), $this->checkout(['country' => 'US']))->assertOk();
        $order = Order::sole();
        $this->assertSame('USD', $order->currency);
        $this->assertSame('$', $order->currency_symbol);
        $this->assertSame('1.75', $order->tax_amount);
        $this->assertSame('17.50', $order->tax_rate);
        $this->assertSame('14.25', $order->grand_total);
        Setting::set('store_currency_symbol', 'USD');
        Setting::set('store_tax_rate', '0');
        Setting::set('site_name', 'Changed Store');
        $this->assertSame('$ 14.25', order_price($order->fresh(), $order->grand_total));
        $this->assertSame('Paper Trail', $order->fresh()->store_name);
    }

    public function test_currency_change_with_an_existing_catalog_rejects_the_entire_settings_write(): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'finance@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin')->putJson(route('admin.settings.update'), ['store_currency' => 'USD', 'site_name' => 'Changed'])->assertUnprocessable()->assertJsonValidationErrors('store_currency');
        $this->assertDatabaseMissing('settings', ['key' => 'store_currency']);
        $this->assertDatabaseMissing('settings', ['key' => 'site_name']);
    }

    public function test_generic_addresses_save_without_local_delivery_and_enforce_ownership(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'web')->post(route('account.addresses.store'), [
            'first_name' => 'Test', 'last_name' => 'Buyer', 'phone' => '12345', 'country' => 'US',
            'city' => 'New York', 'address_line' => 'Fixture street', 'postal_code' => '10001',
        ])->assertSessionHasNoErrors();
        $address = $customer->addresses()->sole();
        $this->assertNull($address->delivery_zone_id);
        $this->assertSame('10001', $address->postal_code);
        $this->actingAs(User::factory()->create(), 'web')->put(route('account.addresses.update', $address), [])->assertForbidden();
    }
}
