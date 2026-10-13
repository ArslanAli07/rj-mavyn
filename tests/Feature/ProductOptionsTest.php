<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00', 'ordering', 'text');
        Setting::set('ordering_end_time', '23:59', 'ordering', 'text');
    }

    protected function createConfiguredProduct(): array
    {
        $category = Category::create([
            'name' => 'Fruit Tea',
            'slug' => 'fruit-tea',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Berry Bliss Tea',
            'slug' => 'berry-bliss-tea',
            'price' => 500,
            'short_description' => 'A fruity tea',
            'description' => 'Long description',
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 20,
        ]);

        $regular = $product->variants()->create([
            'name' => 'Regular',
            'weight' => 'Regular',
            'price' => 500,
            'servings' => '1 Serving',
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        $large = $product->variants()->create([
            'name' => 'Large',
            'weight' => 'Large',
            'price' => 650,
            'servings' => '1 Large Serving',
            'sort_order' => 1,
            'is_default' => false,
            'is_active' => true,
        ]);

        $toppings = $product->optionGroups()->create([
            'name' => 'Toppings',
            'selection_type' => 'multiple',
            'min_selections' => 0,
            'max_selections' => 3,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $berries = $toppings->values()->create([
            'name' => 'Extra berries',
            'price_adjustment' => 50,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $iceGroup = $product->optionGroups()->create([
            'name' => 'Ice',
            'selection_type' => 'single',
            'min_selections' => 1,
            'max_selections' => 1,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $extraIce = $iceGroup->values()->create([
            'name' => 'Extra ice',
            'price_adjustment' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $noIce = $iceGroup->values()->create([
            'name' => 'No ice',
            'price_adjustment' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return compact('product', 'regular', 'large', 'toppings', 'berries', 'iceGroup', 'extraIce', 'noIce');
    }

    public function test_fractional_option_adjustments_use_exact_minor_units(): void
    {
        extract($this->createConfiguredProduct());
        $large->update(['price' => '0.10', 'discount_price' => null]);
        $berries->update(['price_adjustment' => '0.20']);
        $extraIce->update(['price_adjustment' => '-0.05']);
        $config = app(ProductConfigurationService::class)->resolve($product->fresh(), $large->id, [$berries->id, $extraIce->id]);
        $this->assertSame('0.25', $config['unit_price']);
        $this->assertSame(['0.20', '-0.05'], array_column($config['options_snapshot'], 'price_adjustment'));
    }

    public function test_configuration_service_prices_variant_plus_options_and_enforces_rules(): void
    {
        extract($this->createConfiguredProduct());
        $service = app(ProductConfigurationService::class);

        $config = $service->resolve($product, $large->id, [$berries->id, $extraIce->id]);

        $this->assertSame('700.00', $config['unit_price']);
        $this->assertSame('Large', $config['variant_name']);
        $this->assertCount(2, $config['options_snapshot']);

        $this->expectException(ValidationException::class);
        $service->resolve($product, $large->id, [$berries->id]); // missing required ice
    }

    public function test_option_reductions_cannot_produce_a_negative_unit_price(): void
    {
        extract($this->createConfiguredProduct());
        $berries->update(['price_adjustment' => '-1000.00']);
        $this->expectException(ValidationException::class);
        app(ProductConfigurationService::class)->resolve($product->fresh(), $large->id, [$berries->id, $extraIce->id]);
    }

    public function test_cart_keeps_different_configurations_separate_and_merges_identical_ones(): void
    {
        extract($this->createConfiguredProduct());
        $cart = app(CartService::class);

        $cart->add($product, 1, $large, [$berries->id, $extraIce->id]);
        $cart->add($product, 1, $large, [$extraIce->id]);
        $this->assertSame(2, $cart->items()->count());

        $cart->add($product, 2, $large, [$berries->id, $extraIce->id]);
        $items = $cart->items();
        $this->assertSame(2, $items->count());

        $merged = $items->firstWhere(fn ($item) => collect($item->options_snapshot)->pluck('value_id')->contains($berries->id));
        $this->assertSame(3, $merged->quantity);
        $this->assertEquals(700.0, (float) $merged->price);
    }

    public function test_cart_rejects_tampered_option_ids(): void
    {
        extract($this->createConfiguredProduct());

        $otherProduct = Product::create([
            'category_id' => $product->category_id,
            'title' => 'Other Tea',
            'slug' => 'other-tea',
            'price' => 400,
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 5,
        ]);
        $otherProduct->variants()->create([
            'name' => 'Regular',
            'weight' => 'Regular',
            'price' => 400,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $foreignGroup = $otherProduct->optionGroups()->create([
            'name' => 'Foreign',
            'selection_type' => 'single',
            'min_selections' => 0,
            'max_selections' => 1,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $foreignValue = $foreignGroup->values()->create([
            'name' => 'Hack',
            'price_adjustment' => 999,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'product_variant_id' => $large->id,
            'option_value_ids' => [$foreignValue->id, $extraIce->id],
            'quantity' => 1,
        ]);

        $response->assertSessionHasErrors('option_value_ids');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_admin_can_create_and_rename_variants_and_option_groups(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Milk Tea',
            'slug' => 'milk-tea',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.products.store'), [
            'title' => 'Brown Sugar Boba',
            'category_id' => $category->id,
            'display_order' => 1,
            'stock_quantity' => 10,
            'short_description' => 'Sweet tea',
            'description' => 'Long',
            'meta_title' => 'Brown Sugar Boba in Sialkot',
            'meta_description' => 'Order handcrafted brown sugar boba from Happy Tea.',
            'meta_keywords' => 'brown sugar boba, boba Sialkot',
            'is_active' => 1,
            'in_stock' => 1,
            'variants' => [
                [
                    'name' => 'Regular',
                    'price' => 550,
                    'discount_price' => '',
                    'servings' => '1',
                    'is_default' => 1,
                    'is_active' => 1,
                    'sort_order' => 0,
                ],
                [
                    'name' => 'Large',
                    'price' => 650,
                    'discount_price' => '',
                    'servings' => '1',
                    'is_default' => 0,
                    'is_active' => 1,
                    'sort_order' => 1,
                ],
            ],
            'option_groups' => [
                [
                    'name' => 'Toppings',
                    'selection_type' => 'multiple',
                    'min_selections' => 0,
                    'max_selections' => 2,
                    'is_required' => 0,
                    'is_active' => 1,
                    'sort_order' => 0,
                    'values' => [
                        ['name' => 'Extra berries', 'price_adjustment' => 40, 'is_active' => 1, 'sort_order' => 0],
                        ['name' => 'Extra ice', 'price_adjustment' => 0, 'is_active' => 1, 'sort_order' => 1],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $product = Product::where('title', 'Brown Sugar Boba')->firstOrFail();
        $this->assertCount(2, $product->variants);
        $this->assertCount(1, $product->optionGroups);
        $this->assertSame('Extra berries', $product->optionGroups->first()->values->first()->name);
        $this->assertSame('Brown Sugar Boba in Sialkot', $product->meta_title);
        auth()->shouldUse('web');
        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('<title>Brown Sugar Boba in Sialkot</title>', false)
            ->assertSee('content="Order handcrafted brown sugar boba from Happy Tea."', false)
            ->assertSee('content="brown sugar boba, boba Sialkot"', false);

        $group = $product->optionGroups->first();
        $value = $group->values->first();
        $regular = $product->variants->firstWhere('name', 'Regular');

        $update = $this->put(route('admin.products.update', $product), [
            'title' => 'Brown Sugar Boba',
            'category_id' => $category->id,
            'display_order' => 1,
            'stock_quantity' => 10,
            'short_description' => 'Sweet tea',
            'description' => 'Long',
            'is_active' => 1,
            'in_stock' => 1,
            'variants' => [
                [
                    'id' => $regular->id,
                    'name' => 'Medium',
                    'price' => 560,
                    'discount_price' => '',
                    'servings' => '1',
                    'is_default' => 1,
                    'is_active' => 1,
                    'sort_order' => 0,
                ],
            ],
            'option_groups' => [
                [
                    'id' => $group->id,
                    'name' => 'Add-ons',
                    'selection_type' => 'multiple',
                    'min_selections' => 0,
                    'max_selections' => 1,
                    'is_required' => 0,
                    'is_active' => 1,
                    'sort_order' => 0,
                    'values' => [
                        [
                            'id' => $value->id,
                            'name' => 'Berry Burst',
                            'price_adjustment' => 45,
                            'is_active' => 1,
                            'sort_order' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $update->assertRedirect(route('admin.products.index'));
        $product->refresh()->load(['variants', 'optionGroups.values']);
        $this->assertSame('Medium', $product->variants->first()->name);
        $this->assertSame('Add-ons', $product->optionGroups->first()->name);
        $this->assertSame('Berry Burst', $product->optionGroups->first()->values->first()->name);
    }

    public function test_checkout_snapshots_options_and_survives_catalog_rename(): void
    {
        extract($this->createConfiguredProduct());
        $zone = DeliveryZone::create([
            'name' => 'Sialkot City',
            'delivery_fee' => 100,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $add = $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'product_variant_id' => $large->id,
            'option_value_ids' => [$berries->id, $extraIce->id],
            'special_request' => 'Please make it sweet',
            'quantity' => 1,
        ]);
        $add->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cart_items', 1);

        $response = $this->post(route('checkout.place-order'), $this->withHoneypot([
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
            'email' => 'ali@example.com',
            'phone' => '03001234567',
            'country' => 'Pakistan',
            'city' => 'Sialkot',
            'delivery_zone_id' => (string) $zone->id,
            'address_line' => 'Street 1',
            'payment_method' => 'cod',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseCount('order_items', 1);
        $orderItem = OrderItem::firstOrFail();
        $this->assertSame('Large', $orderItem->variant_name);
        $this->assertSame('Please make it sweet', $orderItem->special_request);
        $this->assertEquals(700.0, (float) $orderItem->unit_price);
        $this->assertSame('Extra berries', $orderItem->options_snapshot[0]['value_name'] ?? null);

        $berries->update(['name' => 'Renamed berries']);
        $orderItem->refresh();
        $this->assertSame('Extra berries', $orderItem->options_snapshot[0]['value_name']);
    }

    public function test_guest_cart_merge_uses_configuration_signature(): void
    {
        extract($this->createConfiguredProduct());
        $user = User::factory()->create();

        // Guest cart
        app(CartService::class)->add($product, 1, $large, [$berries->id, $extraIce->id]);
        $this->assertSame(1, CartItem::whereNotNull('session_id')->count());

        $this->actingAs($user);
        app(CartService::class)->mergeGuestCart();

        $this->assertSame(1, CartItem::where('user_id', $user->id)->count());
        $this->assertSame(0, CartItem::whereNotNull('session_id')->count());

        // Add identical config again — should merge quantity
        app(CartService::class)->add($product, 1, $large, [$berries->id, $extraIce->id]);
        $this->assertSame(1, CartItem::where('user_id', $user->id)->count());
        $this->assertSame(2, (int) CartItem::where('user_id', $user->id)->value('quantity'));
    }
}
