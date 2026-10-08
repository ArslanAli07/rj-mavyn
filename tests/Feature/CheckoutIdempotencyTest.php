<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Media\MediaLibraryService;
use App\Services\OrderStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        Setting::set('email_order_confirmation', '1', 'email', 'boolean');
        $category = Category::create(['name' => 'Bags']);
        $this->product = Product::create(['title' => 'Bag', 'category_id' => $category->id, 'price' => 500,
            'inventory_mode' => 'shared', 'stock_quantity' => 10, 'is_active' => true, 'in_stock' => true]);
        $this->product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 500, 'is_default' => true]);
        $this->zone = DeliveryZone::create(['name' => 'Central', 'delivery_fee' => 50, 'is_active' => true]);
    }

    private function add(int $quantity = 2): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
    }

    private function data(array $overrides = []): array
    {
        return $this->withHoneypot(array_replace(['first_name' => 'Test', 'last_name' => 'Buyer', 'email' => 'buyer@example.com',
            'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot', 'address_line' => 'Test street',
            'delivery_zone_id' => $this->zone->id, 'payment_method' => 'cod'], $overrides));
    }

    public function test_retry_after_lost_response_and_cache_session_result_loss_returns_original_order(): void
    {
        $this->add();
        $data = $this->data();
        $first = $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', false)->json();
        session()->forget('placed_order_id');
        Cache::flush();
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('order_id', $first['order_id'])->assertJsonPath('tracking_url', $first['tracking_url']);
        $this->get(route('checkout.success', $first['order_id']))->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
        $this->assertSame(2, $this->product->fresh()->sales_count);
        Mail::assertSent(OrderConfirmationMail::class, 1);
    }

    public function test_committed_retry_ignores_disabled_shop_payment_zone_and_catalog_afterwards(): void
    {
        $this->add();
        $data = $this->data();
        $first = $this->postJson(route('checkout.place-order'), $data)->assertOk()->json('order_id');
        Setting::set('ordering_enabled', '0', 'ordering', 'boolean');
        Setting::set('payment_cod_enabled', '0', 'payment', 'boolean');
        $this->zone->update(['is_active' => false]);
        $this->product->update(['is_active' => false]);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('order_id', $first);
        $this->get(route('checkout.index'))->assertRedirect(route('checkout.success', $first));
        $fresh = $data;
        $fresh['idempotency_key'] = (string) Str::uuid();
        $this->postJson(route('checkout.place-order'), $fresh)->assertForbidden();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_committed_key_rejects_changed_customer_delivery_payment_and_notes(): void
    {
        $this->add();
        $data = $this->data();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        foreach (['email' => 'different@example.com', 'address_line' => 'Another street', 'payment_method' => 'bank_transfer',
            'order_notes' => 'Different order', 'delivery_zone_id' => 999] as $field => $value) {
            $this->postJson(route('checkout.place-order'), array_replace($data, [$field => $value]))->assertStatus(409);
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
        Mail::assertSent(OrderConfirmationMail::class, 1);
    }

    public function test_manual_receipt_retry_preserves_one_private_file_coupon_usage_and_reservation(): void
    {
        $this->add();
        $coupon = Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => 50, 'minimum_order' => 0,
            'is_active' => true, 'usage_limit' => 1, 'per_user_limit' => 1]);
        $this->post(route('cart.coupon'), ['code' => 'SAVE'])->assertSessionHasNoErrors();
        $receipt = UploadedFile::fake()->image('receipt.jpg');
        $data = $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => $receipt]);
        $first = $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $first->assertRedirect(route('checkout.payment-instructions', $order));
        $retry = array_replace($data, ['payment_receipt' => UploadedFile::fake()->createWithContent('renamed.jpg', file_get_contents($receipt->getRealPath()))]);
        $this->post(route('checkout.place-order'), $retry)->assertRedirect(route('checkout.payment-instructions', $order))->assertSessionHasNoErrors();
        unset($retry['payment_receipt']);
        $this->postJson(route('checkout.place-order'), $retry)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame([$order->payment_receipt], Storage::disk('local')->allFiles('receipts'));
        $this->assertSame(1, $coupon->fresh()->usage_count);
        $this->assertDatabaseCount('coupon_usages', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('orders', 1);
        Mail::assertSent(OrderConfirmationMail::class, 1);
    }

    public function test_retry_with_different_receipt_bytes_is_a_conflict(): void
    {
        $this->add();
        $data = $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => UploadedFile::fake()->image('receipt.jpg', 20, 20)]);
        $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $data['payment_receipt'] = UploadedFile::fake()->image('receipt.jpg', 30, 30);
        $this->post(route('checkout.place-order'), $data, ['Accept' => 'application/json'])->assertStatus(409);
        $this->assertCount(1, Storage::disk('local')->allFiles('receipts'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_retry_recovers_an_interrupted_precommit_receipt_write_at_the_same_private_path(): void
    {
        $this->add();
        $receipt = UploadedFile::fake()->image('receipt.jpg');
        $data = $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => $receipt]);
        $path = 'receipts/checkout-'.CheckoutAttempt::firstOrFail()->key_hash;
        Storage::disk('local')->put($path, 'Interrupted upload');
        $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $this->assertSame($path, Order::firstOrFail()->payment_receipt);
        $this->assertSame([$path], Storage::disk('local')->allFiles('receipts'));
        $this->assertSame(hash_file('sha256', $receipt->getRealPath()), hash_file('sha256', Storage::disk('local')->path($path)));
    }

    public function test_committed_retry_still_requires_valid_honeypot_fields(): void
    {
        $this->add();
        $data = $this->data();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($data, filled: true))->assertUnprocessable();
        unset($data[config('honeypot.valid_from_field')]);
        $this->postJson(route('checkout.place-order'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_original_key_does_not_consume_a_new_cart_or_remove_its_coupon(): void
    {
        $this->add();
        $data = $this->data();
        $original = $this->postJson(route('checkout.place-order'), $data)->assertOk()->json('order_id');
        $this->add(1);
        Coupon::create(['code' => 'NEW', 'type' => 'fixed', 'amount' => 10, 'is_active' => true, 'usage_limit' => 5, 'per_user_limit' => 1]);
        $this->post(route('cart.coupon'), ['code' => 'NEW']);
        $next = $this->data();
        $this->assertNotSame($data['idempotency_key'], $next['idempotency_key']);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('order_id', $original);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame('NEW', session('coupon_code'));
        $this->postJson(route('checkout.place-order'), $next)->assertOk()->assertJsonPath('replayed', false);
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(3, $this->product->fresh()->reserved_quantity);
    }

    public function test_foreign_guest_key_cannot_read_or_place_the_original_order(): void
    {
        $this->add();
        $data = $this->data();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $this->withSession(['cart_session_id' => 'another-guest', 'placed_order_id' => null, 'checkout_key' => $data['idempotency_key']]);
        $this->postJson(route('checkout.place-order'), $data)->assertForbidden()->assertJsonMissing(['order_id' => 1]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_authenticated_customer_can_retry_in_another_session_but_other_accounts_cannot(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $this->add();
        $data = $this->data();
        $order = $this->postJson(route('checkout.place-order'), $data)->assertOk()->json('order_id');
        $this->withSession(['cart_session_id' => 'regenerated-session', 'placed_order_id' => null, 'checkout_key' => null]);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('order_id', $order);
        $this->actingAs(User::factory()->create());
        $this->postJson(route('checkout.place-order'), $data)->assertForbidden();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_admin_guard_does_not_bind_checkout_to_a_customer_with_the_same_numeric_id(): void
    {
        $customer = User::factory()->create();
        $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->assertSame($customer->id, $admin->id);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $this->product->id,
            'product_variant_id' => $this->product->variants()->first()->id, 'price' => 500, 'quantity' => 2]);
        $this->actingAs($admin, 'admin');
        $this->add();
        $data = $this->data();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $this->assertNull(Order::firstOrFail()->user_id);
        $this->assertSame(2, CartItem::where('user_id', $customer->id)->sole()->quantity);
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
    }

    public function test_missing_malformed_and_unissued_keys_never_bypass_idempotency(): void
    {
        $this->add();
        $data = $this->data();
        foreach (['', 'invalid', (string) Str::uuid()] as $key) {
            $this->postJson(route('checkout.place-order'), array_replace($data, ['idempotency_key' => $key]))
                ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_validation_failure_does_not_bind_details_and_same_key_can_be_corrected(): void
    {
        $this->add();
        $data = $this->data(['payment_method' => 'bank_transfer']);
        $this->postJson(route('checkout.place-order'), $data)->assertUnprocessable()->assertJsonValidationErrors('payment_receipt');
        $this->assertNull(CheckoutAttempt::firstOrFail()->request_hash);
        $data['address_line'] = 'Corrected street';
        $data['payment_receipt'] = UploadedFile::fake()->image('receipt.jpg');
        $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $this->assertSame('Corrected street', Order::firstOrFail()->delivery_address);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_transaction_failure_leaves_draft_retryable_and_cleans_uncommitted_receipt(): void
    {
        $this->add();
        $data = $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => UploadedFile::fake()->image('receipt.jpg')]);
        $this->mock(MediaLibraryService::class)->shouldReceive('retainOrderImage')->once()->andThrow(new RuntimeException('Injected rollback'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('checkout.place-order'), $data);
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected rollback', $exception->getMessage());
        }
        $this->assertNull(CheckoutAttempt::firstOrFail()->committed_at);
        $this->assertNull(CheckoutAttempt::firstOrFail()->order_id);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->app->forgetInstance(MediaLibraryService::class);
        $this->post(route('checkout.place-order'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('receipts'));
    }

    public function test_price_review_does_not_complete_key_and_resubmission_consumes_it_once(): void
    {
        $this->add();
        $data = $this->data();
        $this->product->variants()->first()->update(['price' => 600]);
        $this->postJson(route('checkout.place-order'), $data)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertNull(CheckoutAttempt::firstOrFail()->committed_at);
        $this->assertDatabaseCount('orders', 0);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', false);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame('1200.00', Order::firstOrFail()->subtotal);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_draft_rejects_changed_cart_and_coupon_until_checkout_is_reviewed_again(): void
    {
        $this->add();
        $data = $this->data();
        $this->add(1);
        $this->postJson(route('checkout.place-order'), $data)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $next = $this->data();
        $this->assertNotSame($data['idempotency_key'], $next['idempotency_key']);
        Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => 10, 'is_active' => true, 'usage_limit' => 5, 'per_user_limit' => 1]);
        $this->post(route('cart.coupon'), ['code' => 'SAVE']);
        $this->postJson(route('checkout.place-order'), $next)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->postJson(route('checkout.place-order'), $this->data())->assertOk();
        $this->assertSame(3, $this->product->fresh()->reserved_quantity);
    }

    public function test_two_different_draft_keys_for_one_cart_cannot_place_two_orders(): void
    {
        $this->add();
        $first = $this->data();
        session()->forget('checkout_key');
        $second = $this->data();
        $this->assertNotSame($first['idempotency_key'], $second['idempotency_key']);
        $this->postJson(route('checkout.place-order'), $first)->assertOk();
        $this->postJson(route('checkout.place-order'), $second)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, CheckoutAttempt::whereNotNull('committed_at')->count());
    }

    public function test_checkout_coupon_edits_refresh_draft_key_without_reusing_completed_keys(): void
    {
        $this->add();
        Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => 10, 'is_active' => true, 'usage_limit' => 5, 'per_user_limit' => 1]);
        $original = $this->data();
        $key = $this->postJson(route('cart.coupon'), ['code' => 'SAVE', 'idempotency_key' => $original['idempotency_key']])
            ->assertOk()->json('idempotency_key');
        $this->assertNotSame($original['idempotency_key'], $key);
        $removed = $this->deleteJson(route('cart.coupon.remove'), ['idempotency_key' => $key])->assertOk()->json('idempotency_key');
        $this->assertNotSame($key, $removed);
        $data = array_replace($original, ['idempotency_key' => $removed]);
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $this->deleteJson(route('cart.coupon.remove'), ['idempotency_key' => $removed])->assertOk()->assertJsonPath('idempotency_key', null);
        $this->postJson(route('checkout.place-order'), $data)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_reload_reuses_draft_and_recovers_completed_order_without_cart(): void
    {
        $this->add();
        $page = $this->get(route('checkout.index'))->assertOk();
        $key = session('checkout_key');
        $page->assertSee('name="idempotency_key" value="'.$key.'"', false);
        $this->get(route('checkout.index'))->assertOk();
        $this->assertSame($key, session('checkout_key'));
        $this->assertDatabaseCount('checkout_attempts', 1);
        $this->postJson(route('checkout.place-order'), $this->data(['idempotency_key' => $key]))->assertOk();
        session()->forget('placed_order_id');
        $this->get(route('checkout.index'))->assertRedirect(route('checkout.success', Order::firstOrFail()));
    }

    public function test_deleted_order_leaves_completed_key_that_cannot_purchase_a_new_cart(): void
    {
        $this->add();
        $data = $this->data();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $order = Order::firstOrFail();
        app(OrderStateService::class)->updateMany([$order->id], ['status' => 'cancelled'], null);
        $order->delete();
        $this->assertNotNull(CheckoutAttempt::firstOrFail()->committed_at);
        $this->assertNull(CheckoutAttempt::firstOrFail()->order_id);
        $this->add();
        $this->postJson(route('checkout.place-order'), $data)->assertStatus(410);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
    }

    public function test_completed_key_still_replays_after_days_with_fresh_honeypot_and_equivalent_scalar_fields(): void
    {
        $this->add();
        $data = $this->data();
        $this->postJson(route('checkout.place-order'), $data)->assertOk();
        $this->travel(10)->days();
        $data['delivery_zone_id'] = (string) $data['delivery_zone_id'];
        $data['map_link'] = '';
        $data['order_notes'] = '';
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($data))->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('orders', 1);
    }
}
