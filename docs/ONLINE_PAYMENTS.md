# Generic online payment foundation (0.1.3)

This is core infrastructure for gateway plugins. It contains no gateway SDK,
provider API credentials, provider event names, or production payment plugin.
A gateway plugin requires `mavyn/core` at `^0.1.3` and `mavyn/contracts` at `^0.1.2`.
Currency management uses the existing `PriceBook` API documented in
[MULTI_CURRENCY.md](MULTI_CURRENCY.md).

## Boundaries and public contracts

`Mavyn\Contracts\Payments\GatewayProvider` extends the existing selectable and
currency-aware payment interfaces. Register it using the existing
`$context->provider('payment', '<plugin-id>.<method>', Gateway::class)` and
`payment.provider` capability. Manual providers remain supported unchanged.

The gateway implements:

```php
public function initiate(PaymentContext $context): PaymentAction;
public function reconcile(PaymentContext $context): PaymentResult;
```

`PaymentContext` supplies an immutable attempt UUID, order ID/number, integer
minor-unit amount, saved ISO currency, customer email and opaque owner reference,
return/cancel URLs, attempt status, stable gateway reference, and order status.
The selected storefront currency is never authority for an existing payment.

Use `attemptId` as the permanent gateway idempotency key. Honor it even after an
API timeout or a worker lease expires. The contract requires duplicate initiation
to retrieve/reuse the same remote payment, not create another. Plugin-owned tables
may map this identity to gateway-specific objects or archived account configuration.
The reference returned by initiation/reconciliation/callbacks must identify the
same stable gateway payment throughout; do not alternate between session and
transaction identifiers. Keep any additional provider IDs in plugin records.

`PaymentAction` is either `redirect` with an HTTPS URL without user credentials,
or `pending`. Reference and URL values come from the verified SDK/API response,
not from an untrusted browser or webhook redirect parameter. A hosted checkout
provider should return its redirect on successful initiation.

`PaymentResult` supplies attempt ID, gateway event ID, status, exact original
amount/currency, and gateway reference. Supported statuses are `processing`,
`paid`, `failed`, `expired`, `cancelled`, `disputed`, and `review_cleared`.
Paid/dispute/review-resolution results require a gateway reference. Reconciliation
must retrieve authoritative provider state; a transport failure is not a declined
payment or expired remote session. Use stable event/revision identities for polls.

Inject `Mavyn\Contracts\Payments\Payments` into plugin code to:

- Obtain a payment context from its attempt UUID.
- Apply verified payment results with `apply($providerKey, $result)`.
- Read safe history with `history($orderId)` behind the core `orders.view` grant.
- Request and apply refunds as described below.

These are trusted PHP contracts, not public HTTP endpoints. Plugin endpoints must
verify webhook authentication/signatures or customer/admin authorization before
calling them. Core validates provider/attempt/reference identity, amount, currency,
and event deduplication again; a callback cannot change an order's saved total.

## Checkout and transaction boundary

Order placement records a durable intent inside the existing order/reservation
transaction. It performs no external call. Initial ordinary responses redirect to
an owned local payment-continuation page. JSON responses include
`payment_action: {type: 'redirect', url: '<local continuation URL>'}`; the bundled
checkout follows that action before showing a completed order flow. Manual
checkout responses retain their existing behavior.

`GET /payments/{order}` uses existing account/session ownership authorization.
`POST /payments/{order}/start` keeps web CSRF and ownership protection and either
reuses the existing attempt or creates a new attempt after a definitive decline.
It invokes the gateway only after its short database transactions commit. This
specific POST bypasses the outer pricing-middleware transaction; payment service
operations acquire currency and extension locks themselves. Other customer/plugin
writes retain their normal middleware. External work explicitly rejects execution
inside any outer database transaction.

The service uses a two-minute renewable-by-retry lease and the permanent operation
identity. Configure provider network timeouts shorter than the lease. Even when
workers overlap after a crash/expired lease, provider-side idempotency is required.
Failed transport calls retain the attempt for retry and store only the exception
class, not an SDK response, secret, request body, or diagnostic message.

