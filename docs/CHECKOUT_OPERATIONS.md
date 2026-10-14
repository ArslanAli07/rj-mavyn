# Checkout retries and recovery

New checkout submissions require a server-issued `idempotency_key`. The key identifies one reviewed cart and, after success, one committed order. Its database record commits in the same transaction as the order, stock reservations, coupon usage, and cart removal. Cache clearing or a lost HTTP response does not remove that record.

## Customer behavior

Opening `/checkout` issues a draft key and embeds it in the form. Reloading an unchanged cart reuses its draft. The form retains the key after network errors and retries with it. Applying or removing a coupon updates the total and draft key together; order submission waits for that change to finish.

After an order commits, resubmitting its key with the same customer, delivery, payment, and notes returns the original order ID and tracking URL. JSON responses include `replayed: true` for retries. Normal form submissions redirect to the original success or payment-instructions page. Returning to checkout with an empty cart also recovers that page using the session's completed key, including when the shop has since closed.

A replay does not consume newly added cart items or remove their coupon. Starting checkout with a new cart issues a new draft. Reusing an old key always refers to the original purchase, including a cancelled order; it does not reopen or replace that order.

Manual-payment checkout still requires a valid image receipt on the first successful submission. A retry can resend identical bytes under another filename or omit the already saved receipt. Different receipt bytes conflict with the committed purchase. Private uploads use one path per checkout attempt, so a retry can overwrite an interrupted pre-commit upload without creating another file. Receipt delivery detects the content type from the bytes. Staff confirmation/cancellation controls stock holds as described in [Inventory operations](INVENTORY_OPERATIONS.md).

## Ownership and request contract

Keys belong to the authenticated customer or the guest's cart session. The same account can retry from another authenticated session if it retains the key and original details. Guest retries require their original cart session. Changing from guest to account checkout requires a new draft. Possessing another customer's key does not authorize order access.

The browser receives its key from `/checkout`; clients cannot supply arbitrary UUIDs as new checkout keys. Existing forms loaded before this change must reload checkout. Normal CSRF, session, and honeypot requirements still apply to retries; an expired honeypot timestamp must be refreshed. Keys are stored as hashes; the attempt record stores hashes of the reviewed cart, submitted details, and receipt, plus the order relationship and commit marker.

| Response | Meaning / next action |
|---|---|
| 200 with `replayed: false` | One order has been placed. |
| 200 with `replayed: true` | The original order was recovered; no new sale occurred. |
| 422 for `idempotency_key` | Key missing, malformed, or not issued; reload checkout. |
| 422 for `cart` | Empty or changed cart, changed prices, unavailable configuration, or insufficient stock; review the cart. |
| 422 for other fields | Correct delivery/payment/receipt details and retry the draft. |
| 403 | Foreign customer key or ordering closed for a new checkout. |
| 409 | A completed key was resubmitted with different details or receipt; review the existing order before starting another purchase. |
| 410 | Its original order has been deleted; this completed key remains unusable for another purchase. |

Drafts are bound to cart rows, configurations, quantities, saved prices, and coupon selection. Cart edits invalidate the old draft. Checkout still reloads catalog pricing and availability under locks. A price refresh commits updated cart prices and leaves the key incomplete, requiring another submission to accept those prices. Validation failures and transaction rollbacks do not bind customer details or consume the key, so the draft can be corrected and retried.

Completed retries bypass current stock, payment-method, delivery-zone, and ordering availability checks because they read an existing order. Structural request validation and ownership checks still run. Two concurrent requests with one key serialize on its attempt row: one creates the order and the other replays it. Different draft keys competing for the same cart serialize on its owner lock before locking cart rows; the second cannot purchase rows already consumed by the first.

Cart additions, quantity/configuration changes, removals, clearing, and guest-to-account transfers share the owner lock. Concurrent first additions to an empty cart create one configured row and preserve both quantities. An addition arriving after checkout has locked the cart waits for checkout to commit, then creates or updates the remaining cart; it cannot be silently written into a purchased row that checkout just deleted. Configuration merges commit both the quantity update and source-row deletion together. Guest transfers acquire both guest and account owner locks in a consistent order.

Migration `2026_10_13_000001_create_cart_owner_locks` is required before running the updated code. It adds an initially empty `cart_owner_locks` table; rows contain a SHA-256 cart identity and creation time. The lock persists independently of cart contents, so empty carts are protected too. Keep these rows; deleting a row while an operation holds it can defeat serialization. There is no automatic retention cleanup. Core mutations must use `CartService`; raw `CartItem` writes bypass this protocol.

## Retention and recovery

There is no automatic expiry or cleanup of checkout keys. Keep completed attempt records even when deleting orders: the nullable order relationship clears, while the commit marker prevents accidental reuse. Do not delete completed records to reset a checkout. Historical orders have no inferred attempt records; idempotency applies to the new submission contract.

A caught rollback removes an uncommitted receipt. If an exception occurs after commit, the durable marker protects its saved receipt. If database availability prevents verifying commit state, cleanup conservatively preserves the file and logs a warning. A process interruption can leave an uncommitted file; retrying the same draft reuses its path. Abandoned drafts/files have no automatic sweeper yet and should not be removed without checking their commit state.

