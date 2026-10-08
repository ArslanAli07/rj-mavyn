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
use App\Services\CouponIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    private function reusableCoupon(int $limit = 1): Coupon
    {
        return Coupon::create(['code' => 'IDENTITY', 'type' => 'fixed', 'amount' => 50,
            'is_active' => true, 'per_user_limit' => $limit, 'usage_limit' => 20]);
    }

    private function buyWithCoupon(string $email): array
    {
        $this->addToCart();
        $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY', 'email' => $email])->assertOk();
        $data = $this->checkout(['email' => $email]);
        $this->postJson(route('checkout.place-order'), $data)->assertOk();

        return $data;
    }

    public function test_guest_limit_survives_new_cart_identity_and_email_case_changes(): void
    {
        $coupon = $this->reusableCoupon();
        $data = $this->buyWithCoupon('Customer@Example.com');
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true);
        $this->withSession(['cart_session_id' => 'guest_another_session', 'coupon_code' => 'IDENTITY']);
        $this->addToCart();
        $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY', 'email' => 'customer@example.com'])->assertUnprocessable();
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertUnprocessable()->assertJsonValidationErrors('coupon');
        $this->assertSame(1, $coupon->fresh()->usage_count);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('coupon_usages', 1);
    }

    public function test_cart_application_without_email_is_provisional_and_checkout_rechecks_email(): void
    {
        $this->reusableCoupon();
        $this->buyWithCoupon('customer@example.com');
        $this->addToCart();
        $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY'])->assertOk();
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertUnprocessable()->assertJsonValidationErrors('coupon');
        $this->postJson(route('checkout.place-order'), $this->checkout(['email' => 'another@example.com']))->assertOk();
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_guest_then_account_purchase_shares_email_limit_even_with_other_contact_email(): void
    {
        $this->reusableCoupon();
        $this->buyWithCoupon('customer@example.com');
        $this->actingAs(User::factory()->create(['email' => 'CUSTOMER@example.com']), 'web');
        $this->addToCart();
        $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY', 'email' => 'other@example.com'])->assertUnprocessable();
        $this->withSession(['coupon_code' => 'IDENTITY']);
        $this->postJson(route('checkout.place-order'), $this->checkout(['email' => 'other@example.com']))->assertUnprocessable()->assertJsonValidationErrors('coupon');
    }

    public function test_account_purchase_counts_against_both_account_and_checkout_email_for_guests(): void
    {
        $coupon = $this->reusableCoupon();
        $user = User::factory()->create(['email' => 'account@example.com']);
        $this->actingAs($user, 'web');
        $this->buyWithCoupon('contact@example.com');
        $user->update(['email' => 'changed@example.com']);
        $this->assertTrue(app(CouponIdentityService::class)->limitReached($coupon, 'new@example.com'));
        auth('web')->logout();
        $this->addToCart();
        foreach (['account@example.com', 'contact@example.com'] as $email) {
            $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY', 'email' => $email])->assertUnprocessable();
        }
    }

    public function test_usage_survives_order_deletion_and_cancellation_and_counts_each_row_once(): void
    {
        $coupon = $this->reusableCoupon(2);
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $this->actingAs($user, 'web');
        $this->buyWithCoupon('customer@example.com');
        $this->assertFalse(app(CouponIdentityService::class)->limitReached($coupon, 'customer@example.com'));
        Order::sole()->update(['status' => 'cancelled']);
        $this->buyWithCoupon('customer@example.com');
        Order::query()->delete();
        $this->assertTrue(app(CouponIdentityService::class)->limitReached($coupon, 'customer@example.com'));
        $this->assertDatabaseCount('coupon_usages', 2);
    }

    public function test_email_ip_combination_allows_a_different_ip_and_ignores_untrusted_forwarded_headers(): void
    {
        $coupon = $this->reusableCoupon();
        $this->buyWithCoupon('customer@example.com');
        $this->addToCart();
        $this->withHeaders(['X-Forwarded-For' => '192.0.2.10']);
        $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY', 'email' => 'customer@example.com'])->assertUnprocessable();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        $this->postJson(route('cart.coupon'), ['code' => 'IDENTITY', 'email' => 'customer@example.com'])->assertOk();
        $this->postJson(route('checkout.place-order'), $this->checkout())->assertOk();
        $this->assertSame(2, $coupon->fresh()->usage_count);
        $this->assertSame(2, $coupon->usages()->distinct()->count('identity_hash'));
    }

    public function test_pair_hash_normalizes_email_and_ipv6_without_collapsing_email_aliases(): void
    {
        $this->assertSame(CouponIdentityService::pairHash(' Customer@Example.com ', '2001:db8::1'),
            CouponIdentityService::pairHash('customer@example.com', '2001:0db8:0:0:0:0:0:1'));
        $this->assertSame(CouponIdentityService::pairHash('customer@example.com', '192.0.2.1'),
            CouponIdentityService::pairHash('customer@example.com', '::ffff:192.0.2.1'));
        $this->assertNotSame(CouponIdentityService::pairHash('customer@example.com', '192.0.2.1'),
            CouponIdentityService::pairHash('customer+alias@example.com', '192.0.2.1'));
    }

    public function test_admin_guard_does_not_supply_coupon_customer_identity(): void
    {
        $coupon = $this->reusableCoupon();
        $this->buyWithCoupon('admin@example.com');
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
        $this->assertFalse(app(CouponIdentityService::class)->limitReached($coupon, 'other@example.com'));
        $this->assertNull(app(CouponIdentityService::class)->snapshot('other@example.com')['account_identity_hash']);
    }

    public function test_migration_preserves_legacy_usages_without_inventing_ip_identity(): void
    {
        $migration = require database_path('migrations/2026_10_09_000008_add_coupon_usage_identity.php');
        $migration->down();
        $coupon = $this->reusableCoupon();
        $user = User::factory()->create(['email' => 'Account@Example.com']);
        $guestOrder = $this->order();
        $accountOrder = $this->order($user->id);
        foreach ([[$guestOrder->id, null], [$accountOrder->id, $user->id], [999999, null]] as [$orderId, $userId]) {
            DB::table('coupon_usages')->insert(['coupon_id' => $coupon->id, 'order_id' => $orderId, 'user_id' => $userId]);
        }
        $migration->up();
        $usages = $coupon->usages()->orderBy('id')->get();
        foreach ($usages as $usage) {
            $this->assertNull($usage->identity_hash);
            $this->assertNull($usage->account_identity_hash);
        }
        $this->assertFalse(app(CouponIdentityService::class)->limitReached($coupon, 'customer@example.com'));
        $this->actingAs($user, 'web');
        $this->assertTrue(app(CouponIdentityService::class)->limitReached($coupon, 'customer@example.com'));
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
