# Independent currency pricing: Phase A

Phase A supplies the core foundation for a currency-management plugin. It does
not implement that plugin, exchange rates, customer currency selectors, or online
payment initiation. The additive generic online-payment foundation is documented in
[ONLINE_PAYMENTS.md](ONLINE_PAYMENTS.md); the real gateway plugin remains separate.

## Public contracts

Inject `Mavyn\Contracts\Pricing\PriceBook` into a plugin controller or service.
The host binds it to the scoped `PricingService`; plugins do not need `App` models,
a replacement checkout service, or a pricing-provider registration capability.
`Mavyn\Contracts\Pricing\CheckoutReview` is the corresponding checkout quote API.
The contracts package has no Laravel or host dependencies. Currency metadata,
money bounds and storage-independent quote fingerprints live in `Mavyn\Core`.

```php
use Mavyn\Contracts\Pricing\PriceBook;

public function __construct(private PriceBook $prices) {}

$context = $this->prices->context();
$definitions = $this->prices->currencies(); // nine codes, symbols, precision, enabled/selected
$prices = $this->prices->read('variant', [12, 13], 'USD'); // batch; missing IDs are absent
$coverage = $this->prices->coverage('USD');
$page = $this->prices->catalog('variant', 'USD', page: 1, perPage: 50);
```

`CurrencyContext` carries `currency`, `symbol`, `precision`, `revision`, `enabled`,
and `ready`. The nine supported codes are PKR, USD, EUR, GBP, AUD, CAD, AED, SAR and
INR; all have two decimal places. No unsupported precision is inferred.

Kinds are `product`, `variant`, `option`, `zone`, and `coupon`. Price rows contain
`amount_minor`, `sale_minor`, `minimum_minor`, and `maximum_minor`. Missing rows
are unavailable; explicit `0` is distinct from missing. Reads take an explicit
currency and do not convert or fall back. Metadata pages contain entity IDs,
labels, product/group IDs, type and the currency-specific price, so an independent
price editor can work without host-model queries. Pages are bounded to 100 rows.
Product metadata pages additionally accept `min_minor`, `max_minor`, and
`sort: price_asc|price_desc` in the final `filters` argument; filters and pagination
run in SQL using the same variant-selection rule as storefront cards.

Authorized writes use an optimistic revision and UUID operation identity:

```php
$context = $this->prices->enable(['PKR', 'USD', 'EUR'], $context->revision, $uuid1);
$context = $this->prices->save('USD', [
    ['kind' => 'variant', 'entity_id' => 12, 'amount_minor' => 15000, 'sale_minor' => null],
    ['kind' => 'option', 'entity_id' => 9, 'amount_minor' => -1000],
    ['kind' => 'zone', 'entity_id' => 3, 'amount_minor' => 500],
    ['kind' => 'coupon', 'entity_id' => 4, 'amount_minor' => 1000,
        'minimum_minor' => 5000, 'maximum_minor' => null],
], $context->revision, $uuid2);
$context = $this->prices->select('USD', $context->revision, $uuid3);
```

Use different UUIDs for different operations. Repeating the same operation and
payload is idempotent; reusing an identity for different changes returns 409.
Conflicting editor revisions return 409. Invalid batches return validation errors
without price changes, revision increments, or a success audit. Batches contain
1–500 unique priced entities. `delete: true` removes a currency-specific price;
it never deletes the catalog entity or historical snapshots. A selected currency
must stay enabled. Selecting an incomplete price book is allowed: coverage is
available to the management UI, and unpriced configurations remain unavailable.

Prices, sale prices and fees are nonnegative; option adjustments can be signed.
A configured unit price and every order subtotal/final total must be nonnegative
and at most 9,999,999,999 minor units (99,999,999.99), matching the existing
`DECIMAL(10,2)` storage. Input to the new writer must be integers, not floats or
decimal strings. A sale must be below its regular price. A percentage coupon's
percentage stays on the coupon; its currency row uses a null `amount_minor` and
explicit minimum/cap rules. Missing rules make the coupon ineligible.

## One display and legacy-write rule

