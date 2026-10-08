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

Completed retries bypass current stock, payment-method, delivery-zone, and ordering availability checks because they read an existing order. Structural request validation and ownership checks still run. Two concurrent requests with one key serialize on its attempt row: one creates the order and the other replays it. Different draft keys competing for the same cart serialize on cart rows; the second cannot purchase rows already consumed by the first.

## Retention and recovery

There is no automatic expiry or cleanup of checkout keys. Keep completed attempt records even when deleting orders: the nullable order relationship clears, while the commit marker prevents accidental reuse. Do not delete completed records to reset a checkout. Historical orders have no inferred attempt records; idempotency applies to the new submission contract.

A caught rollback removes an uncommitted receipt. If an exception occurs after commit, the durable marker protects its saved receipt. If database availability prevents verifying commit state, cleanup conservatively preserves the file and logs a warning. A process interruption can leave an uncommitted file; retrying the same draft reuses its path. Abandoned drafts/files have no automatic sweeper yet and should not be removed without checking their commit state.

Confirmation email is sent only by the request that creates the order; a replay never sends it again. Delivery remains best effort. An interruption before email delivery can leave a committed order without a confirmation email. Durable notification/outbox delivery remains a later milestone.

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

Decimal-safe pricing is the next implementation milestone, followed by guest coupon identity policy.