No browser return marks an order paid. Gateway redirects serve only presentation;
signed callbacks and reconciliation provide payment evidence. Payment being paid
does not advance fulfillment automatically. New fulfillment transitions require
verified payment and no unresolved payment-review flag. Manual payment-status
edits are rejected for online orders, including attempts to label a payment
refunded without a verified refund operation.

A declined attempt preserves the order and reservation for an explicit retry.
Confirmed expiry/cancellation releases a pending order's reservations when no other
active attempt remains. Browser cancellation alone does not supply that evidence.
A late success on a cancelled order records the money and raises `needs_review`
without reopening fulfillment or consuming released stock. Multiple captures for
one order also raise review rather than fulfilling twice. Review reasons are recorded separately: `dispute`, `duplicate_capture`,
`late_capture`, `refund_conflict`, and `legacy_review`. The existing `needs_review`
flag is their aggregate. A verified dispute raises its own hold; `review_cleared`
removes **only** the dispute reason. It cannot release an unrelated duplicate-charge,
late-capture, or refund-capacity hold. It never reopens a cancelled order.
Plugin admin pages can show the safe `review_reasons` array through history.

Core recomputes order payment status from all captured attempts and confirmed
refunds after applying each verified result. A new capture after a full refund
changes the payment status back to paid and raises late-capture review without
consuming stock again or advancing fulfillment. `refunded` requires repayment of
all captured funds. Partial refunds continue to leave payment status paid.

Duplicate-capture holds resolve when confirmed refunds remove the excess over the
saved order total. A late-capture hold resolves when that attempt is fully refunded.
Refund-capacity conflicts remain held while pending/confirmed requests exceed the
attempt's capture. Such conflicts block new remote refund initiation, while the
other hold reasons permit the refund needed to resolve them. Existing remote
operations remain reconcilable. A full refund does not clear an unresolved dispute.
The additive 0.1.3 migration preserves preexisting unknown holds as `legacy_review`;
these require a reviewed recovery decision and cannot be cleared by a dispute event.

## Verified callbacks and deactivation

A payment plugin may declare:

```json
"capabilities": ["payment.provider"],
"routes": {"callbacks": "routes/callbacks.php"}
```

Callback routes have prefix `/extensions/{plugin-id}/callbacks` and names
`plugin.{id}.callbacks.*`. They are installed resources, have bounded request-rate
middleware, and deliberately have neither session/CSRF nor plugin-activation
middleware. The plugin **must** authenticate every callback using the provider's
raw-body signature verification before business execution. Customer and admin
route scopes continue requiring their existing CSRF, activation and ownership/
permission protections. Never put ordinary customer mutations in callback scope.

Disabling a gateway stops its contribution to new checkout choices. Previously
accepted intents, reconciliation, refunds, and authenticated historical callbacks
continue using the installed provider class. A retry that needs a new attempt
requires the original gateway to remain selectable. Accepted work must not be lost
because a toggle removed the provider from the active registry.

Retain plugin code and required account credentials during draining and the
historical callback/refund window. Extension validation rejects removal while
pending operations depend on missing gateway code. Physical removal is a separate
reviewed deployment; deactivation never deletes financial records. Orders with
online payment history cannot be deleted through the ordinary order-delete path.

## Refunds

A provider optionally implements `RefundProvider` with:

```php
public function refund(RefundContext $context): RefundResult;
public function reconcileRefund(RefundContext $context): RefundResult;
```

An active administrator with `orders.payment` requests a refund using
`Payments::requestRefund($attemptId, $amountMinor, $operationUuid)`. This validates
captured funds and pending/confirmed refund totals, records an audit, and returns a
context without calling the provider. Replays of the same operation/payload are
idempotent; conflicting use or over-refunds reject. The scheduler performs the
external operation after the admin request commits. `refundId` is its permanent
provider idempotency key. Plugin admin routes need their own manage permission too.

