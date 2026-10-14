<?php

namespace App\Services;

use App\Jobs\Notifications\SendOutboxNotification;
use App\Mail\ForgotPasswordMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderStatusUpdateMail;
use App\Mail\WelcomeMail;
use App\Models\NotificationOutbox;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class NotificationOutboxService
{
    public function recordAccount(User $user, ?string $resetToken = null): void
    {
        $reset = $resetToken !== null;
        NotificationOutbox::firstOrCreate(['event_key' => $reset ? 'password-reset:'.$user->id.':'.hash('sha256', $resetToken) : 'account-welcome:'.$user->id], [
            'order_id' => null, 'user_id' => $user->id, 'notification_type' => $reset ? 'password_reset' : 'account_welcome',
            'recipient' => $user->email, 'message_id' => Str::uuid().'@mavyn.invalid', 'available_at' => now(),
            'expires_at' => $reset ? now()->addMinutes((int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)) : null,
            'payload' => ['version' => 1, 'user' => ['name' => $user->name, 'email' => $user->email],
                'token' => $reset ? Crypt::encryptString($resetToken) : null, 'brand' => StoreIdentity::snapshot()],
        ]);
    }

    /** Called inside the order transaction. No queue or transport operations here. */
    public function recordConfirmation(Order $order): void
    {
        if (! setting('email_order_confirmation', true)) {
            return;
        }
        $order->loadMissing('items');
        NotificationOutbox::firstOrCreate(['event_key' => 'order-confirmation:'.$order->id], [
            'order_id' => $order->id,
            'notification_type' => 'order_confirmation',
            'recipient' => $order->customer_email,
            'message_id' => Str::uuid().'@mavyn.invalid',
            'available_at' => now(),
            'payload' => [
                'version' => 1,
                'order' => Arr::only($order->getAttributes(), ['id', 'order_number', 'tracking_token',
                    'customer_name', 'payment_method', 'delivery_address', 'delivery_city', 'delivery_zone_id',
                    'subtotal', 'delivery_fee', 'discount_amount', 'tax_amount', 'tax_rate', 'grand_total',
                    'currency', 'currency_symbol', 'brand_snapshot', 'shipping_snapshot', 'payment_instructions_snapshot']),
                'items' => $order->items->map(fn ($item) => Arr::only($item->getAttributes(), [
                    'product_title', 'product_sku', 'variant_name', 'weight', 'options_snapshot',
                    'special_request', 'quantity', 'total_price',
                ]))->all(),
            ],
        ]);
    }

    public function recordStatusUpdate(Order $order, string $previousStatus): void
    {
        if (! setting('email_order_update', true)) {
            return;
        }
        NotificationOutbox::firstOrCreate(['event_key' => 'order-status:'.$order->id.':'.$order->status_revision], [
            'order_id' => $order->id,
            'notification_type' => 'order_status_update',
            'recipient' => $order->customer_email,
            'message_id' => Str::uuid().'@mavyn.invalid',
            'available_at' => now(),
            'payload' => ['version' => 1, 'previous_status' => $previousStatus, 'items' => [],
                'order' => Arr::only($order->getAttributes(), ['id', 'order_number', 'tracking_token',
                    'customer_name', 'status', 'status_revision', 'grand_total', 'delivery_address', 'delivery_city',
                    'currency', 'currency_symbol', 'brand_snapshot'])],
        ]);
    }

    /** Leases dispatch for five minutes; interrupted/lost queue pushes are recoverable. */
    public function dispatchPending(int $limit = 100): array
    {
        NotificationOutbox::whereNull('sent_at')->whereNull('discarded_at')->where('expires_at', '<=', now())
            ->update(['discarded_at' => now(), 'queued_at' => null, 'dispatch_token' => null]);
        $ids = NotificationOutbox::whereNull('sent_at')->whereNull('discarded_at')->where('available_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(5)))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('notification_outbox as earlier')
                    ->whereColumn('earlier.order_id', 'notification_outbox.order_id')
                    ->whereColumn('earlier.id', '<', 'notification_outbox.id')->whereNull('earlier.sent_at');
            })
            ->orderBy('id')->limit($limit)->pluck('id');
        $counts = ['queued' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            $token = DB::transaction(function () use ($id) {
                $entry = NotificationOutbox::whereKey($id)->lockForUpdate()->first();
                if (! $entry || $entry->sent_at || $entry->discarded_at || $entry->available_at->isFuture()
                    || ($entry->queued_at && $entry->queued_at->gt(now()->subMinutes(5)))) {
                    return null;
                }
                $token = (string) Str::uuid();
                $entry->update(['queued_at' => now(), 'dispatch_token' => $token]);

                return $token;
            });
            if (! $token) {
                continue;
            }
            try {
                SendOutboxNotification::dispatch($id)->onConnection(config('notifications.connection'))
                    ->onQueue(config('notifications.queue'));
                $counts['queued']++;
            } catch (Throwable $exception) {
                NotificationOutbox::whereKey($id)->where('dispatch_token', $token)->whereNull('sent_at')
                    ->update(['queued_at' => null, 'dispatch_token' => null, 'last_error' => 'Queue: '.get_class($exception)]);
                Log::warning('Notification queue dispatch failed.', ['outbox_id' => $id, 'exception_type' => get_class($exception)]);
                $counts['failed']++;
            }
        }

        return $counts;
    }

    public function deliver(int $id): void
    {
        // Hold the outbox row lock across send to serialize duplicate jobs on all workers.
        DB::transaction(function () use ($id) {
            $entry = NotificationOutbox::whereKey($id)->lockForUpdate()->first();
            if (! $entry || $entry->sent_at || $entry->discarded_at || $entry->available_at->isFuture()) {
                return;
            }
            if ($entry->expires_at?->isPast()) {
                $entry->update(['discarded_at' => now(), 'queued_at' => null, 'dispatch_token' => null]);

                return;
            }
            // Current read serializes acceptance of this order's notifications.
            $earlier = $entry->order_id === null ? null : NotificationOutbox::where('order_id', $entry->order_id)->where('id', '<', $entry->id)
                ->whereNull('sent_at')->whereNull('discarded_at')->orderBy('id')->lockForUpdate()->first();
            if ($earlier) {
                $entry->update(['queued_at' => null, 'dispatch_token' => null]);

                return;
            }
            $entry->attempts++;
            try {
                if (in_array($entry->notification_type, ['account_welcome', 'password_reset'], true)) {
                    $user = User::find($entry->user_id);
                    $token = $entry->notification_type === 'password_reset' ? Crypt::decryptString($entry->payload['token']) : null;
                    if (! $user || $user->email !== $entry->recipient || ($token !== null && ! Password::broker()->tokenExists($user, $token))) {
                        $entry->update(['discarded_at' => now(), 'queued_at' => null, 'dispatch_token' => null]);

                        return;
                    }
                    $user->name = $entry->payload['user']['name'];
                    $mail = $token !== null ? new ForgotPasswordMail($user, $token, $entry->payload['brand'], $entry->message_id) : new WelcomeMail($user, $entry->payload['brand'], $entry->message_id);
                } else {
                    $order = new Order;
                    $order->setRawAttributes($entry->payload['order'], true);
                    $order->setRelation('items', collect($entry->payload['items'])->map(function ($attributes) {
                        $item = new OrderItem;
                        $item->setRawAttributes($attributes, true);

                        return $item;
                    }));
                    $mail = match ($entry->notification_type) {
                        'order_confirmation' => new OrderConfirmationMail($order, $entry->message_id),
                        'order_status_update' => new OrderStatusUpdateMail($order, $entry->message_id),
                        default => throw new \RuntimeException('Unsupported notification type.'),
                    };
                }
                Mail::to($entry->recipient)->send($mail);
                $entry->sent_at = now();
                $entry->last_error = null;
            } catch (Throwable $exception) {
                // Store the error class only; transport exceptions may contain credentials/PII.
                $entry->last_error = get_class($exception);
                $delay = min(3600, 60 * (2 ** min(6, $entry->attempts - 1)));
                $entry->available_at = now()->addSeconds($delay);
                Log::warning('Notification delivery deferred.', ['outbox_id' => $id, 'exception_type' => get_class($exception)]);
            }
            $entry->queued_at = null;
            $entry->dispatch_token = null;
            $entry->save();
        });
    }
}