The request that creates an order records its confirmation in the durable outbox; a replay never creates another entry. Dispatch and retry behavior is described below.

## Verification

Apply the migration before serving the updated checkout:

```sh
php artisan migrate --force
```

Functional tests use disposable SQLite data:

```sh
php artisan test
```

Explicit integration checks create, migrate, and drop randomly named disposable databases on the configured local MySQL server. The account needs create/drop permission. Application records and receipt files are not used for these checks.

```sh
php tests/Support/checkout-concurrency.php
php tests/Support/inventory-concurrency.php
```

The checkout check covers simultaneous retries, different keys contending for one cart, and a forced exception after commit that preserves the receipt and recovers the original order. Functional tests cover ownership, conflicting details/receipt bytes, rollback, coupon edits, new carts, disabled services, price review, interrupted uploads, deleted-order tombstones, page reloads, and delayed retries. Browser verification uses an isolated database/session/receipt directory and simulates loss of a committed manual-payment response before retrying from the same form.

Decimal-safe pricing and guest coupon identity enforcement are implemented.

## Coupon identity policy

Guest per-customer coupon limits use the **normalized checkout email and client IP address together**. Reusing the same pair in another session consumes the same allowance. A different email or IP makes a different guest pair; two people sharing an IP do not share an allowance unless their email also matches. Email normalization trims surrounding whitespace and lowercases; provider-specific dots and plus aliases remain distinct. Equivalent IPv6 spellings and IPv4-mapped addresses normalize to the same address. This policy does not verify email ownership.

Signed-in purchases also enforce the existing user-ID limit, so changing email or IP cannot reset an account's allowance. A usage snapshots both checkout-email/IP and account-email/IP hashes; guest and signed-in purchases using either pair share the allowance. A row matching multiple identities counts once. Only the `web` guard supplies account identity.

Cart coupon application can precede the guest entering an email and is provisional. Checkout's apply action submits the current email for an eligibility check; editing the email afterward is revalidated on order placement. IP comes from Laravel's server-side request, never coupon fields. Keep trusted-proxy configuration limited to actual deployment proxies; arbitrary forwarded headers are not trusted by the current configuration. Missing/invalid client IP blocks coupon checkout rather than creating a bypass identity.

Order placement locks the coupon row and performs a current locking read of matching usages before committing the order and usage together. This prevents independent carts from spending the same final pair allowance concurrently under MySQL repeatable-read isolation. Completed checkout retries recover the original order before eligibility checks and do not spend another allowance. Cancellation and order deletion do not refund usage. Existing user-deletion cascade behavior remains unchanged.

Migration `2026_10_09_000008_add_coupon_usage_identity` adds indexed pair-hash columns without changing counters or historical orders. Legacy usages have no captured client IP; their pair hashes remain null rather than inferring an address from today's session. Legacy signed-in usages still count by user ID; legacy guests cannot be retrospectively matched to pairs. New hashes retain no raw IP address and use SHA-256 over normalized email and canonical IP bytes. They are deterministic identifiers, not anonymization or credentials. No existing-store pair backfill is possible without trustworthy historical IP data.

## Durable order notification delivery

New checkouts record one `notification_outbox` entry inside the order transaction when `email_order_confirmation` is enabled. Its unique event key is `order-confirmation:<order id>`. Recipient, confirmation fields, line-item snapshots, and a unique stable Message-ID are saved with the order. Receipt paths and admin notes are excluded. Rollbacks leave no entry; committed checkout replays never create another entry. Existing orders are not retroactively emailed.

The checkout request performs no mail or queue push. The scheduler runs `notifications:dispatch` every minute. It claims up to 100 due entries (maximum configurable batch 1000), then queues `SendOutboxNotification` jobs. The older `SendOrderConfirmation` class remains compatible with previously queued jobs. The notification queue defaults to the database connection and `default` queue independently of the general queue configuration, so a synchronous general queue cannot send confirmation mail during checkout. Configure `NOTIFICATION_QUEUE_CONNECTION` and `NOTIFICATION_QUEUE` when using another backend or a dedicated queue.

Keep the Laravel scheduler and queue worker running using the hosting platform's normal process supervision. Typical commands from the application directory:

```sh
# Run once per minute through the hosting scheduler/cron:
php artisan schedule:run
# Long-running queue worker (must cover the configured notification queue):
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=60
# Read-only counts, without recipient or payload disclosure:
php artisan notifications:dispatch --report
# Dispatch due entries immediately:
php artisan notifications:dispatch --limit=100
```

Keep the queue's `retry_after` greater than the worker/job timeout; the default database values are 90 and 60 seconds respectively. Configure the mail transport timeout below the job timeout. Restart queue workers when deploying code updates. The five-minute dispatch lease prevents repeated scheduler pushes while jobs are pending. A failed queue push releases its lease. Interrupted dispatches and lost/exhausted jobs become eligible again after five minutes. Duplicate queue jobs are safe: the sender locks the outbox row across the mail send and delivery-marker update; completed entries are skipped.

