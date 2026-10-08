<?php

namespace Tests\Feature;

use App\Jobs\Notifications\SendOrderConfirmation;
use App\Jobs\Notifications\SendOutboxNotification;
use App\Mail\OrderStatusUpdateMail;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\NotificationOutbox;
use App\Models\Order;
use App\Models\Setting;
use App\Services\NotificationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OrderStatusOutboxTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        Setting::set('email_order_update', '1', 'email', 'boolean');
        $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'long-test-password', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
        $this->order = $this->makeOrder();
    }

    private function makeOrder(bool $managed = false): Order
    {
        return Order::create(['order_number' => 'STATUS-'.uniqid(), 'customer_name' => 'Original Buyer', 'customer_email' => 'original@example.com',
            'customer_phone' => '123', 'delivery_country' => 'Pakistan', 'delivery_city' => 'City', 'delivery_address' => 'Street',
            'subtotal' => 100, 'grand_total' => 100, 'payment_method' => 'cod', 'status' => 'pending', 'payment_status' => 'pending', 'inventory_managed' => $managed]);
    }

    private function change(string $status, bool $notify = true): void
    {
        $this->patch(route('admin.orders.status', $this->order), ['status' => $status, 'payment_status' => 'pending', 'send_email' => $notify,
            'admin_notes' => 'Confidential staff note'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_single_status_change_records_one_snapshot_with_audit_and_no_inline_mail(): void
    {
        $this->change('confirmed');
        $this->change('confirmed');
        $entry = NotificationOutbox::sole();
        $this->assertSame('order_status_update', $entry->notification_type);
        $this->assertSame('order-status:'.$this->order->id.':1', $entry->event_key);
        $this->assertSame('pending', $entry->payload['previous_status']);
        $this->assertSame('confirmed', $entry->payload['order']['status']);
        $this->assertArrayNotHasKey('admin_notes', $entry->payload['order']);
        $this->assertSame(1, $this->order->fresh()->status_revision);
        $this->assertDatabaseHas('admin_audit_logs', ['entity_type' => 'orders', 'entity_id' => (string) $this->order->id, 'action' => 'updated']);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_bulk_status_changes_notify_each_changed_order_once_and_repeated_submission_is_noop(): void
    {
        $other = $this->makeOrder();
        $data = ['ids' => [$this->order->id, $other->id, $other->id], 'bulk_action' => 'set_status', 'status' => 'confirmed', 'send_email' => true];
        $this->post(route('admin.orders.bulk'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.orders.bulk'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notification_outbox', 2);
        $this->assertSame(1, $other->fresh()->status_revision);
        $this->assertCount(2, AdminAuditLog::where('entity_type', 'orders')->get());
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Email customers when status changes');
        Mail::assertNothingSent();
    }

    public function test_opt_in_and_setting_are_both_required_but_disabling_later_does_not_drop_events(): void
    {
        $this->change('confirmed', false);
        $this->assertDatabaseCount('notification_outbox', 0);
        Setting::set('email_order_update', '0', 'email', 'boolean');
        $this->change('preparing');
        $this->assertDatabaseCount('notification_outbox', 0);
        Setting::set('email_order_update', '1', 'email', 'boolean');
        $this->change('out_for_delivery');
        $entry = NotificationOutbox::sole();
        $this->assertSame(3, $entry->payload['order']['status_revision']);
        Setting::set('email_order_update', '0', 'email', 'boolean');
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderStatusUpdateMail::class, 1);
    }

    public function test_return_to_previous_status_creates_distinct_event_and_delivery_is_in_order(): void
    {
        $this->change('confirmed');
        $this->change('preparing');
        $this->change('confirmed'); // Legacy unmanaged orders permit this transition.
        $entries = NotificationOutbox::orderBy('id')->get();
        $this->assertCount(3, $entries);
        $outbox = app(NotificationOutboxService::class);
        $outbox->deliver($entries[2]->id); // Out-of-order duplicate/lost job must wait.
        Mail::assertNothingSent();
        $this->assertSame(0, $entries[2]->fresh()->attempts);
        $outbox->dispatchPending();
        Queue::assertPushed(SendOutboxNotification::class, 1);
        foreach ($entries as $entry) {
            $outbox->deliver($entry->id);
        }
        $this->assertSame(['confirmed', 'preparing', 'confirmed'], Mail::sent(OrderStatusUpdateMail::class)->map(fn ($mail) => $mail->order->status)->all());
        $this->assertSame(3, $entries->pluck('message_id')->unique()->count());
    }

    public function test_snapshot_survives_later_status_recipient_changes_and_order_deletion(): void
    {
        $this->change('confirmed');
        $entry = NotificationOutbox::sole();
        $this->order->update(['status' => 'delivered', 'customer_email' => 'changed@example.com', 'customer_name' => 'Changed Name']);
        $this->order->delete();
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderStatusUpdateMail::class, function ($mail) use ($entry) {
            $html = $mail->render();
            $this->assertStringContainsString('Confirmed', $html);
            $this->assertStringContainsString('Original Buyer', $html);
            $this->assertStringNotContainsString('Confidential staff note', $html);
            $this->assertSame('confirmed', $mail->order->status);
            $this->assertSame($entry->message_id, $mail->headers()->messageId);

            return $mail->hasTo('original@example.com');
        });
    }

    public function test_audit_failure_rolls_back_status_revision_and_outbox(): void
    {
        $this->withoutExceptionHandling();
        AdminAuditLog::creating(function ($log) {
            if ($log->entity_type === 'admin_operations') {
                throw new RuntimeException('Audit failure after status event');
            }
        });
        try {
            $this->change('confirmed');
            $this->fail('Expected audit failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit failure after status event', $exception->getMessage());
        }
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(0, $this->order->fresh()->status_revision);
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_invalid_bulk_transition_rolls_back_all_statuses_and_notifications(): void
    {
        $other = $this->makeOrder(true);
        $other->update(['status' => 'delivered']);
        $this->postJson(route('admin.orders.bulk'), ['ids' => [$this->order->id, $other->id], 'bulk_action' => 'set_status', 'status' => 'confirmed', 'send_email' => true])->assertUnprocessable();
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(0, $this->order->fresh()->status_revision);
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_payment_only_changes_are_silent_except_when_the_domain_cancels_the_order(): void
    {
        $this->post(route('admin.orders.bulk'), ['ids' => [$this->order->id], 'bulk_action' => 'set_payment', 'payment_status' => 'paid', 'send_email' => true])->assertRedirect();
        $this->assertDatabaseCount('notification_outbox', 0);
        $other = $this->makeOrder(true);
        $this->post(route('admin.orders.bulk'), ['ids' => [$other->id], 'bulk_action' => 'set_payment', 'payment_status' => 'failed', 'send_email' => true])->assertRedirect();
        $this->assertSame('cancelled', NotificationOutbox::sole()->payload['order']['status']);
    }

    public function test_unauthorized_staff_cannot_trigger_status_email(): void
    {
        $staff = Admin::create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => 'long-test-password', 'role' => 'custom', 'permissions' => ['orders.view'], 'is_active' => true]);
        $this->actingAs($staff, 'admin')->patchJson(route('admin.orders.status', $this->order), ['status' => 'confirmed', 'payment_status' => 'pending', 'send_email' => true])->assertForbidden();
        $this->postJson(route('admin.orders.bulk'), ['ids' => [$this->order->id], 'bulk_action' => 'set_status', 'status' => 'confirmed', 'send_email' => true])->assertForbidden();
        $this->assertDatabaseCount('notification_outbox', 0);
    }

    public function test_confirmation_precedes_status_and_legacy_jobs_still_work(): void
    {
        $outbox = app(NotificationOutboxService::class);
        $outbox->recordConfirmation($this->order);
        $confirmation = NotificationOutbox::sole();
        $this->change('confirmed');
        $status = NotificationOutbox::where('notification_type', 'order_status_update')->sole();
        $outbox->deliver($status->id);
        Mail::assertNothingSent();
        $legacyJob = unserialize(serialize(new SendOrderConfirmation($confirmation->id)));
        $legacyJob->handle($outbox);
        $this->assertNotNull($confirmation->fresh()->sent_at);
        Queue::swap(Queue::getFacadeRoot()->queue);
        $outbox->dispatchPending();
        $job = Queue::connection('database')->pop('default');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        Mail::assertSent(OrderStatusUpdateMail::class, 1);
        $this->assertNotNull($status->fresh()->sent_at);
    }

    public function test_status_email_retries_transport_failure_with_same_message_id(): void
    {
        $this->change('confirmed');
        $entry = NotificationOutbox::sole();
        $original = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP failure'));
        app(NotificationOutboxService::class)->deliver($entry->id);
        $this->assertNull($entry->fresh()->sent_at);
        Mail::swap($original);
        $this->travel(61)->seconds();
        app(NotificationOutboxService::class)->deliver($entry->id);
        app(NotificationOutboxService::class)->deliver($entry->id);
        Mail::assertSent(OrderStatusUpdateMail::class, 1);
        $this->assertSame(2, $entry->fresh()->attempts);
        Mail::assertSent(OrderStatusUpdateMail::class, fn ($mail) => $mail->messageId === $entry->message_id);
    }
}
