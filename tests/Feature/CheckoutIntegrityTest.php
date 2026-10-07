<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        Setting::set('email_order_confirmation', '0', 'email', 'boolean');
        $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories', 'is_active' => true]);
        $this->product = Product::create([
            'category_id' => $category->id, 'title' => 'Travel bag', 'slug' => 'travel-bag',
            'price' => 500, 'in_stock' => true, 'is_active' => true, 'stock_quantity' => 10,
        ]);
        $this->product->variants()->create([
            'name' => 'Standard', 'weight' => 'Standard', 'price' => 500, 'is_active' => true, 'is_default' => true,
        ]);
        $this->zone = DeliveryZone::create(['name' => 'Central', 'delivery_fee' => 100, 'is_active' => true]);
    }

    private function addToCart(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertSessionHasNoErrors();
    }

    private function checkout(array $overrides = []): array
    {
        return $this->withHoneypot(array_replace([
            'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.com',
            'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot',
            'delivery_zone_id' => $this->zone->id, 'address_line' => '1 Main Street',
            'payment_method' => 'cod',
        ], $overrides));
    }

    private function order(?int $userId = null): Order
    {
        return Order::create([
            'order_number' => 'TEST-'.Order::count(), 'user_id' => $userId,
            'customer_name' => 'Test Customer', 'customer_email' => 'customer@example.com',
            'customer_phone' => '03001234567', 'delivery_country' => 'Pakistan',
            'delivery_city' => 'Sialkot', 'delivery_address' => '1 Main Street',
            'subtotal' => 500, 'grand_total' => 600, 'payment_method' => 'cod',
            'payment_status' => 'pending', 'status' => 'pending',
        ]);
    }

    private function coupon(): Coupon
    {
        return Coupon::create([
            'code' => 'SAVE', 'type' => 'fixed', 'amount' => 50, 'minimum_order' => 0,
            'is_active' => true, 'usage_limit' => 1, 'per_user_limit' => 1,
        ]);
    }

    public function test_anonymous_visitors_cannot_read_unrelated_guest_order_pages(): void
    {
        $order = $this->order();
        $this->get(route('checkout.success', $order))->assertForbidden();
        $this->get(route('checkout.payment-instructions', $order))->assertForbidden();
    }

    public function test_matching_guest_session_can_read_its_order_but_not_another(): void
    {
        $order = $this->order();
        $other = $this->order();
        $this->withSession(['placed_order_id' => $order->id]);
        $this->get(route('checkout.success', $order))->assertOk();
        $this->get(route('checkout.payment-instructions', $order))->assertOk();
        $this->get(route('checkout.success', $other))->assertForbidden();
    }

    public function test_customer_ownership_requires_a_real_matching_user(): void
    {
        $customer = User::factory()->create();
        $owned = $this->order($customer->id);
        $guest = $this->order();
        $other = $this->order(User::factory()->create()->id);
        $this->actingAs($customer);
        $this->get(route('checkout.success', $owned))->assertOk();
        $this->get(route('checkout.success', $guest))->assertForbidden();
        $this->get(route('checkout.success', $other))->assertForbidden();
    }

    public function test_disabling_every_payment_method_does_not_fall_back_to_cod(): void
    {
        $this->addToCart();
        foreach (['cod', 'easypaisa', 'jazzcash', 'bank_transfer'] as $method) {
            Setting::set('payment_'.$method.'_enabled', '0', 'payment', 'boolean');
        }
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_new_price_is_refreshed_and_requires_customer_resubmission(): void
    {
        $this->addToCart();
        $this->product->variants()->first()->update(['price' => 650]);
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('650.00', CartItem::firstOrFail()->price);

        $this->postJson(route('checkout.place-order'), $this->checkout())->assertOk();
        $this->assertSame('750.00', Order::firstOrFail()->grand_total);
        $this->assertDatabaseCount('cart_items', 0);
        $this->get(route('checkout.success', Order::firstOrFail()))->assertOk();
    }

    public function test_inactive_products_are_rejected_at_checkout_without_losing_cart(): void
    {
        $this->addToCart();
        $this->product->update(['is_active' => false]);
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_inactive_variants_are_revalidated_at_checkout(): void
    {
        $this->addToCart();
        $this->product->variants()->first()->update(['is_active' => false]);
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_new_required_options_are_revalidated_at_checkout(): void
    {
        $this->addToCart();
        $group = $this->product->optionGroups()->create([
            'name' => 'Material', 'selection_type' => 'single', 'min_selections' => 1,
            'max_selections' => 1, 'is_required' => true, 'is_active' => true,
        ]);
        $group->values()->create(['name' => 'Canvas', 'is_active' => true, 'price_adjustment' => 0]);
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('option_value_ids');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_coupon_cannot_be_used_after_it_was_applied(): void
    {
        $this->addToCart();
        $coupon = $this->coupon();
        $this->post(route('cart.coupon'), ['code' => $coupon->code])->assertSessionHasNoErrors();
        $coupon->update(['expires_at' => now()->subDay()]);
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('coupon');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
    }

    public function test_exhausted_coupon_cannot_be_used_after_it_was_applied(): void
    {
        $this->addToCart();
        $coupon = $this->coupon();
        $this->post(route('cart.coupon'), ['code' => $coupon->code]);
        $coupon->update(['usage_count' => 1]);
        $this->postJson(route('checkout.place-order'), $this->checkout())
            ->assertUnprocessable()->assertJsonValidationErrors('coupon');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_successful_checkout_snapshots_current_labels_and_records_coupon_once(): void
    {
        $this->addToCart();
        $coupon = $this->coupon();
        $this->post(route('cart.coupon'), ['code' => $coupon->code]);
        $this->product->variants()->first()->update(['name' => 'Everyday']);
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('550.00', $order->grand_total);
        $this->assertSame('Everyday', $order->items->first()->variant_name);
        $this->assertSame(1, $coupon->fresh()->usage_count);
        $this->assertDatabaseCount('coupon_usages', 1);
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_receipt_upload_is_private_and_admin_only(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->addToCart();
        $this->post(route('checkout.place-order'), $this->checkout([
            'payment_method' => 'bank_transfer',
            'payment_receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame('local', $order->payment_receipt_disk);
        Storage::disk('local')->assertExists($order->payment_receipt);
        Storage::disk('public')->assertMissing($order->payment_receipt);
        $url = route('admin.orders.receipt', $order);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->get($url)->assertRedirect(route('admin.login'));

        $admin = Admin::create([
            'name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin')->get($url)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_invalid_checkout_does_not_leave_uploaded_receipts(): void
    {
        Storage::fake('local');
        $this->addToCart();
        $this->product->update(['in_stock' => false]);
        $this->post(route('checkout.place-order'), $this->checkout([
            'payment_method' => 'bank_transfer',
            'payment_receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]))->assertSessionHasErrors('cart');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('orders', 0);
    }
}