Transport/rendering failures persist the attempt count and error class and defer delivery for 60, 120, 240, 480, 960, 1920, then at most 3600 seconds. The next scheduler pass dispatches due retries. These expected failures do not exhaust Laravel queue retries; unexpected worker/database errors use the job's three attempts, with the outbox lease providing later recovery. Monitor pending counts and `last_error` on unsent entries; fix SMTP/template/queue configuration and allow due retries to recover. Error messages, credentials, and email content are not copied to logs or outbox error fields.

Delivery uses the saved recipient and order details even if the order is edited/deleted or the email setting is later disabled. Links may cease to work after order deletion. The setting controls new entries; it does not discard already committed confirmations. Preserve outbox rows with order history and include them in backups. No automatic retention cleanup or admin retry UI is implemented.

Delivery is **at least once**. Ordinary duplicate jobs and checkout retries do not send duplicates, but a process/database failure after the mail provider accepts the message and before `sent_at` commits can trigger a resend. Retries reuse the saved Message-ID; SMTP does not guarantee deduplication. Exactly-once provider delivery would require a provider supporting an idempotency key.

Verification includes a real database-queue push/pop and handler execution, snapshot rendering, transport/dispatch failure recovery, stale lease recovery, transactional rollback, settings, and checkout replay. The disposable MySQL integration check also verifies outbox retention after an injected after-commit exception and simultaneous duplicate senders; only one simulated mail send occurs. These checks use test mail transports and do not send external email.

### Order-status updates

Single-order and bulk status/payment actions share `OrderStateService`. Select **Email customers when status changes** (or `send_email=true` in the validated request) and enable the global `email_order_update` setting to record status emails. Actual fulfillment-status changes are detected against the locked current order. Repeated/no-op submissions, note-only updates, and payment-only changes create no message. If a failed payment automatically cancels an inventory-managed order, that actual cancellation can create a status message when opted in. Deletion never creates a status email.

Each actual change increments `orders.status_revision`, even when notifications are disabled. An opted-in change records `order-status:<order id>:<revision>` in the same transaction as status/inventory/audit changes. This handles later returns to a previous status in legacy unmanaged orders without confusing them with duplicate submissions. Existing transition restrictions on managed orders remain authoritative. Unauthorized requests, invalid bulk transitions, or audit persistence failures roll back revisions and outbox entries together.

Status entries use `notification_type=order_status_update` with a minimal recipient/status snapshot. The mail contains the status and customer-facing summary from that transition, not today's order state. Private admin notes and receipt paths are excluded. Disabling the setting later does not cancel saved events. The existing retry, lease recovery, stable Message-ID, and at-least-once delivery rules also apply to status messages.

Dispatch and delivery select the oldest unsent notification per order, including its initial confirmation. A later event waits until predecessors have been accepted and marked sent. Different orders can deliver independently. A permanently failing older message blocks later messages for that order; monitor the pending outbox/error class and fix the failure. This controls send order, not the mail provider's final arrival order. No automatic skipping or manual resend UI is implemented.

Migration `2026_10_09_000011_add_order_status_notifications` initializes existing orders at revision zero and defaults existing outbox entries to confirmation type. It performs no historical status backfill. Apply it before serving the new code, then restart long-lived queue workers before permitting new status notifications; old worker code does not understand the new notification type. The dispatch schedule and queue configuration are unchanged.

Tests cover single/bulk/no-op changes, re-entered statuses, opt-in/global settings, audit rollback, invalid bulk rollback, permission denial, original snapshots after edits/deletion, private-note exclusion, ordered delivery, retry recovery, compatibility with serialized legacy jobs, and a database-queue round trip for status mail. Disposable MySQL workers verify that concurrent identical status updates create one revision, change audit, and outbox entry, and duplicate senders send once. Local SMTP acceptance with a real database-queue worker has been verified using synthetic account recipients; external-provider acceptance remains deployment-specific.

## Account notifications and financial snapshots

Welcome and password-reset messages use the same durable outbox. Registration and its welcome intent commit together. Reset tokens are encrypted using the client APP_KEY; messages expire with the configured broker lifetime and replaced tokens or changed/deleted accounts are discarded before send. The reset response says queued. Account messages do not wait on unrelated orders or welcome delivery. Keep the scheduler and notification worker running before offering password recovery.

Orders and order-mail snapshots retain currency, currency symbol, flat tax rate, brand and shipping selection. Receipt paths and admin notes remain excluded from mail. See [fix validation](FIX_VALIDATION.md) for supported financial boundaries and the five required migrations.

For manual abandoned-draft maintenance, stop new requests and drain checkout workers first, then take a database/storage backup. Select only old records with both `committed_at` and `order_id` null (a suggested operational retention period is 30 days). A receipt eligible for deletion is exactly `receipts/checkout-<key_hash>` on the private local disk, with no committed order reference. Verify the same state again before deleting. Retain unknown paths, legacy public receipts, completed attempts (including deleted-order tombstones) and all cart-owner locks. If storage/database state cannot be verified, preserve files and retry after recovery. Perform this only in a maintenance window; there is no automatic prune command.
