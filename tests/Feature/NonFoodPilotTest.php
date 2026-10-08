<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Pilots\PilotCatalog;
use Tests\TestCase;

class NonFoodPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_stationery_catalog_admin_and_storefront_have_generic_fields_and_precise_prices(): void
    {
        ['admin' => $admin, 'category' => $category] = PilotCatalog::configure('stationery');
        $this->actingAs($admin, 'admin')->get(route('admin.products.create'))->assertOk()->assertDontSee('name="ingredients"', false)->assertDontSee('name="allergens"', false)->assertDontSee('][servings]', false);
        $this->post(route('admin.products.store'), PilotCatalog::product('stationery', $category->id))->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertSame(['A5', 'A4'], $product->variants->pluck('name')->all());
        $this->assertNull($product->ingredients);
        auth()->shouldUse('web');
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('Paper Trail')->assertSee('Select Variant:')->assertSee('Rs. 125.55')->assertDontSee('Serves:')->assertDontSee('Ingredients Listing:')->assertDontSee('All drinks');
        $this->get(route('shop'))->assertOk()->assertSee('Everyday Journal')->assertDontSee('Milk Tea')->assertDontSee('Boba')->assertDontSee('fruit teas');
        $this->get(route('search'))->assertOk()->assertSee('Search products...')->assertDontSee('Search milk tea');
        $this->get(route('cart.index'))->assertOk()->assertDontSee('favorite sip');
    }

    public function test_stationery_guest_configured_checkout_receipt_coupon_stock_and_snapshots(): void
    {
        ['admin' => $admin, 'category' => $category, 'zone' => $zone] = PilotCatalog::configure('stationery');
        Storage::fake('local');
        $this->actingAs($admin, 'admin')->post(route('admin.products.store'), PilotCatalog::product('stationery', $category->id))->assertSessionHasNoErrors();
        $product = Product::sole();
        auth()->shouldUse('web');
        $values = $product->optionGroups()->with('values')->get()->map(fn ($group) => $group->values->first()->id)->all();
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'product_variant_id' => $product->variants()->first()->id, 'option_value_ids' => $values, 'quantity' => 2, 'special_request' => 'Wrap as a gift'])->assertOk();
        $this->postJson(route('cart.coupon'), ['code' => 'PILOT'])->assertOk();
        $this->get(route('checkout.index'))->assertOk()->assertSee('Everyday Journal');
        $data = $this->withHoneypot(['first_name' => 'Paper', 'last_name' => 'Buyer', 'email' => 'paper-buyer@example.test', 'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot', 'delivery_zone_id' => $zone->id, 'address_line' => '1 Fixture Street', 'payment_method' => 'easypaisa', 'payment_receipt' => UploadedFile::fake()->image('receipt.png')]);
        $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('260.00', $order->subtotal);
        $this->assertSame('5.25', $order->discount_amount);
        $this->assertSame('261.10', $order->grand_total);
        $this->assertSame(20, $product->fresh()->stock_quantity);
        $this->assertSame(2, $product->fresh()->reserved_quantity);
        $this->assertSame('A5', $order->items->sole()->variant_name);
        $this->assertEmpty($order->items->sole()->attributes_snapshot);
        $this->assertSame('Ivory', $order->items->sole()->options_snapshot[0]['value_name']);
        $this->assertNotNull($order->payment_receipt);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->get(route('checkout.payment-instructions', $order))->assertOk()->assertSee('fixture-account-stationery');
        $product->update(['title' => 'Changed journal']);
        $this->get(route('orders.track-guest', $order->tracking_token))->assertOk()->assertSee('Everyday Journal')->assertSee('A5')->assertDontSee('Changed journal');
        $replay = $data;
        unset($replay['payment_receipt']);
        $this->postJson(route('checkout.place-order'), $replay)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(20, $product->fresh()->stock_quantity);
        $this->assertSame(2, $product->fresh()->reserved_quantity);
        $this->actingAs(User::factory()->create(), 'web')->get(route('orders.show', $order))->assertForbidden();
    }

    public function test_food_profile_keeps_independent_theme_fields_settings_and_snapshot_policy(): void
    {
        ['admin' => $admin, 'category' => $category] = PilotCatalog::configure('beverage');
        $this->actingAs($admin, 'admin')->post(route('admin.products.store'), PilotCatalog::product('beverage', $category->id))->assertSessionHasNoErrors();
        $product = Product::sole();
        auth()->shouldUse('web');
        $this->get(route('product.show', $product->slug))->assertOk()->assertSee('Tea Pilot')->assertSee('Jasmine tea')->assertSee('Rs. 125.55');
        $this->assertSame('Jasmine tea', $product->ingredients);
        $this->assertDatabaseHas('theme_selection', ['active_theme' => 'mavyn.theme-happy-tea']);
        $this->assertDatabaseHas('plugin_states', ['plugin_id' => 'mavyn.food-products', 'active' => true]);
        $this->assertSame(0, DB::table('orders')->count());
    }
}