Every purchasable configuration uses a variant. The catalog/card default is the
first **priced, active** variant, ordered by default flag, sort order, then ID.
Stock does not change the advertised price selection; unavailable stock disables
purchase. Configuration, filtering and ordering follow the same rule. Required
option groups without enough priced choices make the product unavailable.
Selections with missing adjustment records are rejected, including formerly free
options that have not been explicitly initialized in the selected currency.

A public `product` price write is an editing alias for that product's default
active variant. Product and default-variant edits that collide in one batch reject.
Product price reads resolve the displayed priced variant. Legacy product-only
records remain retained for compatibility/provenance; they do not create a
purchasable configuration without a variant. No currency's price is obtained
from a different currency's product or variant record.

Legacy columns carry an immutable `price_currency`. Existing model/form writes
update that stamped currency's canonical records atomically; they never assume
the currently selected currency. Product/default-variant writes synchronize their
legacy projection. New core admin forms show the denomination and submit an
editor revision; stale forms reject before mutation. Nested new variants/options
inherit the parent form's explicit denomination. Attempts to relabel an existing
record reject; use the price book to enter an additional currency instead.
Factories and old callers omitting a denomination use the source's established
legacy currency. Raw SQL/bulk query-builder monetary writes bypass model adapters
and are unsupported; use the public price book or the existing model adapters.

## Permissions and plugin entry point

The actor must be an active authenticated administrator. Public metadata pages
require `pricing.view`; price batches require `pricing.manage`; enabled currencies,
selection and payment policies require `currencies.manage`. Existing core admin
forms retain their domain permissions. Public operations audit actor, currency,
entities, old/new revisions, and operation identity. Legacy HTTP monetary audits
include their stamped currency, prior revision and shared request operation ID.

A plugin can declare an `admin` route resource and its existing namespaced
`<plugin-id>.manage` permission. Define the GET editor route as `index` in that
resource. The shared Plugins page displays a Manage link for an active plugin
when `plugin.<plugin-id>.admin.index` exists and the actor has that permission.
Existing plugin activation, authentication, permission and web/CSRF middleware
protect that route. The core price-book permissions are checked again inside the
adapter. No shared sidebar or product-controller replacement is necessary.
See `tests/Fixtures/Pricing` for a contract-only synthetic integration.

Disabling/removing the management plugin removes its management routes. Price
interpretation, selection, canonical storage and historical order rendering remain
core-owned and do not depend on that plugin being active.

## Manual payment and shipping eligibility

`CurrencyPaymentProvider::supportsCurrency(string $currency): bool` is additive to
`SelectablePaymentProvider`. Core checks eligibility both when displaying choices
and inside placement. No method is eligible merely because it is enabled.
Known `mavyn.manual-payments.easypaisa` and `.jazzcash` providers remain PKR-only.
Other legacy providers, including COD and bank transfer, require recorded,
provider-specific evidence of supported currencies. Unknown eligibility fails
closed, including on existing interfaces; those interfaces remain loadable.

```php
$context = $prices->paymentPolicy('vendor.payments.bank', ['USD'],
    'Verified this configured bank account accepts USD transfers.',
    $prices->context()->revision, $operationId);
```

A recorded policy takes precedence over an additive provider declaration, except
that it cannot broaden the known PKR-only wallet identities. Historical instructions
are read from the saved order and are not subject to current eligibility.
The corresponding operator command is:

```sh
php artisan platform:pricing:payment-policy vendor.payments.bank USD --admin=1 --revision=12 --evidence="Verified this account accepts USD transfers."
```

Legacy delivery-zone records are explicitly PKR during migration. Their adapter
returns only a price explicitly recorded in the requested currency. A shipping
provider must itself return a matching-currency option and quote; mismatched
options are omitted and forged/stale choices reject. Existing third-party quote
amounts are never relabeled. Provider-specific free-shipping thresholds and other
shipping settings remain provider-owned and require that provider's currency
policy; core does not convert them.

## Complete checkout review

1. `GET /checkout` supplies an owner-bound draft `idempotency_key`.
2. `POST /checkout/options` accepts country/postal inputs and returns current
   currency-bearing shipping/payment choices. Customer changes clear review state.
3. `POST /checkout/quote` validates customer/destination/payment input and the draft
   key, reprices the cart from authoritative records, and returns `review_token`,
   `expires_at`, and `quote`. It does not place an order or reserve stock.
