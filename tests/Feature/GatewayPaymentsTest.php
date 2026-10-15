<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Services\CheckoutAttemptService;
use App\Services\OrderStateService;
use App\Services\Payments\PaymentService;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginRoutes;
use App\Services\Plugins\PluginRuntime;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\Payments;
use Mavyn\Contracts\Payments\RefundResult;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Fixtures\Gateway\FakeGateway;
use Tests\TestCase;

/** Each test uses a fresh in-memory database, without a surrounding test transaction. */
class GatewayPaymentsTest extends TestCase
{
    private string $catalogPath;

    private string $themesPath;

    private Admin $actor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force' => true]);
        $this->catalogPath = tempnam(sys_get_temp_dir(), 'mavyn-gateway-plugins-');
        $this->themesPath = tempnam(sys_get_temp_dir(), 'mavyn-gateway-themes-');
        $base = ['format' => 1, 'lock_hash' => hash_file('sha256', base_path('composer.lock'))];
        $manifest = json_decode(file_get_contents(base_path('tests/Fixtures/Gateway/composer.json')), true)['extra']['mavyn'];
        file_put_contents($this->catalogPath, json_encode([...$base, 'packages' => [['package' => 'test/gateway', 'version' => '1.0.0', 'path' => 'tests/Fixtures/Gateway', 'manifest' => $manifest]]], JSON_THROW_ON_ERROR));
        $theme = json_decode(file_get_contents(base_path('resources/themes/default/composer.json')), true);
        file_put_contents($this->themesPath, json_encode([...$base, 'packages' => [['package' => $theme['name'], 'version' => '0.1.0', 'path' => 'resources/themes/default', 'manifest' => $theme['extra']['mavyn']]]], JSON_THROW_ON_ERROR));
        config(['extensions.catalog' => $this->catalogPath, 'extensions.live_manifests' => false, 'themes.catalog' => $this->themesPath]);
        DB::table('plugin_states')->insert(['plugin_id' => 'test.gateway', 'version' => '1.0.0', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('extension_runtime')->where('id', 1)->increment('revision');
        app(PluginRuntime::class)->forget();
        app(PluginRoutes::class)->register();
        Route::getRoutes()->refreshNameLookups();
        $this->actor = Admin::create(['name' => 'Gateway owner', 'email' => 'gateway@example.test', 'password' => 'fixture-only-password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($this->actor, 'admin');
        auth()->shouldUse('web');
        $category = Category::create(['name' => 'Jackets', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'title' => 'Gateway jacket', 'price' => '150.00', 'is_active' => true, 'in_stock' => true, 'inventory_mode' => 'shared', 'stock_quantity' => 5]);
        $this->product->variants()->create(['name' => 'Regular', 'weight' => 'Regular', 'price' => '150.00', 'is_default' => true, 'is_active' => true]);
        DeliveryZone::create(['name' => 'Fixture delivery', 'delivery_fee' => '5.00', 'is_active' => true]);
        FakeGateway::$sessions = FakeGateway::$calls = FakeGateway::$states = FakeGateway::$refunds = [];
        FakeGateway::$timeout = false;
    }

    protected function tearDown(): void
    {
        unlink($this->catalogPath);
        unlink($this->themesPath);
        parent::tearDown();
    }

    private function place(): array
    {
        $this->postJson(route('cart.add'), ['product_id' => $this->product->id, 'quantity' => 1])->assertOk();
        $input = ['idempotency_key' => app(CheckoutAttemptService::class)->issue(),
            'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '03001234567',
            'country' => 'PK', 'city' => 'City', 'address_line' => 'Fixture street', 'shipping_option' => 'test.gateway.delivery:standard', 'payment_method' => 'fixture_card'];
        $quote = $this->postJson(route('checkout.quote'), $input)->assertOk();
        $input['review_token'] = $quote->json('review_token');
        $response = $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()->assertJsonPath('payment_action.type', 'redirect');
        $order = Order::findOrFail($response->json('order_id'));

        return [$order, app(PaymentService::class)->forOrder($order->id)->id, $input];
    }

    private function verifiedResult(string $id, string $status, string $event = 'verified'): PaymentResult
    {
        $context = app(Payments::class)->context($id);

        return new PaymentResult($id, $event, $status, $context->amountMinor, $context->currency, $context->reference ?? 'payment-'.$id);
    }

    private function rejects(callable $call, int $status): void
    {
        try {
            $call();
            $this->fail('Operation unexpectedly accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_order_commit_records_intent_without_gateway_calls_and_start_is_owned(): void
    {
        [$order, $id] = $this->place();
        $this->assertSame([], FakeGateway::$calls);
        $this->get(route('payments.show', $order))->assertOk()->assertSee('Payment for '.$order->order_number)->assertSee('PKR');
        $this->assertDatabaseHas('gateway_payment_attempts', ['id' => $id, 'amount_minor' => 15500, 'currency' => 'PKR', 'status' => 'created']);
        $this->postJson(route('payments.start', $order))->assertOk()->assertJsonPath('payment_action.type', 'redirect');
        $this->assertSame([['initiate', $id, 0]], FakeGateway::$calls);
        $this->postJson(route('payments.start', $order))->assertOk();
        $this->assertCount(1, FakeGateway::$calls);
        $this->withSession(['placed_order_id' => null])->postJson(route('payments.start', $order))->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_unknown_creation_outcome_retries_the_same_operation_and_scheduler_needs_no_browser(): void
    {
        [$order, $id, $input] = $this->place();
        FakeGateway::$timeout = true;
        app(PaymentService::class)->processDue();
        $this->assertDatabaseHas('gateway_payment_attempts', ['id' => $id, 'status' => 'created', 'last_error_class' => \RuntimeException::class]);
        $this->assertCount(1, FakeGateway::$sessions);
        $this->travel(2)->minutes();
        app(PaymentService::class)->processDue();
        $this->assertCount(1, FakeGateway::$sessions);
        $this->assertSame($id, FakeGateway::$calls[1][1]);
        $this->assertDatabaseHas('gateway_payment_attempts', ['id' => $id, 'status' => 'pending']);
        $this->postJson(route('checkout.place-order'), $this->withHoneypot($input))->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('gateway_payment_attempts', 1);
    }

    public function test_verified_paid_is_exactly_applied_once_and_cannot_regress(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $paid = $this->verifiedResult($id, 'paid', 'paid-event');
        $payments->apply('test.gateway.card', $paid);
        $payments->apply('test.gateway.card', $paid);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'failed', 'older-failure'));
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'processing', 'older-processing'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'pending']);
        $this->assertSame(4, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseHas('gateway_payment_attempts', ['id' => $id, 'captured' => true]);
    }

    public function test_amount_currency_reference_and_duplicate_event_conflicts_reject_atomically(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $this->rejects(fn () => $payments->apply('test.gateway.card', new PaymentResult($id, 'bad', 'paid', 15501, 'PKR', 'payment-'.$id)), 409);
        $this->rejects(fn () => $payments->apply('test.gateway.card', new PaymentResult($id, 'bad', 'paid', 15500, 'USD', 'payment-'.$id)), 409);
        $this->rejects(fn () => $payments->apply('other.gateway.card', $this->verifiedResult($id, 'paid')), 409);
        $this->assertDatabaseCount('gateway_events', 0);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'processing', 'same-event'));
        $this->rejects(fn () => $payments->apply('test.gateway.card', $this->verifiedResult($id, 'paid', 'same-event')), 409);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_failure_is_retryable_but_expiry_releases_stock_and_late_success_needs_review(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'failed', 'declined'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'payment_status' => 'pending']);
        $this->assertSame(1, $this->product->fresh()->reserved_quantity);
        app(PaymentService::class)->start($order);
        $second = app(PaymentService::class)->forOrder($order->id)->id;
        $this->assertNotSame($id, $second);
        $payments->apply('test.gateway.card', $this->verifiedResult($second, 'expired', 'expired'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
        $payments->apply('test.gateway.card', $this->verifiedResult($second, 'paid', 'late-paid'));
        $this->assertDatabaseHas('gateway_payment_attempts', ['id' => $second, 'captured' => true, 'needs_review' => true]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'paid']);
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
    }

    public function test_a_declined_attempt_can_later_expire_without_leaking_reservations(): void
    {
        [$order, $id] = $this->place();
        app(Payments::class)->apply('test.gateway.card', $this->verifiedResult($id, 'failed', 'declined'));
        FakeGateway::$states[$id] = 'expired';
        app(PaymentService::class)->processDue();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertSame(0, $this->product->fresh()->reserved_quantity);
    }

    public function test_partial_and_full_refunds_are_permissioned_durable_and_idempotent(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'paid', 'paid'));
        app(OrderStateService::class)->updateMany([$order->id], ['status' => 'confirmed'], $this->actor);
        $operation = (string) Str::uuid();
        $payments->requestRefund($id, 5000, $operation);
        $payments->requestRefund($id, 5000, $operation);
        $this->assertSame([], FakeGateway::$refunds);
        app(PaymentService::class)->processDue();
        $this->assertCount(1, FakeGateway::$refunds);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->rejects(fn () => $payments->requestRefund($id, 10600, (string) Str::uuid()), 422);
        $payments->requestRefund($id, 10500, (string) Str::uuid());
        app(PaymentService::class)->processDue();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded', 'status' => 'confirmed']);
        $this->assertSame(4, $this->product->fresh()->stock_quantity); // Refund is not a stock return.
        $this->assertDatabaseCount('gateway_refunds', 2);
        $history = $payments->history($order->id);
        $this->assertCount(2, $history[0]['refunds']);
        $this->assertArrayNotHasKey('action', $history[0]);
        foreach (FakeGateway::$calls as $call) {
            $this->assertSame(0, $call[2]);
        }
        $this->actor->update(['role' => 'custom', 'permissions' => []]);
        $this->rejects(fn () => $payments->requestRefund($id, 1, (string) Str::uuid()), 403);
        $this->rejects(fn () => $payments->history($order->id), 403);
    }

