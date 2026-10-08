<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\Media\MediaLibraryService;
use App\Services\OrderStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        Setting::set('email_order_confirmation', '0', 'email', 'boolean');
        $category = Category::create(['name' => 'Accessories']);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Bag', 'price' => 500,
            'inventory_mode' => 'shared', 'stock_quantity' => 5, 'in_stock' => true, 'is_active' => true]);
        $this->variant = $this->product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 500,
            'is_active' => true, 'is_default' => true]);
        $this->zone = DeliveryZone::create(['name' => 'Central', 'delivery_fee' => 0, 'is_active' => true]);
    }

    private function add(int $quantity = 1, ?ProductVariant $variant = null, string $request = ''): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'product_variant_id' => ($variant ?? $this->variant)->id,
            'quantity' => $quantity, 'special_request' => $request])->assertSessionHasNoErrors();
    }

    private function data(array $overrides = []): array
    {
        return $this->withHoneypot(array_replace(['first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'buyer@example.com',
            'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot', 'delivery_zone_id' => $this->zone->id,
            'address_line' => 'Street', 'payment_method' => 'cod'], $overrides));
    }

    private function place(int $quantity = 1): Order
    {
        $this->add($quantity);
        $this->postJson(route('checkout.place-order'), $this->data())->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    private function transition(Order $order, string $status, string $payment = 'pending'): void
    {
        app(OrderStateService::class)->updateMany([$order->id], ['status' => $status, 'payment_status' => $payment], null);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
    }

    public function test_checkout_reserves_without_deducting_on_hand_and_cart_does_not_reserve(): void
    {
        $this->add(2);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->postJson(route('checkout.place-order'), $this->data())->assertOk();
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
        $this->assertSame(3, app(InventoryService::class)->availableQuantity($this->product, $this->variant));
        $this->assertSame('reserved', InventoryReservation::firstOrFail()->state);
        $this->assertSame(1, InventoryMovement::where('kind', 'reserve')->count());
        $this->assertTrue(Order::firstOrFail()->inventory_managed);
    }

    public function test_untracked_products_keep_explicit_availability_and_never_touch_quantities(): void
    {
        $this->product->update(['inventory_mode' => 'untracked', 'stock_quantity' => 0]);
        $order = $this->place(20);
        $this->transition($order, 'confirmed');
        $this->transition($order, 'cancelled');
        $this->assertSame(0, $this->product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->product->update(['in_stock' => false]);
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id])->assertUnprocessable();
    }

    public function test_checkout_aggregates_different_configurations_and_preserves_the_cart_on_insufficient_stock(): void
    {
        $this->add(2, request: 'First');
        $this->add(3, request: 'Second');
        $this->product->update(['stock_quantity' => 4]);
        $this->postJson(route('checkout.place-order'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
    }

    public function test_shared_stock_applies_across_variants_and_cart_reports_excess_quantity_early(): void
    {
        $other = $this->product->variants()->create(['name' => 'Large', 'weight' => 'Large', 'price' => 500]);
        $this->add(3);
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'product_variant_id' => $other->id, 'quantity' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->add(2, $other);
        $this->postJson(route('checkout.place-order'), $this->data())->assertOk();
        $this->assertSame(5, $this->product->fresh()->reserved_quantity);
        $this->assertDatabaseCount('inventory_reservations', 2);
        $this->assertSame(0, app(InventoryService::class)->availableQuantity($this->product, $other));
    }

    public function test_variants_can_have_independent_tracked_or_untracked_stock_and_availability(): void
    {
        $this->product->update(['inventory_mode' => 'variant']);
        $this->variant->update(['track_inventory' => true, 'stock_quantity' => 2]);
        $other = $this->product->variants()->create(['name' => 'Large', 'weight' => 'Large', 'price' => 500,
            'track_inventory' => true, 'stock_quantity' => 3]);
        $made = $this->product->variants()->create(['name' => 'Custom', 'weight' => 'Custom', 'price' => 500]);
        $this->add(2);
        $this->add(3, $other);
        $this->add(10, $made);
        $this->postJson(route('checkout.place-order'), $this->data())->assertOk();
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertSame(2, $this->variant->fresh()->reserved_quantity);
        $this->assertSame(3, $other->fresh()->reserved_quantity);
        $this->assertSame(0, $made->fresh()->reserved_quantity);
        $this->assertNull(app(InventoryService::class)->availableQuantity($this->product, $made));
        $made->update(['in_stock' => false]);
        $this->assertSame(0, app(InventoryService::class)->availableQuantity($this->product, $made));
    }

    public function test_variant_availability_changes_are_revalidated_at_checkout(): void
    {
        $this->add();
        $this->variant->update(['in_stock' => false]);
        $this->postJson(route('checkout.place-order'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_confirmation_consumes_once_and_early_cancellation_restores_once(): void
    {
        $order = $this->place(2);
        $this->transition($order, 'confirmed');
        $this->transition($order, 'confirmed');
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertSame('consumed', InventoryReservation::firstOrFail()->state);
        $this->transition($order, 'preparing');
        $this->transition($order, 'cancelled');
        $this->transition($order, 'cancelled');
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame('restocked', InventoryReservation::firstOrFail()->state);
        $this->assertSame(1, InventoryMovement::where('kind', 'consume')->count());
        $this->assertSame(1, InventoryMovement::where('kind', 'restore')->count());
    }

    public function test_pending_cancellation_releases_once_and_cancelled_orders_cannot_be_reopened(): void
    {
        $order = $this->place(2);
        $this->transition($order, 'cancelled');
        $this->transition($order, 'cancelled');
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertSame(1, InventoryMovement::where('kind', 'release')->count());
        try {
            $this->transition($order, 'confirmed');
            $this->fail('Cancelled order reopened.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_manual_receipt_reservations_do_not_expire_and_payment_confirmation_consumes_them(): void
    {
        $this->add(2);
        $this->post(route('checkout.place-order'), $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => UploadedFile::fake()->image('receipt.jpg')]))->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->travel(10)->days();
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
        $this->assertSame('reserved', InventoryReservation::firstOrFail()->state);
        app(OrderStateService::class)->updateMany([$order->id], ['payment_status' => 'paid'], null);
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        Storage::disk('local')->assertExists($order->payment_receipt);
    }

    public function test_pending_payment_failure_cancels_and_releases_stock(): void
    {
        $order = $this->place(2);
        app(OrderStateService::class)->updateMany([$order->id], ['payment_status' => 'failed'], null);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
    }

    public function test_delivery_does_not_mark_payment_paid_and_dispatched_stock_is_not_automatically_restocked(): void
    {
        $order = $this->place(2);
        $this->transition($order, 'out_for_delivery');
        try {
            $this->transition($order, 'cancelled');
            $this->fail('Dispatched stock was automatically restored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->transition($order, 'delivered');
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->delivered_at);
        app(OrderStateService::class)->updateMany([$order->id], ['payment_status' => 'refunded'], null);
        $this->assertSame(3, $this->product->fresh()->stock_quantity);
    }

    public function test_second_customer_cannot_buy_stock_reserved_by_the_first_customer(): void
    {
        $this->actingAs(User::factory()->create());
        $this->add(3);
        $buyer = User::factory()->create();
        $this->actingAs($buyer);
        $this->place(3);
        $first = CartItem::where('user_id', '!=', $buyer->id)->firstOrFail();
        $this->actingAs(User::findOrFail($first->user_id));
        $this->postJson(route('checkout.place-order'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(3, $this->product->fresh()->reserved_quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_failure_after_reservation_rolls_back_stock_audit_coupon_order_cart_and_receipt(): void
    {
        $this->add(2);
        $coupon = Coupon::create(['code' => 'SAVE', 'type' => 'fixed', 'amount' => 50, 'minimum_order' => 0,
            'is_active' => true, 'usage_limit' => 10, 'per_user_limit' => 1]);
        $this->post(route('cart.coupon'), ['code' => 'SAVE']);
        $this->mock(MediaLibraryService::class)->shouldReceive('retainOrderImage')->once()->andThrow(new RuntimeException('Injected failure after stock reservation'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('checkout.place-order'), $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => UploadedFile::fake()->image('receipt.jpg')]));
            $this->fail('Expected checkout failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Injected', $exception->getMessage());
        }
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertSame(0, $coupon->fresh()->usage_count);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_price_refresh_does_not_reserve_stock(): void
    {
        $this->add(2);
        $this->variant->update(['price' => 600]);
        $this->postJson(route('checkout.place-order'), $this->data())->assertUnprocessable();
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->postJson(route('checkout.place-order'), $this->data())->assertOk();
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
    }

    public function test_stale_admin_stock_edits_and_quantities_below_holds_are_rejected(): void
    {
        $order = $this->place(3);
        $inventory = app(InventoryService::class);
        foreach ([[5, 0], [2, $this->product->fresh()->inventory_version]] as [$quantity, $version]) {
            try {
                DB::transaction(function () use ($inventory, $quantity, $version) {
                    $product = $inventory->lockProducts([$this->product->id])->first();
                    $inventory->configureProduct($product, 'shared', $quantity, $version, null);
                });
                $this->fail('Invalid stock edit accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('stock_quantity', $exception->errors());
            }
        }
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(3, $this->product->fresh()->reserved_quantity);
        $this->transition($order, 'cancelled');
    }

    public function test_inventory_mode_cannot_change_with_open_allocations_and_adjustments_are_audited(): void
    {
        $order = $this->place(2);
        $this->transition($order, 'confirmed');
        $inventory = app(InventoryService::class);
        try {
            DB::transaction(function () use ($inventory) {
                $product = $inventory->lockProducts([$this->product->id])->first();
                $inventory->configureProduct($product, 'untracked', $product->stock_quantity, $product->inventory_version, null);
            });
            $this->fail('Mode changed with open allocations.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inventory_mode', $exception->errors());
        }
        $this->transition($order, 'cancelled');
        $admin = $this->admin();
        DB::transaction(function () use ($inventory, $admin) {
            $product = $inventory->lockProducts([$this->product->id])->first();
            $inventory->configureProduct($product, 'shared', 8, $product->inventory_version, $admin);
        });
        $movement = InventoryMovement::where('kind', 'adjust')->firstOrFail();
        $this->assertSame(3, $movement->stock_delta);
        $this->assertSame($admin->id, $movement->admin_id);
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
    }

    public function test_product_variant_and_order_deletion_protect_allocations_then_preserve_terminal_audit(): void
    {
        $order = $this->place(2);
        foreach ([$this->product, $this->variant, $order] as $model) {
            try {
                $model->delete();
                $this->fail('Allocated record was deleted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('products', 1);
        $this->transition($order, 'cancelled');
        $this->product->delete();
        $this->transition($order, 'cancelled');
        $this->assertSame('cancelled', $order->fresh()->status);
        $order->delete();
        $reservation = InventoryReservation::firstOrFail();
        $this->assertNull($reservation->order_id);
        $this->assertNull($reservation->product_id);
        $this->assertSame('Bag', $reservation->snapshot['product_title']);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_delivered_payment_can_be_updated_after_catalog_deletion_without_moving_stock(): void
    {
        $order = $this->place(2);
        $this->transition($order, 'delivered');
        $this->product->delete();
        app(OrderStateService::class)->updateMany([$order->id], ['payment_status' => 'paid'], null);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('consumed', InventoryReservation::firstOrFail()->state);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_bulk_status_transitions_are_atomic_and_do_not_skip_inventory(): void
    {
        $first = $this->place(2);
        $second = $this->place(2);
        $this->actingAs($this->admin(), 'admin')->post(route('admin.orders.bulk'), ['ids' => [$first->id, $second->id], 'bulk_action' => 'set_status', 'status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->transition($second, 'out_for_delivery');
        $this->post(route('admin.orders.bulk'), ['ids' => [$first->id, $second->id], 'bulk_action' => 'set_status', 'status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame('out_for_delivery', $second->fresh()->status);
        $this->assertSame(1, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, InventoryMovement::where('kind', 'restore')->count());
    }

    public function test_cached_storefront_availability_reflects_current_stock_and_disabled_variants(): void
    {
        $cached = $this->product->load('variants');
        Cache::put('product_'.$this->product->slug, $cached, 600);
        $this->place(5);
        $this->assertFalse($cached->available_for_sale);
        $this->get(route('product.show', $this->product->slug))->assertOk()->assertSee('Out of Stock');
        $this->assertSame(0, app(InventoryService::class)->availableQuantity($cached, $this->variant));
    }

    private function productPayload(array $overrides = []): array
    {
        return array_replace(['category_id' => $this->product->category_id, 'title' => 'Bag', 'stock_quantity' => 5,
            'inventory_mode' => 'shared', 'inventory_version' => $this->product->fresh()->inventory_version,
            'in_stock' => 1, 'is_active' => 1, 'display_order' => 0,
            'variants' => [['id' => $this->variant->id, 'name' => 'Standard', 'price' => 500, 'is_default' => 1, 'is_active' => 1]]], $overrides);
    }

    public function test_admin_can_configure_and_inspect_shared_stock_with_audited_changes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->put(route('admin.products.update', $this->product), $this->productPayload(['stock_quantity' => 8]))
            ->assertSessionHasNoErrors();
        $this->assertSame(8, $this->product->fresh()->stock_quantity);
        $this->assertSame($admin->id, InventoryMovement::where('kind', 'adjust')->firstOrFail()->admin_id);
        $this->get(route('admin.products.edit', $this->product))->assertOk()->assertSee('Recent stock movements')->assertSee('Owner');
        $this->get(route('admin.products.create'))->assertOk()->assertSee('Shared stock across variants');
    }

    public function test_stale_admin_submission_rolls_back_product_content_changes(): void
    {
        $stale = $this->productPayload(['title' => 'Stale overwrite']);
        $this->place(2);
        $this->actingAs($this->admin(), 'admin')->put(route('admin.products.update', $this->product), $stale)->assertSessionHasErrors('stock_quantity');
        $this->assertSame('Bag', $this->product->fresh()->title);
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
    }

    public function test_admin_cannot_remove_a_variant_with_an_active_allocation(): void
    {
        $this->place(2);
        $this->actingAs($this->admin(), 'admin')->put(route('admin.products.update', $this->product), $this->productPayload([
            'title' => 'Rejected change', 'variants' => [['name' => 'Replacement', 'price' => 500, 'is_default' => 1, 'is_active' => 1]],
        ]))->assertSessionHasErrors('variants');
        $this->assertSame('Bag', $this->product->fresh()->title);
        $this->assertSame(1, $this->product->variants()->count());
        $this->assertDatabaseHas('product_variants', ['id' => $this->variant->id]);
    }

    public function test_single_order_admin_updates_use_the_same_inventory_transition_rules(): void
    {
        $order = $this->place(2);
        $this->actingAs($this->admin(), 'admin');
        foreach (['confirmed', 'confirmed', 'cancelled', 'cancelled'] as $status) {
            $this->patch(route('admin.orders.status', $order), ['status' => $status, 'payment_status' => 'pending'])->assertSessionHasNoErrors();
        }
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::where('kind', 'consume')->count());
        $this->assertSame(1, InventoryMovement::where('kind', 'restore')->count());
    }

    public function test_deleting_active_orders_does_not_unlink_their_private_receipts(): void
    {
        $this->add(2);
        $this->post(route('checkout.place-order'), $this->data(['payment_method' => 'bank_transfer', 'payment_receipt' => UploadedFile::fake()->image('receipt.jpg')]))->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->actingAs($this->admin(), 'admin')->post(route('admin.orders.bulk'), ['ids' => [$order->id], 'bulk_action' => 'delete'])->assertSessionHasErrors('inventory');
        Storage::disk('local')->assertExists($order->payment_receipt);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSame(2, $this->product->fresh()->reserved_quantity);
    }
}