`RefundResult` has refund UUID, event ID, status (`pending`, `succeeded`, `failed`),
amount/currency and stable provider refund reference. Core checks these against
the saved request and deduplicates events. A partial refund leaves the order paid;
confirmed full repayment changes payment status to refunded. Neither case
implicitly returns goods to inventory or changes fulfillment status.

For a verified refund created in the provider dashboard, include the originating
core `attemptId` on `RefundResult`, a stable UUID for that external refund, and its
provider refund reference. Core can import it without an admin session, validate
it against captured funds, and preserve history. The plugin must persist a stable
mapping; repeated events must not invent new local refund IDs. A conflict with
already reserved refunds raises review and holds new refund initiation. Keep
reconciliation available for operations that may already have reached the gateway.

Event identities share a provider namespace. If one gateway event contains several
payment/refund results, derive stable distinct identities for each target, such as
`event-id:refund-reference`. Reusing an identity for different data returns 409.

## Durable operations and deployment

Run the normal Laravel scheduler (`php artisan schedule:run` each minute). Core
schedules `platform:payments:reconcile --limit=50` every minute without overlapping.
The command processes bounded batches of committed new intents, pending payment
reconciliation and refund operations. It works even if the customer never returns
or a prior request/worker crashed. It can also be run by a dedicated supervised
worker loop. This is a payment-specific journal, not a general plugin job/event SDK.

Monitor pending age, `tries`, `retry_at`, `last_error_class`, and `needs_review`.
The command reports processed/review counts. It does not purge events or payment
records. Provider/account outages require operational attention; no automatic
assumption that an unavailable API means an unpaid order should be cancelled.

Existing-store upgrade:

```sh
php bin/prepare-composer.php
composer update mavyn/contracts mavyn/core mavyn/theme-default --with-dependencies
php artisan migrate --force
php artisan platform:pricing:preflight
php artisan platform:extensions:build
php artisan platform:extensions:validate
php artisan optimize:clear
```

Preserve the application key and private client dependencies. Do not use fresh
`composer setup` on an existing store. Run currency provenance recovery as needed
before reopening selling. The payment/pricing migrations retain deliberate price deletions,
create generic payment/refund/event journals, and preserve separate review reasons; they do not select USD, create
sample data, alter merchant prices, or configure a gateway. Existing deletions made
before the exclusion journal existed cannot be reconstructed automatically; review
those against a prior deployment backup before running a legacy backfill again.

The installed default theme is clone-owned and setup preserves it. Merge the new
checkout `payment_action` continuation support into its own repository, along with
Phase A's quote/review flow. Client custom themes require the same adaptation.
Never replace their branding/assets or assume a passing seed-theme test certifies
an older installed copy.

## Verification boundary

Core tests use a fake gateway, not a production payment plugin. They cover committed
intents and owned continuation, no external work inside transactions, unknown API
outcomes/idempotent retry, missing browser recovery, verified/duplicate/out-of-order
results, exact money validation, failure/expiry/late success, fulfillment guards,
permissioned partial/full/external refunds, dispute holds and deactivation callbacks.
The currency suite also covers coupon type changes and repeated backfill after
intentional price deletion. Run the core suite and disposable MySQL contention
checks before distributing the updated contract packages:

```sh
composer test:core
php tests/Support/currency-concurrency.php
php tests/Support/payment-concurrency.php
```

The concurrency scripts require MySQL permissions to create/drop their own randomly
named disposable databases. They never migrate or mutate the configured merchant
database. Currency checks cover ten checkout/mutation ordering cases; payment
checks cover duplicate callbacks, refund replay/capacity races, and case-sensitive
provider event identities on a case-insensitive MySQL installation.

A real gateway plugin still owns its SDK, credentials/settings, account/currency
eligibility, external signature verification, provider-specific object mapping,
API version, timeout configuration, and sandbox/end-to-end acceptance. No live
payment readiness is implied by the core fake-gateway tests.
