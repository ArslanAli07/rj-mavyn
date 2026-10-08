<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\LocalDeliveryService;
use App\Services\OrderPlacementService;
use App\Services\PaymentMethodService;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginSettings;
use App\Services\ProductAttributesService;
use App\Services\Themes\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mavyn\Core\Themes\ThemeCatalog;
use Tests\TestCase;

class CommercePluginsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 12:00', 'Asia/Karachi'));
        Storage::fake('local');
        Setting::set('ordering_enabled', true);
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        $category = Category::create(['name' => 'Plugin products', 'slug' => 'plugin-products', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Original product', 'slug' => 'original-product', 'price' => '12.50', 'ingredients' => 'Original ingredients', 'allergens' => 'Original allergens', 'in_stock' => true, 'is_active' => true]);
        $this->product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => '12.50', 'servings' => 'Two servings', 'is_active' => true, 'is_default' => true]);
        $this->zone = DeliveryZone::create(['name' => 'Original zone', 'delivery_fee' => '2.25', 'is_active' => true]);
        Setting::set('email_order_confirmation', false, 'email', 'boolean');
    }

    private function actor(array $permissions = [], string $role = 'super_admin'): Admin
    {
        return Admin::create(['name' => 'Operator', 'email' => uniqid().'@example.com', 'password' => 'password', 'role' => $role, 'is_active' => true, 'permissions' => $permissions]);
    }

    private function cart(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cart_items', 1);
    }

    private function checkout(array $overrides = []): array
    {
        return $this->withHoneypot(array_replace(['first_name' => 'Test', 'last_name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot', 'delivery_zone_id' => $this->zone->id, 'address_line' => '1 Main Street', 'payment_method' => 'cod'], $overrides));
    }

    private function productData(array $extra = []): array
    {
        $variant = $this->product->variants()->first();

        return array_replace(['title' => 'Updated product', 'category_id' => $this->product->category_id, 'display_order' => 0, 'stock_quantity' => 0, 'price' => '12.50', 'in_stock' => 1, 'is_active' => 1, 'variants' => [['id' => $variant->id, 'name' => 'Standard', 'price' => '12.50', 'is_default' => 1, 'is_active' => 1]]], $extra);
    }

    public function test_checkout_renders_provider_receipt_choices_and_one_upload_control(): void
    {
        $this->cart();
        $response = $this->get(route('checkout.index'))->assertOk();
        $this->assertSame(1, preg_match_all('/<input[^>]*name="payment_receipt"/', $response->getContent()));
        $response->assertSee('Cash on Delivery')->assertSee('Easypaisa')->assertSee('JazzCash');
        if ($capture = getenv('MAVYN_CHECKOUT_UI_CAPTURE')) {
            file_put_contents($capture, $response->getContent());
        }
    }

    public function test_existing_host_features_are_installed_and_enabled_with_legacy_identifiers(): void
    {
        $this->assertSame(4, DB::table('plugin_states')->where('active', true)->count());
        $this->assertSame(['cod', 'easypaisa', 'jazzcash', 'bank_transfer'], app(PaymentMethodService::class)->enabled());
        $this->assertCount(3, app(ProductAttributesService::class)->fields());
        $this->assertSame($this->zone->id, app(LocalDeliveryService::class)->zones()[0]->id);
        $this->assertTrue(ordering_enabled());
    }

    public function test_manual_payment_disable_blocks_new_checkout_but_preserves_cart(): void
    {
        $this->cart();
        $data = $this->checkout();
        app(PluginManager::class)->change('mavyn.manual-payments', false);
        $this->get(route('checkout.index'))->assertOk()->assertDontSee('name="payment_method"', false);
        $this->postJson(route('checkout.place-order'), $data)->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_local_delivery_disable_blocks_checkout_admin_and_preserves_zones(): void
    {
        $this->cart();
        $data = $this->checkout();
        app(PluginManager::class)->change('mavyn.local-delivery', false);
        $this->assertSame([], app(LocalDeliveryService::class)->zones());
        $this->postJson(route('checkout.place-order'), $data)->assertUnprocessable()->assertJsonValidationErrors('delivery_zone_id');
        $this->actingAs($this->actor(), 'admin')->get(route('admin.delivery-zones.index'))->assertNotFound();
        $this->assertDatabaseHas('delivery_zones', ['id' => $this->zone->id]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_completed_checkout_replays_after_all_commerce_plugins_are_disabled(): void
    {
        $this->cart();
        $data = $this->checkout();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $order = Order::sole();
        foreach (['mavyn.local-delivery', 'mavyn.manual-payments', 'mavyn.store-availability', 'mavyn.food-products'] as $id) {
            app(PluginManager::class)->change($id, false);
        }
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('order_id', $order->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_payment_instruction_and_attribute_snapshots_survive_config_changes_and_disable(): void
    {
        Setting::set('easypaisa_account', 'original-account');
        Setting::set('easypaisa_name', 'Original recipient');
        $this->cart();
        $data = $this->checkout(['payment_method' => 'easypaisa', 'payment_receipt' => UploadedFile::fake()->image('receipt.png')]);
        $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('mavyn.manual-payments.easypaisa', $order->payment_instructions_snapshot['provider_key']);
        $this->assertSame('Original ingredients', $order->items()->sole()->attributes_snapshot['mavyn.food-products.food']['ingredients']['value']);
        Setting::set('easypaisa_account', 'changed-account');
        $this->product->update(['ingredients' => 'Changed ingredients']);
        app(PluginManager::class)->change('mavyn.manual-payments', false);
        app(PluginManager::class)->change('mavyn.food-products', false);
        $this->get(route('checkout.payment-instructions', $order))->assertOk()->assertSee('original-account')->assertDontSee('changed-account');
        $this->assertSame('Original ingredients', $order->items()->sole()->attributes_snapshot['mavyn.food-products.food']['ingredients']['value']);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_direct_service_checks_availability_again_inside_order_transaction(): void
    {
        $this->cart();
        $data = $this->checkout();
        Setting::set('ordering_enabled', false);
        try {
            app(OrderPlacementService::class)->place($data);
            $this->fail('Closed store accepted order');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }
        $this->assertDatabaseCount('orders', 0);
        app(PluginManager::class)->change('mavyn.store-availability', false);
        $this->assertTrue(ordering_enabled());
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
    }

    public function test_food_disable_hides_fields_rejects_writes_and_preserves_existing_values(): void
    {
        app(PluginManager::class)->change('mavyn.food-products', false);
        $this->actingAs($this->actor(), 'admin');
        $this->get(route('admin.products.edit', $this->product))->assertOk()->assertDontSee('name="ingredients"', false)->assertDontSee('name="allergens"', false)->assertDontSee('][servings]', false);
        $this->putJson(route('admin.products.update', $this->product), $this->productData(['ingredients' => 'Changed']))->assertUnprocessable()->assertJsonValidationErrors('ingredients');
        $this->put(route('admin.products.update', $this->product), $this->productData())->assertSessionHasNoErrors();
        $this->assertSame('Original ingredients', $this->product->fresh()->ingredients);
        $this->assertSame('Two servings', $this->product->variants()->sole()->servings);
        auth()->shouldUse('web');
        $this->get(route('product.show', $this->product->fresh()->slug))->assertOk()->assertDontSee('Original ingredients')->assertDontSee('Original allergens');
        app(PluginManager::class)->change('mavyn.food-products', true);
        $this->get(route('admin.products.edit', $this->product))->assertOk()->assertSee('Original ingredients');
    }

    public function test_plugin_food_fields_validate_and_save_through_existing_transaction(): void
    {
        $this->actingAs($this->actor(), 'admin');
        $data = $this->productData(['ingredients' => 'New ingredients', 'allergens' => 'New allergens']);
        $data['variants'][0]['servings'] = 'Three servings';
        $this->put(route('admin.products.update', $this->product), $data)->assertSessionHasNoErrors();
        $this->assertSame('New ingredients', $this->product->fresh()->ingredients);
        $this->assertSame('Three servings', $this->product->variants()->sole()->servings);
        $data['variants'][0]['servings'] = str_repeat('a', 51);
        $this->putJson(route('admin.products.update', $this->product), $data)->assertUnprocessable()->assertJsonValidationErrors('variants.0.servings');
        $this->assertSame('Three servings', $this->product->variants()->sole()->servings);
    }

    public function test_legacy_setting_aliases_and_plugin_settings_share_authoritative_values(): void
    {
        $owner = $this->actor();
        app(PluginSettings::class)->save('mavyn.store-availability', ['ordering_enabled' => false], $owner);
        $this->assertFalse(Setting::get('ordering_enabled'));
        $this->assertFalse(ordering_enabled());
        Setting::set('ordering_enabled', true);
        $this->assertTrue(app(PluginSettings::class)->get('mavyn.store-availability', 'ordering_enabled'));
        $this->actingAs($this->actor(['settings.manage'], 'custom'), 'admin')->put(route('admin.settings.update'), ['payment_cod_enabled' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse(app(PluginSettings::class)->get('mavyn.manual-payments', 'payment_cod_enabled'));
        $this->assertNotContains('cod', app(PaymentMethodService::class)->enabled());
        app(PluginManager::class)->change('mavyn.manual-payments', false);
        $this->putJson(route('admin.settings.update'), ['payment_cod_enabled' => '1'])->assertStatus(409);
        $this->put(route('admin.settings.update'), ['site_name' => 'Still editable'])->assertSessionHasNoErrors();
        $this->assertSame('Still editable', Setting::get('site_name'));
    }

    public function test_active_plugin_quote_revalidates_zone_fee_and_activity(): void
    {
        $this->cart();
        $data = $this->checkout();
        $this->zone->update(['delivery_fee' => '3.35']);
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $order = Order::sole();
        $this->assertSame('3.35', $order->delivery_fee);
        $this->assertSame('15.85', $order->grand_total);
        $this->zone->update(['is_active' => false]);
        try {
            app(LocalDeliveryService::class)->quote($this->zone->id, 1250, 'Pakistan');
            $this->fail('Inactive zone accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery_zone_id', $exception->errors());
        }
    }

    public function test_theme_switch_during_checkout_preserves_actions_totals_and_order_replay(): void
    {
        $this->cart();
        $data = $this->checkout();
        app(ThemeManager::class)->activate(ThemeCatalog::DEFAULT);
        $this->get(route('cart.index'))->assertOk()->assertSee('Original product');
        $this->get(route('checkout.index'))->assertOk()->assertSee('name="payment_method"', false);
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $order = Order::sole();
        $this->assertSame('14.75', $order->grand_total);
        $this->get(route('checkout.success', $order))->assertOk();
        app(ThemeManager::class)->activate('mavyn.theme-happy-tea-child');
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('orders', 1);
    }
}
