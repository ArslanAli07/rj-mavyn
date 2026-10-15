<?php

namespace App\Services\Payments;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Services\OrderStateService;
use App\Services\PaymentMethodService;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mavyn\Contracts\Payments\GatewayProvider;
use Mavyn\Contracts\Payments\PaymentAction;
use Mavyn\Contracts\Payments\PaymentContext;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\Payments;
use Mavyn\Contracts\Payments\RefundContext;
use Mavyn\Contracts\Payments\RefundProvider;
use Mavyn\Contracts\Payments\RefundResult;
use Mavyn\Core\Money;
use Mavyn\Core\Pricing\QuoteFingerprint;

/** Durable gateway boundary. External calls are deliberately outside database transactions. */
final class PaymentService implements Payments
{
    public const OPEN = ['created', 'pending', 'processing'];

    public const RECONCILE = ['created', 'pending', 'processing', 'failed'];

    /** Called only inside the authoritative order transaction; no network work. */
    public function record(Order $order, string $providerKey, string $providerClass, string $owner): string
    {
        if (DB::transactionLevel() < 1 || ! is_subclass_of($providerClass, GatewayProvider::class)) {
            throw new \LogicException('Record a gateway intent inside order placement.');
        }
        $plugins = array_keys(app(InstalledPluginCatalog::class)->load()->all());
        usort($plugins, fn ($a, $b) => strlen($b) <=> strlen($a));
        $plugin = collect($plugins)->first(fn ($id) => str_starts_with($providerKey, $id.'.'));
        if (! $plugin) {
            throw new \LogicException('Gateway must belong to an installed plugin.');
        }
        $id = (string) Str::uuid();
        DB::table('gateway_payment_attempts')->insert([
            'id' => $id, 'order_id' => $order->id, 'sequence' => (int) (DB::table('gateway_payment_attempts')->where('order_id', $order->id)->orderByDesc('sequence')->lockForUpdate()->value('sequence') ?? 0) + 1, 'plugin_id' => $plugin, 'provider_key' => $providerKey,
            'provider_class' => $providerClass, 'amount_minor' => Money::minor($order->grand_total),
            'currency' => $order->currency, 'owner_reference' => $owner, 'status' => 'created',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function context(string $attemptId): PaymentContext
    {
        $query = DB::table('gateway_payment_attempts')->where('id', $attemptId);
        $row = (DB::transactionLevel() > 0 ? $query->lockForUpdate() : $query)->first();
        abort_unless($row, 404);
        $orders = Order::query();
        $order = (DB::transactionLevel() > 0 ? $orders->lockForUpdate() : $orders)->findOrFail($row->order_id);

        return new PaymentContext($row->id, $order->id, $order->order_number, (int) $row->amount_minor, $row->currency,
            $row->owner_reference, $order->customer_email, route('payments.show', $order->id), route('payments.show', $order->id), $row->status, $row->reference, $order->status);
    }

    public function forOrder(int $orderId): ?object
    {
        $query = DB::table('gateway_payment_attempts')->where('order_id', $orderId)->orderByDesc('sequence');

        return (DB::transactionLevel() > 0 ? $query->lockForUpdate() : $query)->first();
    }

    public function checkoutAction(Order $order): ?array
    {
        return $this->forOrder($order->id) && ! in_array($order->payment_status, ['paid', 'refunded'], true)
            ? ['type' => 'redirect', 'url' => route('payments.show', $order->id)] : null;
    }

    /** Customer ownership is enforced by the host controller. */
    public function start(Order $order): PaymentAction
    {
        $this->outsideTransaction();
        $id = DB::transaction(function () use ($order) {
            $this->lock();
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_if($order->status === 'cancelled' || in_array($order->payment_status, ['paid', 'refunded'], true), 409, 'This order cannot start another payment.');
            $attempt = $this->forOrder($order->id);
            abort_unless($attempt, 404);
            if ($attempt->status === 'failed') {
                $method = app(PaymentMethodService::class)->choose($order->payment_method, $order->currency);
                abort_unless(($method['gateway'] ?? false) && $method['provider_key'] === $attempt->provider_key, 409, 'The original gateway is unavailable.');

                return $this->record($order, $attempt->provider_key, $method['provider_class'], $attempt->owner_reference);
            }

            return $attempt->id;
        }, 3);

        return $this->initiate($id);
    }

    /** Safe for scheduler/customer races: leased calls also share a permanent gateway idempotency key. */
    public function initiate(string $id): PaymentAction
    {
        $this->outsideTransaction();
        $row = DB::table('gateway_payment_attempts')->where('id', $id)->first();
        abort_unless($row, 404);
        if ($row->action && in_array($row->status, ['pending', 'processing'], true)) {
            $action = json_decode($row->action, true, 512, JSON_THROW_ON_ERROR);

            return new PaymentAction(...$action);
        }
        $lease = $this->claim('gateway_payment_attempts', $id, ['created']);
        if (! $lease) {
            return new PaymentAction('pending');
        }
        try {
            $action = $this->provider($id)->initiate($this->context($id));
            DB::transaction(function () use ($id, $lease, $action) {
                $this->lock();
                $row = DB::table('gateway_payment_attempts')->where('id', $id)->lockForUpdate()->first();
                if ($row->lease_token !== $lease) {
                    return;
                }
                if ($row->reference !== null && $action->reference !== null && $row->reference !== $action->reference) {
                    throw new \RuntimeException('Gateway changed the persistent payment reference.');
                }
                $changes = ['lease_until' => null, 'lease_token' => null, 'last_error_class' => null, 'updated_at' => now(), 'retry_at' => now()->addMinute()];
                if ($row->status === 'created') {
                    $changes += ['status' => 'pending', 'action' => json_encode((array) $action, JSON_THROW_ON_ERROR), 'reference' => $action->reference ?? $row->reference];
                }
                DB::table('gateway_payment_attempts')->where('id', $id)->update($changes);
            }, 3);

            return in_array($this->context($id)->status, ['pending', 'processing'], true) ? $action : new PaymentAction('pending');
        } catch (\Throwable $error) {
            $this->release('gateway_payment_attempts', $id, $lease, $error);

            return new PaymentAction('pending');
        }
    }

    public function apply(string $providerKey, PaymentResult $result): void
    {
        DB::transaction(function () use ($providerKey, $result) {
            $this->lock();
            $row = DB::table('gateway_payment_attempts')->where('id', $result->attemptId)->lockForUpdate()->first();
            abort_unless($row && $row->provider_key === $providerKey, 409, 'Payment identity does not match.');
            $this->money($row, $result->amountMinor, $result->currency);
            if ($row->reference !== null && $result->reference !== null && $row->reference !== $result->reference) {
                abort(409, 'Payment reference does not match.');
            }
            if (! $this->event($providerKey, $result->eventId, (array) $result)) {
                return;
            }
            $order = Order::lockForUpdate()->findOrFail($row->order_id);
            $changes = ['updated_at' => now(), 'reference' => $result->reference ?? $row->reference];
            $reasons = $this->reviewReasons($row);
            if ($result->status === 'paid') {
                if (! $row->captured && ($order->status === 'cancelled' || $order->payment_status === 'refunded')) {
                    $reasons[] = 'late_capture';
                }
                $changes += ['captured' => true, 'status' => 'paid', 'reference' => $result->reference, 'action' => null];
            } elseif ($result->status === 'review_cleared') {
                abort_unless($row->captured, 409, 'Review resolution requires a captured payment.');
                $reasons = array_values(array_diff($reasons, ['dispute']));
            } elseif ($result->status === 'disputed') {
                abort_unless($row->captured, 409, 'A dispute requires a captured payment.');
                $reasons[] = 'dispute';
            } elseif (! $row->captured && (in_array($row->status, self::OPEN, true) || ($row->status === 'failed' && in_array($result->status, ['expired', 'cancelled'], true)))) {
                $changes += ['status' => $result->status];
                if (in_array($result->status, ['expired', 'cancelled'], true)) {
                    $other = DB::table('gateway_payment_attempts')->where('order_id', $order->id)->where('id', '!=', $row->id)->whereIn('status', self::OPEN)->lockForUpdate()->get(['id'])->isNotEmpty();
                    if (! $other && $this->forOrder($order->id)->id === $row->id && $order->status === 'pending' && $order->payment_status === 'pending') {
                        $this->orderState($order, ['status' => 'cancelled', 'payment_status' => 'failed']);
                    }
                }
                // A retryable failure stays pending on the order and retains its reservation.
            }
            DB::table('gateway_payment_attempts')->where('id', $row->id)->update($changes + $this->reviewChanges($reasons));
            $this->refreshFinancialState($order->id);
        }, 3);
    }

    public function requestRefund(string $attemptId, int $amountMinor, string $operationId): RefundContext
    {
        $actor = auth('admin')->user();
        abort_unless($actor && $actor->is_active && $actor->hasPermission('orders.payment'), 403);
        abort_unless(Str::isUuid($operationId) && $amountMinor > 0, 422, 'Use a positive refund amount and UUID operation identity.');

        return DB::transaction(function () use ($attemptId, $amountMinor, $operationId, $actor) {
            $this->lock();
            $attempt = DB::table('gateway_payment_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            abort_unless($attempt && $attempt->captured && is_subclass_of($attempt->provider_class, RefundProvider::class), 409, 'This captured payment does not support refunds.');
            $prior = DB::table('gateway_refunds')->where('id', $operationId)->lockForUpdate()->first();
            if ($prior) {
                abort_unless($prior->attempt_id === $attemptId && (int) $prior->amount_minor === $amountMinor && (int) $prior->actor_id === $actor->id, 409, 'Refund identity already used for different changes.');

                return $this->refundContext($operationId);
            }
            $reserved = (int) DB::table('gateway_refunds')->where('attempt_id', $attemptId)->whereIn('status', ['created', 'pending', 'succeeded'])->lockForUpdate()->get(['amount_minor'])->sum('amount_minor');
            abort_if($amountMinor > (int) $attempt->amount_minor - $reserved, 422, 'Refund exceeds the unrefunded captured amount.');
            DB::table('gateway_refunds')->insert(['id' => $operationId, 'attempt_id' => $attemptId, 'actor_id' => $actor->id,
                'amount_minor' => $amountMinor, 'currency' => $attempt->currency, 'status' => 'created', 'created_at' => now(), 'updated_at' => now()]);
            AdminAuditLog::create(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'route' => request()->route()?->getName() ?? 'console',
                'action' => 'refund_requested', 'entity_type' => 'gateway_refunds', 'entity_id' => $operationId,
                'changes' => ['attempt_id' => $attemptId, 'amount_minor' => $amountMinor, 'currency' => $attempt->currency]]);

            return $this->refundContext($operationId);
        }, 3);
    }

    public function applyRefund(string $providerKey, RefundResult $result): void
    {
        DB::transaction(function () use ($providerKey, $result) {
            $this->lock();
            $refund = DB::table('gateway_refunds')->where('id', $result->refundId)->lockForUpdate()->first();
            if (! $refund) {
                abort_unless($result->attemptId && Str::isUuid($result->refundId) && $result->reference, 404);
                $attempt = DB::table('gateway_payment_attempts')->where('id', $result->attemptId)->lockForUpdate()->first();
                abort_unless($attempt && $attempt->captured && $attempt->provider_key === $providerKey && $attempt->currency === $result->currency, 409, 'External refund identity does not match.');
                $returned = (int) DB::table('gateway_refunds')->where('attempt_id', $attempt->id)->where('status', 'succeeded')->lockForUpdate()->get(['amount_minor'])->sum('amount_minor');
                abort_if($returned + $result->amountMinor > (int) $attempt->amount_minor, 409, 'External refund exceeds captured amount.');
                DB::table('gateway_refunds')->insert(['id' => $result->refundId, 'attempt_id' => $attempt->id, 'actor_id' => null,
                    'amount_minor' => $result->amountMinor, 'currency' => $result->currency, 'status' => 'pending',
                    'reference' => $result->reference, 'created_at' => now(), 'updated_at' => now()]);
                $refund = DB::table('gateway_refunds')->where('id', $result->refundId)->first();
            }
            $attempt = DB::table('gateway_payment_attempts')->where('id', $refund->attempt_id)->lockForUpdate()->first();
            abort_unless($attempt->provider_key === $providerKey, 409, 'Refund provider does not match.');
            $this->money($refund, $result->amountMinor, $result->currency);
            abort_if($refund->reference !== null && $result->reference !== null && $refund->reference !== $result->reference, 409, 'Refund reference does not match.');
            if (! $this->event($providerKey, $result->eventId, (array) $result) || $refund->status === 'succeeded' || ($refund->status === 'failed' && $result->status === 'pending')) {
                return;
            }
            DB::table('gateway_refunds')->where('id', $refund->id)->update(['status' => $result->status, 'reference' => $result->reference ?? $refund->reference, 'updated_at' => now()]);
            $this->refreshFinancialState($attempt->order_id);
        }, 3);
    }

    public function history(int $orderId): array
    {
        $actor = auth('admin')->user();
        abort_unless($actor && $actor->is_active && $actor->hasPermission('orders.view'), 403);
        Order::findOrFail($orderId);

        return DB::table('gateway_payment_attempts')->where('order_id', $orderId)->orderBy('sequence')->get()->map(function ($row) {
            return ['id' => $row->id, 'provider_key' => $row->provider_key, 'sequence' => (int) $row->sequence,
                'amount_minor' => (int) $row->amount_minor, 'currency' => $row->currency, 'status' => $row->status,
                'reference' => $row->reference, 'captured' => (bool) $row->captured, 'needs_review' => (bool) $row->needs_review,
                'review_reasons' => $this->reviewReasons($row),
                'last_error_class' => $row->last_error_class,
                'refunds' => DB::table('gateway_refunds')->where('attempt_id', $row->id)->orderBy('created_at')->get(['id', 'amount_minor', 'currency', 'status', 'reference'])->map(fn ($refund) => [
                    'id' => $refund->id, 'amount_minor' => (int) $refund->amount_minor, 'currency' => $refund->currency,
                    'status' => $refund->status, 'reference' => $refund->reference,
                ])->all()];
        })->all();
    }

    public function refundContext(string $id): RefundContext
    {
        $query = DB::table('gateway_refunds')->where('id', $id);
        $row = (DB::transactionLevel() > 0 ? $query->lockForUpdate() : $query)->first();
        abort_unless($row, 404);

        return new RefundContext($row->id, $this->context($row->attempt_id), (int) $row->amount_minor, $row->status, $row->reference);
    }

    /** Scheduler is the durable recovery trigger; work remains recorded even if the browser disappears. */
    public function processDue(int $limit = 50): array
    {
        $this->outsideTransaction();
        $processed = 0;
        $limit = max(1, min($limit, 500));
        $attempts = DB::table('gateway_payment_attempts')->whereIn('status', self::RECONCILE)->where(fn ($q) => $q->whereNull('retry_at')->orWhere('retry_at', '<=', now()))->orderBy('created_at')->limit($limit)->get();
        foreach ($attempts as $row) {
            if ($row->status === 'created' && ! in_array(Order::findOrFail($row->order_id)->payment_status, ['paid', 'refunded'], true) && Order::findOrFail($row->order_id)->status !== 'cancelled') {
                $this->initiate($row->id);
            } elseif ($row->status === 'created' && (int) $row->tries === 0) {
                DB::transaction(function () use ($row) {
                    $this->lock();
                    DB::table('gateway_payment_attempts')->where('id', $row->id)->where('tries', 0)->where('status', 'created')->update(['status' => 'cancelled']);
                });
            } elseif ($lease = $this->claim('gateway_payment_attempts', $row->id, self::RECONCILE)) {
                try {
                    $this->apply($row->provider_key, $this->provider($row->id)->reconcile($this->context($row->id)));
                    $this->release('gateway_payment_attempts', $row->id, $lease);
                } catch (\Throwable $error) {
                    $this->release('gateway_payment_attempts', $row->id, $lease, $error);
                }
            }
            $processed++;
        }
        $refunds = DB::table('gateway_refunds')->whereIn('status', ['created', 'pending'])->where(fn ($q) => $q->whereNull('retry_at')->orWhere('retry_at', '<=', now()))->orderBy('created_at')->limit($limit)->get();
        foreach ($refunds as $row) {
            if ($lease = $this->claim('gateway_refunds', $row->id, ['created', 'pending'])) {
                try {
                    $context = $this->refundContext($row->id);
                    if ($row->status === 'created' && in_array('refund_conflict', $this->reviewReasons(DB::table('gateway_payment_attempts')->where('id', $row->attempt_id)->first()), true)) {
                        $this->release('gateway_refunds', $row->id, $lease);

                        continue;
                    }
                    $provider = $this->provider($row->attempt_id);
                    if (! $provider instanceof RefundProvider) {
                        throw new \RuntimeException('Refund provider is unavailable.');
                    }
                    $result = $row->status === 'created' ? $provider->refund($context) : $provider->reconcileRefund($context);
                    $this->applyRefund(DB::table('gateway_payment_attempts')->where('id', $row->attempt_id)->value('provider_key'), $result);
                    $this->release('gateway_refunds', $row->id, $lease);
                } catch (\Throwable $error) {
                    $this->release('gateway_refunds', $row->id, $lease, $error);
                }
            }
            $processed++;
        }

        return ['processed' => $processed, 'review_required' => DB::table('gateway_payment_attempts')->where('needs_review', true)->count()];
    }

    /** Caller holds the currency/extension locks; all accounting uses current locking reads. */
    private function refreshFinancialState(int $orderId): void
    {
        $order = Order::lockForUpdate()->findOrFail($orderId);
        $attempts = DB::table('gateway_payment_attempts')->where('order_id', $orderId)->orderBy('sequence')->lockForUpdate()->get();
        $refunds = DB::table('gateway_refunds')->whereIn('attempt_id', $attempts->pluck('id'))->lockForUpdate()->get();
        $captured = (int) $attempts->where('captured', true)->sum('amount_minor');
        $returned = (int) $refunds->where('status', 'succeeded')->sum('amount_minor');
        $excess = $captured - $returned > Money::minor($order->grand_total);
        foreach ($attempts as $attempt) {
            $ownRefunds = $refunds->where('attempt_id', $attempt->id);
            $ownReturned = (int) $ownRefunds->where('status', 'succeeded')->sum('amount_minor');
            $reserved = (int) $ownRefunds->whereIn('status', ['created', 'pending', 'succeeded'])->sum('amount_minor');
            $reasons = array_values(array_diff($this->reviewReasons($attempt), ['duplicate_capture', 'refund_conflict']));
            if ($excess && $attempt->captured && $ownReturned < (int) $attempt->amount_minor) {
                $reasons[] = 'duplicate_capture';
            }
            if ($reserved > (int) $attempt->amount_minor) {
                $reasons[] = 'refund_conflict';
            }
            if ($attempt->captured && $ownReturned >= (int) $attempt->amount_minor) {
                $reasons = array_values(array_diff($reasons, ['late_capture']));
            }
            DB::table('gateway_payment_attempts')->where('id', $attempt->id)->update($this->reviewChanges($reasons));
        }
        if ($attempts->where('captured', true)->isNotEmpty()) {
            $payment = $captured > 0 && $returned >= $captured ? 'refunded' : 'paid';
            if ($order->payment_status !== $payment) {
                $this->orderState($order, ['payment_status' => $payment]);
            }
        }
    }

    private function reviewReasons(object $row): array
    {
        // Preserve holds whose cause predates the reason journal; never guess that they were disputes.
        return $row->review_reasons === null
            ? ($row->needs_review ? ['legacy_review'] : [])
            : json_decode($row->review_reasons, true, 512, JSON_THROW_ON_ERROR);
    }

    private function reviewChanges(array $reasons): array
    {
        $reasons = array_values(array_unique($reasons));
        sort($reasons);

        return ['needs_review' => $reasons !== [], 'review_reasons' => json_encode($reasons, JSON_THROW_ON_ERROR)];
    }

    private function provider(string $id): GatewayProvider
    {
        $row = DB::table('gateway_payment_attempts')->where('id', $id)->first();
        app(InstalledPluginCatalog::class)->load()->get($row->plugin_id);
        $provider = app()->make($row->provider_class);
        if (! $provider instanceof GatewayProvider) {
            throw new \RuntimeException('Installed gateway provider is unavailable.');
        }

        return $provider;
    }

    private function claim(string $table, string $id, array $states): ?string
    {
        return DB::transaction(function () use ($table, $id, $states) {
            $this->lock();
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->status, $states, true) || ($row->lease_until && now()->lt($row->lease_until)) || ($row->retry_at && now()->lt($row->retry_at))) {
                return null;
            }
            $token = (string) Str::uuid();
            DB::table($table)->where('id', $id)->update(['lease_token' => $token, 'lease_until' => now()->addMinutes(2), 'retry_at' => now()->addMinute(), 'tries' => $row->tries + 1, 'updated_at' => now()]);

            return $token;
        }, 3);
    }

    private function release(string $table, string $id, string $lease, ?\Throwable $error = null): void
    {
        DB::transaction(function () use ($table, $id, $lease, $error) {
            $this->lock();
            DB::table($table)->where('id', $id)->where('lease_token', $lease)->update(['lease_until' => null, 'lease_token' => null,
                'last_error_class' => $error ? get_class($error) : null, 'retry_at' => now()->addMinute(), 'updated_at' => now()]);
        }, 3);
    }

    private function event(string $provider, string $id, array $payload): bool
    {
        $fingerprint = QuoteFingerprint::of($payload);
        $prior = DB::table('gateway_events')->where('provider_key', $provider)->where('event_id', $id)->lockForUpdate()->first();
        if ($prior) {
            abort_unless(hash_equals($prior->fingerprint, $fingerprint), 409, 'Event identity already used for a different result.');

            return false;
        }
        DB::table('gateway_events')->insert(['provider_key' => $provider, 'event_id' => $id, 'fingerprint' => $fingerprint, 'operation_id' => $payload['refundId'] ?? $payload['attemptId'],
            'status' => $payload['status'], 'amount_minor' => $payload['amountMinor'], 'currency' => $payload['currency'], 'created_at' => now()]);

        return true;
    }

    private function money(object $row, int $amount, string $currency): void
    {
        abort_unless((int) $row->amount_minor === $amount && $row->currency === $currency, 409, 'Gateway money does not match the saved operation.');
    }

    private function orderState(Order $order, array $changes): void
    {
        app(OrderStateService::class)->updateMany([$order->id], $changes, null, false, true);
    }

    private function lock(): void
    {
        app(PricingService::class)->lock(true);
        DB::table('extension_runtime')->where('id', 1)->lockForUpdate()->first();
    }

    private function outsideTransaction(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('External gateway work must run after the outermost transaction commits.');
        }
    }
}