4. Display the returned subtotal, discount, shipping, tax and final total in the
   returned currency. The customer confirms this reviewed result in a subsequent
   request; a token is never silently refreshed during placement.
5. `POST /checkout/place-order` includes the draft key and token. The server locks
   and recomputes the full terms, then compares them to the persisted review.

The 15-minute quote binds owner/cart identity, currency and pricing revision,
extension revision, item/configuration amounts, coupon identity/rules/eligibility,
destination, shipping provider/option/fee, payment method/instructions, tax
rate/basis/amount and total. Tokens are random and stored as hashes. JSON
fingerprints canonicalize associative keys because MySQL JSON storage can reorder
them. Integer and string types and list order remain significant.

Missing, expired, tampered or stale reviews return validation errors under
`review_token` (or the relevant unavailable method/configuration). Ownership
mismatches return 403. No order, reservation, coupon usage or confirmation commits.
The quote endpoint is the supported refresh path. Final stock and coupon limits
are checked again; obtaining a quote does not reserve either.

A completed matching request is replayed before current pricing/payment checks,
even after expiry, a currency switch or provider deactivation. Changing committed
request details returns 409. The bundled JavaScript keeps the exact confirmed
request after an uncertain network outcome so the customer can retry it safely.
Order success denotes order placement; no online payment capture is introduced.

## Transactions, caches and workers

The common order is currency runtime, extension runtime, checkout attempt, sorted
cart owners/cart rows, sorted products/variants, coupon/usage reads, shipping-zone
rows, then inventory/reservation work. Price/currency/monetary-settings changes
hold the currency runtime exclusively; checkout holds it shared. Existing catalog,
plugin settings/lifecycle, theme and inventory entry points acquire the same root
lock before their own locks. Mutation validation and audit are transactional.

HTTP middleware conservatively holds the root lock for the complete response:
shared for reads and exclusive for writes. This pins symbols and prices through
rendering and prevents mixed responses. This initial implementation serializes
web writes; measure contention before narrowing that scope for higher throughput.
Price caches are scoped and keyed by revision/currency/entity. Currency-bearing
homepage products are read fresh. Shared invalidation happens after commit, while
operation-local state resets immediately. Laravel scoped bindings reset between
requests/jobs; direct long-running host operations acquire a fresh root context.
No worker should retain a price DTO as authority for a later purchase.

Reports select a transaction currency explicitly and never sum different currencies.
Dashboard totals, discounts, averages and charts filter by saved currency, and
recent orders/admin polling format the order's saved identity. Customer spending
is grouped by currency. Existing sales-status definitions are preserved. No FX
reporting or live rate lookup is added.

## Upgrade and provenance recovery

Use an upgrade, not `composer setup`, on an existing store. Setup generates an
application key; it is not an upgrade command. Keep the current `.env`, key,
accounts, media, merchant data, private manifests and installed theme/plugin repos.
Back up the database before deployment. Take a maintenance window while applying
schema/backfill and adapting the installed checkout theme.

```sh
php bin/prepare-composer.php
composer update mavyn/contracts mavyn/core mavyn/theme-default --with-dependencies
php artisan migrate --force
php artisan platform:pricing:preflight
```

Resolve only changed requirements; preserve unrelated/private dependency versions.
The migration retains the selected store currency. It does not select USD globally.
It records evidence separately for product, variant, option, zone and coupon
sources. Empty sources have no legacy money to infer; zone provenance is PKR.
Populated sources without explicit row currency require an operator resolution,
even if a current store setting looks plausible. Until all sources and historical
currency identities are unambiguous, selling is blocked (`ready = false`).

After checking deployment records and invoices, record each resolved source:

```sh
php artisan platform:pricing:preflight --resolve=product=PKR --resolve=variant=PKR --resolve=option=PKR --resolve=coupon=PKR --admin=1 --evidence="Verified the original deployment price book and invoices." --backfill
```

