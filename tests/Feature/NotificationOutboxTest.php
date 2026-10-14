<?php

namespace Tests\Feature;

use App\Jobs\Notifications\SendOutboxNotification;
use App\Mail\OrderConfirmationMail;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\NotificationOutbox;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\NotificationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        Setting::set('email_order_confirmation', '1', 'email', 'boolean');
        $category = Category::create(['name' => 'Outbox']);
        $product = Product::create(['category_id' => $category->id, 'title' => 'Original product', 'price' => '0.30', 'is_active' => true, 'in_stock' => true]);
        $product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => '0.30', 'is_default' => true]);
        $zone = DeliveryZone::create(['name' => 'Central', 'delivery_fee' => '0.20', 'is_active' => true]);
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->data = $this->withHoneypot(['first_name' => 'Original', 'last_name' => 'Buyer', 'email' => 'original@example.com',
            'phone' => '03001234567', 'country' => 'Pakistan', 'city' => 'Sialkot', 'address_line' => 'Original street',
            'delivery_zone_id' => $zone->id, 'payment_method' => 'cod']);
    }

    private function place(): NotificationOutbox
    {
        $this->postJson(route('checkout.place-order'), $this->data)->assertOk();

        return NotificationOutbox::sole();
    }

    public function test_legacy_brand_backfill_preserves_orders_and_pending_mail_after_rebranding(): void
    {
        $entry = $this->place();
        Order::query()->update(['brand_snapshot' => null]);
        $payload = $entry->payload;
        unset($payload['order']['brand_snapshot'], $payload['order']['currency'], $payload['order']['currency_symbol']);
        $entry->update(['payload' => $payload]);
        Setting::set('site_name', 'Original Store');
        $migration = require database_path('migrations/2026_10_14_000005_snapshot_legacy_order_branding.php');
        $migration->up();
        Setting::set('site_name', 'Renamed Store');
        $migration->up();
        $this->assertSame('Original Store', Order::sole()->store_name);
        $this->assertSame('Original Store', json_decode($entry->fresh()->payload['order']['brand_snapshot'], true)['name']);
        $this->assertSame('PKR', $entry->fresh()->payload['order']['currency']);
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderConfirmationMail::class, fn ($mail) => $mail->envelope()->from->name === 'Original Store');
    }

    public function test_confirmation_is_durable_before_dispatch_and_replay_does_not_duplicate_it(): void
    {
        $entry = $this->place();
        $this->postJson(route('checkout.place-order'), $this->data)->assertOk()->assertJsonPath('replayed', true);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('notification_outbox', 1);
        app(NotificationOutboxService::class)->recordConfirmation(Order::sole());
        $this->assertSame($entry->message_id, NotificationOutbox::sole()->message_id);
        $this->artisan('notifications:dispatch')->assertSuccessful();
        Queue::assertPushed(SendOutboxNotification::class, fn ($job) => $job->outboxId === $entry->id && $job->connection === 'database');
        app(NotificationOutboxService::class)->deliver($entry->id);
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertNotNull($entry->fresh()->sent_at);
        $this->assertSame(1, $entry->fresh()->attempts);
    }

    public function test_checkout_failure_and_transaction_rollback_leave_no_outbox(): void
    {
        $this->postJson(route('checkout.place-order'), array_replace($this->data, ['delivery_zone_id' => 999999]))->assertUnprocessable();
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('orders', 0);
        DB::beginTransaction();
        $entry = $this->place();
        $this->assertNotNull($entry);
        DB::rollBack();
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
    }

    public function test_disabled_setting_skips_new_notifications_but_does_not_cancel_existing_ones(): void
    {
        $entry = $this->place();
        Setting::set('email_order_confirmation', '0', 'email', 'boolean');
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        NotificationOutbox::query()->delete();
        $this->postJson(route('checkout.place-order'), $this->data)->assertOk();
        $this->assertDatabaseCount('notification_outbox', 0);
        app(NotificationOutboxService::class)->recordConfirmation(Order::sole());
        $this->assertDatabaseCount('notification_outbox', 0);
    }

    public function test_delivery_uses_original_snapshot_even_after_order_edit_and_deletion(): void
    {
        $entry = $this->place();
        $order = Order::sole();
        $order->update(['customer_email' => 'changed@example.com', 'customer_name' => 'Changed Buyer', 'grand_total' => '999.99']);
        $order->items()->update(['product_title' => 'Changed title']);
        $order->delete();
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($entry) {
            $html = $mail->render();
            $this->assertStringContainsString('Original product', $html);
            $this->assertStringContainsString('Original Buyer', $html);
            $this->assertStringNotContainsString('Changed title', $html);
            $this->assertSame('0.50', $mail->order->grand_total);
            $this->assertSame($entry->message_id, $mail->headers()->messageId);

            return $mail->hasTo('original@example.com');
        });
        $this->assertArrayNotHasKey('payment_receipt', $entry->payload['order']);
    }

    public function test_transport_failure_is_saved_and_retried_only_when_due(): void
    {
        $entry = $this->place();
        $originalMail = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Secret SMTP credential and customer email'));
        app(NotificationOutboxService::class)->deliver($entry->id);
        $this->assertNull($entry->fresh()->sent_at);
        $this->assertSame(RuntimeException::class, $entry->fresh()->last_error);
        $this->assertSame(1, $entry->fresh()->attempts);
        Mail::swap($originalMail);
        Mail::fake();
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertNothingSent();
        $this->travel(61)->seconds();
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertSame(2, $entry->fresh()->attempts);
        $this->assertNotNull($entry->fresh()->sent_at);
        $this->assertNull($entry->fresh()->last_error);
    }

    public function test_failed_queue_dispatch_releases_lease_for_recovery(): void
    {
        $entry = $this->place();
        $originalQueue = Queue::getFacadeRoot();
        Queue::shouldReceive('connection')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $counts = app(NotificationOutboxService::class)->dispatchPending();
        $this->assertSame(['queued' => 0, 'failed' => 1], $counts);
        $this->assertNull($entry->fresh()->queued_at);
        $this->assertNull($entry->fresh()->dispatch_token);
        Queue::swap($originalQueue);
        Queue::fake();
        $this->assertSame(['queued' => 1, 'failed' => 0], app(NotificationOutboxService::class)->dispatchPending());
        Queue::assertPushed(SendOutboxNotification::class, 1);
    }

    public function test_dispatch_lease_prevents_repeated_pushes_and_recovers_lost_jobs(): void
    {
        $entry = $this->place();
        app(NotificationOutboxService::class)->dispatchPending();
        app(NotificationOutboxService::class)->dispatchPending();
        Queue::assertPushed(SendOutboxNotification::class, 1);
        $token = $entry->fresh()->dispatch_token;
        $this->travel(301)->seconds();
        app(NotificationOutboxService::class)->dispatchPending();
        Queue::assertPushed(SendOutboxNotification::class, 2);
        $this->assertNotSame($token, $entry->fresh()->dispatch_token);
        app(NotificationOutboxService::class)->deliver($entry->id);
        $this->travel(301)->seconds();
        app(NotificationOutboxService::class)->dispatchPending();
        Queue::assertPushed(SendOutboxNotification::class, 2);
    }

    public function test_database_queue_round_trip_sends_confirmation_outside_checkout(): void
    {
        $entry = $this->place();
        Queue::swap(Queue::getFacadeRoot()->queue);
        app(NotificationOutboxService::class)->dispatchPending();
        $this->assertDatabaseCount('jobs', 1);
        Mail::assertNothingSent();
        $job = Queue::connection('database')->pop('default');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertNotNull($entry->fresh()->sent_at);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_report_only_and_invalid_limits_do_not_queue_mail(): void
    {
        $this->place();
        $this->artisan('notifications:dispatch --report')->assertSuccessful();
        $this->artisan('notifications:dispatch --limit=0')->assertFailed();
        Queue::assertNothingPushed();
    }
}