    public function test_verified_external_refund_can_be_imported_without_an_admin_session(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'paid', 'paid'));
        auth('admin')->forgetUser();
        $result = new RefundResult((string) Str::uuid(), 'external-refund', 'succeeded', 15500, 'PKR', 'external-ref-1', $id);
        $payments->applyRefund('test.gateway.card', $result);
        $payments->applyRefund('test.gateway.card', $result);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded']);
        $this->assertDatabaseCount('gateway_refunds', 1);
    }

    public function test_callbacks_verify_signatures_and_continue_after_deactivation(): void
    {
        [$order, $id] = $this->place();
        app(PluginManager::class)->change('test.gateway', false, $this->actor);
        $body = json_encode((array) $this->verifiedResult($id, 'paid', 'signed-event'), JSON_THROW_ON_ERROR);
        $url = route('plugin.test.gateway.callbacks.events');
        $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);
        $signature = hash_hmac('sha256', $body, 'fixture-only-secret');
        $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FIXTURE_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $middleware = Route::getRoutes()->getByName('plugin.test.gateway.callbacks.events')->gatherMiddleware();
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains('plugin.active:test.gateway', $middleware);
    }

    public function test_unpaid_fulfillment_and_manual_payment_status_edits_are_rejected(): void
    {
        [$order, $id] = $this->place();
        try {
            app(OrderStateService::class)->updateMany([$order->id], ['status' => 'confirmed'], $this->actor);
            $this->fail('Unpaid order fulfilled.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        try {
            app(OrderStateService::class)->updateMany([$order->id], ['payment_status' => 'paid'], $this->actor);
            $this->fail('Manual payment override accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_status', $e->errors());
        }
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
        app(Payments::class)->apply('test.gateway.card', $this->verifiedResult($id, 'paid', 'paid'));
        app(OrderStateService::class)->updateMany([$order->id], ['status' => 'confirmed'], $this->actor);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_gateway_work_cannot_run_inside_an_outer_transaction(): void
    {
        [$order, $id] = $this->place();
        $this->expectException(\LogicException::class);
        DB::transaction(fn () => app(PaymentService::class)->initiate($id));
    }

    public function test_a_delayed_old_success_does_not_start_the_new_uninitiated_attempt(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'failed', 'declined'));
        FakeGateway::$timeout = true;
        app(PaymentService::class)->start($order);
        $new = app(PaymentService::class)->forOrder($order->id)->id;
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'paid', 'late-success'));
        $this->travel(2)->minutes();
        app(PaymentService::class)->processDue();
        $this->assertSame('reconcile', FakeGateway::$calls[1][0]);
        $this->assertSame($new, FakeGateway::$calls[1][1]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
    }

    public function test_disputes_block_fulfillment_and_payment_history_cannot_be_deleted(): void
    {
        [$order, $id] = $this->place();
        $payments = app(Payments::class);
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'paid', 'paid'));
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'disputed', 'dispute'));
        try {
            app(OrderStateService::class)->updateMany([$order->id], ['status' => 'confirmed'], $this->actor);
            $this->fail('Disputed order fulfilled.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $payments->apply('test.gateway.card', $this->verifiedResult($id, 'review_cleared', 'dispute-won'));
        app(OrderStateService::class)->updateMany([$order->id], ['status' => 'confirmed'], $this->actor);
        try {
            $order->delete();
            $this->fail('Payment history deleted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }
    }
}