That example is not a default denomination instruction: use the proven codes for
that installation. Known provenance cannot be overwritten with another code.
Backfill is chunked, repeatable, and preserves explicit canonical prices. It uses
exact stored decimal strings and integer arithmetic. It does not rewrite order,
item or notification snapshots. Missing/contradictory historical or queued order
currency is reported and blocks readiness; resolve it through a separately
reviewed recovery using historical evidence, preserving established snapshots.
Record any needed legacy payment policies before reopening checkout.

Core setup intentionally preserves an existing `themes/default`. Merge the view
changes under `resources/themes/default/resources/views` into the installed theme
repository; compare against its prior seed and resolve customizations there.
The checkout view now requires the options/quote/review flow above. Also merge
currency-bearing product/cart/drawer data and per-currency account spending.
Do not replace the whole theme directory, its build assets, or merchant branding.
The core-only integration suite renders the new bundled seed directly, so passing
it does not certify a client's older installed checkout template. Adapt and test
that installed copy before reopening selling. RJ Leather adaptations remain in
its own repository.

```sh
npm ci --ignore-scripts
npm run build
php artisan platform:extensions:build
php artisan platform:themes:assets
php artisan optimize:clear
composer test:core
php tests/Support/currency-concurrency.php
```

Complete client-specific storefront/checkout and historical receipt checks too.
Select USD for RJ Leather only through the supported management operation during
its agreed plugin setup, after verification; retain its legacy-currency records.

The migration's `down()` deliberately rejects automatic rollback. After multiple
currency prices exist, older code would reinterpret legacy columns. Prefer a
forward fix. A rollback requires a reviewed pre-upgrade database/code backup and
an explicit reconciliation plan for orders created since that backup; dropping
price records is not a recovery strategy.

## Validation evidence

The core-only suite includes independent USD/EUR/GBP order placement, missing/zero
prices, signed options, product/default-variant sales and SQL filters, public plugin
editor permissions/batch rollback/revisions, manual-provider currency policies,
quote changes/expiry/tampering/ownership, committed replay, migration provenance,
PKR delivery-fee preservation, historical dashboard/outbox identity, plugin
removal, and bundled template compilation. Disposable MySQL checks run both lock
acquisition orders for switch/price/shipping/coupon/tax edits against checkout,
including stock, coupon and outbox effects and committed replay.

Reference-client suites that require Happy Tea or separately installed commerce
packages are not a substitute for this gate and cannot run without those packages.
Validation on 2026-10-15: `composer test:core` passes 52 tests / 439 assertions;
68 compatible regression tests pass with 487 assertions; all 10 disposable MySQL
contention cases pass. The admin build, Composer validation and PHP formatting
checks pass. A headless Chromium fixture verifies destination-choice refresh,
USD tax/total review, stale shipping rejection, fresh confirmation, and replay
after a lost confirmation response with no duplicate orders/notifications or
page JavaScript errors. The installed reference theme/commerce packages are absent;
reference-only suites, including the legacy commerce fields in `SettingsSchemaTest`,
are not certified by this run.

An isolated manual UI fixture is available without client/reference installations:

```sh
pricing_fixture=$(php tests/Support/currency-browser.php init)
MAVYN_CURRENCY_BROWSER_FIXTURE="$pricing_fixture" php -S 127.0.0.1:8766 -t public tests/Support/currency-browser.php
```

Open the local shop, add Fixture jacket, and review checkout. In a second shell,
`php tests/Support/currency-browser.php raise-fee "$pricing_fixture"` changes only
that fixture's fee; confirming the old review must reject, and a fresh review
must display the new total. `inspect` reports fixture order/outbox counts. Stop
the temporary server and remove that fixture directory after testing. Its SQLite
database, sessions, compiled views and generated identity are isolated under `/tmp`.

## Currency integrity fixes (0.1.2)

Changing a coupon between fixed and percentage always rewrites its original-
currency rule, even when the numeric amount is unchanged. Other currencies
become ineligible until their rules are explicitly configured again. Invalid
legacy rules left by older code fail closed instead of raising a checkout error.

Deleting a price through `PriceBook` also records a permanent legacy-backfill
exclusion. Re-running backfill cannot restore that deliberately removed price.
An explicit management/model write may still create a new canonical price;
backfill remains excluded from recreating it on its own. Apply the additive
exclusion migration before using deletion in the updated management plugin.
