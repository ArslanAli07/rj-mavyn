# Core handoff: independent prices per currency

Status: Phase A implemented in the shared core (contracts/core 0.1.1); see
[MULTI_CURRENCY.md](MULTI_CURRENCY.md) for the actual APIs, upgrade procedure and
validation evidence. Phase B remains a separate, unimplemented readiness gate.
Baseline reviewed: `a938a33` (`default theme setup`), 2026-10-15.
Core development repository: `/home/ali/sites/mavyn`.
Client/integration repository: `/home/ali/sites/rjleather`.

## 1. Objective and repository boundary

Implement the shared foundation in the **core development repository**, commit
it there, and distribute it to client repositories through the normal pull and
dependency/migration process. Do not implement these core changes directly in
the RJ Leather client repository.

After the changes are pulled and verified, develop extensions in this order:

1. Currency management plugin: independent prices per currency, with an
   administrator selecting the store-wide selling currency.
2. Stripe payment plugin: charge the amount and currency frozen on the order.

The client currency plugin currently consists only of an uninstalled, disabled
scaffold at `plugins/mavyn.currency-changer`. It contains no pricing behavior.
No application-code changes have been made for this feature in the client.

Phase A below is required before currency-plugin development. Phase B describes
the additional, separate core requirements before Stripe-plugin development.
Passing Phase A alone must not be reported as full Stripe readiness.

## 2. Product decisions

- Each sellable item has independently entered prices for each supported
  currency. For example, a jacket may cost USD 150, EUR 145, or GBP 125.
- Selecting EUR loads its EUR prices. It does not convert, overwrite, or relabel
  the USD prices. No exchange-rate service is required for this feature.
- The administrator selects one selling currency for the whole storefront.
  Customer currency selectors, geolocation, and simultaneous per-customer
  selling currencies are outside the first release.
- USD is the requested initial selling currency for RJ Leather. A shared core
  migration must preserve each existing client's configured currency; it must
  not change every installation to USD.
- Initially retain the nine supported two-decimal currencies: PKR, USD, EUR,
  GBP, AUD, CAD, AED, SAR, and INR. Other currency precisions require a separate
  money/storage review rather than applying a blanket multiplication by 100.
- Historical orders, refunds, receipts, invoices, and queued notifications retain
  their transaction currency and monetary snapshots permanently.
- Revenue is reported by transaction currency. An all-currency monetary total
  and automatic conversion into USD are outside this release. Counts can still
  be combined when their labels clearly describe what is counted.

## 3. Confirmed limitations in the current core

Paths in this document are relative to the core repository.

| Current code | Required change |
| --- | --- |
| `app/Services/StoreFinancials.php::validateChange()` blocks changing currency after any product/order exists | Replace the blanket block with a validated, audited currency-switch operation; retain protection against relabeling existing amounts. |
| `app/Services/ProductConfigurationService.php` reads variant prices and option adjustments directly | Resolve those amounts through one currency-aware pricing boundary. |
| `app/Http/Controllers/ShopController.php` filters the original product price columns | Filter and order using the same selected-currency prices displayed to customers. |
| `app/Services/CartService.php` stores numeric price snapshots without explicit currency context | Persist/validate cart currency and pricing context; reprice stale carts safely. |
| `app/Services/CheckoutAttemptService.php::cartHash()` includes item prices and coupon code, but not currency or a complete reviewed quote | Bind checkout drafts to the reviewed currency and all total-affecting terms, including when currency/item amounts remain unchanged. |
| `app/Services/PaymentMethodService.php::choices()` checks enabled state without currency eligibility | Filter payment methods by supported transaction currency and recheck eligibility at order placement, with an explicit legacy compatibility policy. |
| `app/Services/Plugins/EloquentDeliveryZoneStore.php::data()` explicitly denominates legacy delivery fees in PKR | Preserve that source currency during backfill even if the configured store currency differs; resolve ambiguous sources explicitly. |
| `app/Services/OrderPlacementService.php` revalidates prices and snapshots financial identity | Extend that existing transaction to enforce a single consistent pricing/currency context. |
| `app/Http/Controllers/Admin/DashboardController.php` sums all orders without grouping by currency | Group/filter monetary aggregates by order currency. |
| `resources/views/admin/dashboard.blade.php` has PKR chart labels and uses current-store formatting for recent orders | Use explicit report currency and each order's own currency for historical values. |
| `packages/core/src/Plugins/ProviderRegistry.php`, `RuntimeContext.php`, and `PluginCatalog.php` expose no pricing capability | Publish the required extension contracts and registration/storage access mechanism. |

Order currency/symbol/tax snapshots and `order_price()` already exist. Reuse and
audit these paths rather than rebuilding historical identity from current settings.
Other services, templates, exports, emails, PDFs, feeds, and endpoints that read
prices directly must be inventoried during implementation; this table is the
confirmed starting set, not an exhaustive list.

## 4. Ownership and public API requirements

Core owns the authoritative pricing engine, persistence invariants, currency
context, cart/checkout integration, and historical reporting. The plugin owns the
management interface, enabling supported currencies, and workflows for editing
currency-specific prices. The plugin manages prices **through public core
contracts**, not by querying host models or copying checkout logic.

This ownership is deliberate: pricing must remain interpretable and historical
orders readable when the management plugin is disabled or removed.

Publish contracts under `Mavyn\Contracts` and reusable calculations under
`Mavyn\Core`; keep Laravel models, transactions, authorization, and database
adapters in the host. Package contracts must not depend on `App` or Laravel.

The following are proposed responsibilities, not mandatory PHP class names:

| Public boundary | Required behavior |
| --- | --- |
| Currency definitions/context | Return enabled and selected ISO codes, symbol/precision metadata, and a currency/pricing revision for a consistent operation. |
| Price reader | Resolve regular/sale prices for products/variants and signed option adjustments in a requested currency; distinguish missing prices from explicit zero. Support batch reads. |
| Price writer | Validate and atomically save independently entered prices through an authorized adapter, with audit and revision/cache invalidation. Support an optimistic revision check for concurrent editors. |
| Catalog pricing query | Supply efficient currency-aware filtering, ordering, pagination, and display amounts using the same price-selection rule. Avoid loading the whole catalog into PHP. |
| Currency switch operation | Validate target currency, apply the switch atomically, increment revision, invalidate affected caches, and audit actor plus old/new code. |
| Monetary rules access | Read/write currency-specific fixed coupon rules and legacy-zone delivery fees, while preserving provider ownership of other shipping quotes. |
| Payment-method eligibility | Evaluate whether a method accepts the transaction currency at display and placement; expose additive contracts/adapters for existing providers. |
| Checkout quote/review context | Issue and validate a server-owned or integrity-protected quote covering all total-affecting inputs, owner/cart identity, currency, revisions, and expiry. |

Document how a plugin obtains these services and supplies admin pages/links.
If provider registration is used, implement and test the capability allowlist,
registry type, runtime registration, and lazy resolution together. A newly named
interface with no actual host call sites is not sufficient.

The plugin needs an authenticated management page reachable from the shared
Plugins screen or another documented extension entry point. It must not require
editing the client's shared admin sidebar or replacing product controllers.
An independent plugin price editor is sufficient for the first release; general
purpose admin UI slots are optional.

## 5. Price persistence and migration

Use normalized currency-keyed records, unique by priced entity and currency.
Suggested logical records are catalog prices (product/variant, regular/sale),
option-value adjustments, legacy delivery-zone fees, and coupon currency rules.
Final table names and schema are implementation choices; the behavior is required.

- Core owns the schema and its migrations. Use integer minor units for new
  monetary records with explicit supported bounds; preserve exact existing
  decimal amounts during backfill without floating-point arithmetic.
- Regular/sale prices and fees are nonnegative. Preserve existing support for
  signed option adjustments, while rejecting a configuration with a negative
  final price. Validate sale price against its regular price in the same currency.
- Product card prices and default-variant prices need one documented consistency
  rule. A card, product configuration, filter result, and checkout must not select
  conflicting prices for the same sellable configuration.
- Establish currency provenance separately for each source: product/variant
  prices, option adjustments, delivery fees, fixed coupon amounts, coupon minimums,
  and discount caps. Use trustworthy stored currency, explicit provider/adapter
  denomination, or validated deployment provenance. The configured store currency
  is usable only when verified to describe that particular source; it is not a
  universal denomination for all legacy amounts.
- In particular, `EloquentDeliveryZoneStore::data()` explicitly labels legacy
  zone fees as PKR. A USD-configured installation with those fees must retain
  them as PKR records. It must not acquire USD shipping prices by copying the
  numbers. Preserve other known provider-specific denominations in the same way.
- Produce a migration/preflight report identifying each source's resolved currency
  and evidence. If provenance is missing or contradictory, stop the affected
  backfill/switch for explicit, recorded operator resolution. Do not guess, silently
  use the current store setting, or leave a partial migration enabled for selling.
  Any explicit resolution must preserve existing historical snapshots.
- Do not create another currency's prices by conversion or by copying the same
  number into a different denomination. Backfill only the proven source currency.
- A percentage discount remains a percentage; it is not a currency price.
  Currency-specific minimums/caps still apply. Tax percentages are not converted.
- Explicit zero is valid where the existing business rules allow it. Missing is
  unavailable, not zero, not the base-currency number, and not an implicit fallback.
- Preserve legacy single-currency clients, forms, factories, and external callers.
  Define a compatibility adapter for existing price fields; do not leave two
  independently writable sources of truth. Legacy writes must identify the
  currency they edit and keep the canonical records consistent.
- Backfill must be resumable/idempotent and preserve existing explicit price
  records, historical orders/items, notification payloads, credentials, and media.
  Never infer a legacy record's historical currency from a newly changed setting.
- Before enabling switching, ensure existing orders and queued historical
  payloads have unambiguous currency identity; preserve prior backfill semantics
  and do not rewrite established historical snapshots.
- Document forward recovery. Rolling back to code that cannot interpret the new
  currency state must be rejected or use a reviewed recovery plan; dropping price
  records is not an acceptable automatic rollback.

## 6. Phase A: runtime behavior

### Storefront selection and missing prices

Switching validates the target currency and reports price coverage. Products or
variants missing prices become unavailable in that currency and cannot enter
checkout. An incomplete price book must never produce a purchasable price by
fallback. An empty store may select USD before products are created.

A selected option with no amount in that currency is unavailable, including an
option whose old default was zero unless zero has explicitly been initialized for
that currency. A coupon missing required currency rules is ineligible. A shipping
provider without a matching-currency quote supplies no option. Existing third-party
providers must not be made multi-currency by relabeling their returned amounts.

### Payment-method currency eligibility (Phase A)

Currency eligibility applies to manual methods as well as future online gateways.
An enabled method is not automatically eligible for every currency. Evaluate the
reviewed transaction currency when displaying payment choices and revalidate it
inside order placement before creating the order or applying financial effects.
An unsupported method is hidden/unavailable; a forged or stale submitted method
must reject even if its plugin remains active. If no eligible method remains,
checkout must explain that payment is unavailable rather than offer a substitute.

Use an additive eligibility contract or a host compatibility adapter so existing
manual provider interfaces remain loadable. Document a per-provider legacy policy:
known PKR-only wallet methods remain PKR-only; other bank/COD providers require
an explicit supported-currency policy or validated configuration. Preserve proven
existing single-currency behavior, but do not infer new currency support from
`enabled()`, the store setting, a generic provider label, or absence of metadata.
Unresolved eligibility must fail closed for a newly selected currency. Tests must
exercise both compatibility and unsupported-currency rejection. Historical payment
instructions/snapshots remain readable after currency changes or deactivation.
This Phase A requirement does not introduce payment initiation/capture.

### Management plugin deactivation

Disabled currency management stops configuration writes, not price interpretation.
Core continues serving the selected currency's valid prices and preserves all
price records/history. Disabling or removing the plugin must not reset the store
to PKR, relabel legacy columns, or break historical receipts.

### Carts, checkout attempts, and concurrency

1. Persist the cart's currency/context and bind checkout drafts to it. Include
   currency in draft validation even if USD 100 and EUR 100 have identical numbers.
2. Customer review covers the complete checkout quote: item/configuration amounts,
   currency, selected shipping provider/option and quoted fee, coupon identity and
   effective rules/discount, tax rate/basis/amount, subtotal, and final total. Include
   destination and other eligibility inputs used to derive those amounts. Issue or
   refresh a server-validated review token or equivalent persisted context after
   shipping selection, coupon edits, and other total-affecting customer changes;
   binding only the initial cart page or coupon code is insufficient.
3. Bind that review context to the customer/session owner, checkout/cart identity,
   relevant monetary revisions or immutable terms, and an expiry. Resolve amounts
   server-side and protect integrity; trusting posted totals or an unsigned browser
   fingerprint is insufficient. Recompute/revalidate under the placement transaction
   and compare against what was reviewed. Shipping, coupon terms, or tax changes
   require a fresh review even if currency and item prices have not changed.
4. Stale, expired, tampered, or context-mismatched submissions fail with a clear
   reload/review response and a supported way to obtain a fresh quote. No order,
   reservation, coupon consumption, payment, or confirmation may commit on rejection.
   A valid quote does not replace final inventory and coupon-eligibility checks.
   Do not silently refresh the quote and place the order in the same submission.
5. Keep completed checkout retries replayable after a later currency switch.
   Return the original order and original currency; do not create another payment
   or reinterpret its committed request under the new currency.
6. Validate currency, prices, payment-method eligibility, shipping, coupons, tax,
   inventory, and order snapshots within one consistent checkout transaction/context.
   Never accept browser-supplied totals, currency labels, or exchange rates as authority.
7. Serialize currency switches, price edits, and shipping/coupon/tax term changes
   against checkout/catalog writes. Document a common lock order that includes
   current checkout/cart, extension-runtime, product/variant, coupon, and inventory locks. Add contention
   tests; do not introduce a conflicting order of locks or rely only on cache.
8. A concurrent switch must yield either a complete old-currency order or a stale
   checkout rejection. An already-running request must not mix old catalog
   amounts with the new symbol or currency.

### Presentation, caches, and reports

- Resolve monetary data before formatting. Changing `format_price()` or replacing
  symbols alone is insufficient. Expose explicit currency-bearing view data for
  catalog/cards, configurations, drawers, carts, checkout JSON, and provider calls.
- Cache keys or invalidation must account for selected currency and pricing
  revision, including home/catalog/product data, cart state, metadata/structured
  data, admin polling, reports, and long-running workers. Invalidate after commit.
- Price filters and sorting must use the selected-currency effective price under
  the same product/variant display rule. No hidden filtering on legacy prices.
- Keep each historical order/item, email, invoice, receipt, refund reference, and
  queued payload in its saved currency. No current-store symbol for old amounts.
- Group/filter revenue, daily charts, average order value, discount totals, and
  monetary exports by transaction currency. Select currency explicitly in report
  requests/responses and include it in cache keys. Do not add USD and EUR amounts.
- Retain existing definitions of sales/order statuses unless a separate change is
  explicitly specified. Fix currency grouping without silently redefining revenue.
- Consolidated FX reporting is deferred. Do not introduce a live exchange-rate
  lookup into historical reports. A later implementation needs a separately
  defined valuation policy, stored rates, and settlement/fee reconciliation.

### Authorization and audit

Currency switches and price writes require explicit admin permissions and CSRF
protection. Enforce permissions on direct/API/bulk requests, not only in forms.
Validate batches before committing and roll back all changes on failure. Audits
record actor, currency, affected entities, revisions, and operation identity.
Reject stale editor revisions rather than overwriting another administrator.

## 7. Phase A acceptance tests: currency-plugin readiness gate

Add tests to the core-only suite so they run without client/reference packages.
Use a synthetic pricing-management plugin or adapter to exercise the public
contracts; the test must not need private `App` models from inside its plugin.

| Scenario | Required result |
| --- | --- |
| Upgrade populated single-currency store | Each source is backfilled in its proven original denomination; existing orders, mail, credentials, media, and plugin settings are preserved. |
| Store currency differs from legacy delivery-fee currency | With a USD store and legacy PKR delivery fees, those amounts backfill only as PKR; USD shipping remains unavailable until explicitly priced. |
| Missing or contradictory currency provenance | Preflight/backfill stops for explicit recorded resolution; no guessed denomination, enabled partial migration, or historical relabeling. |
| USD 150 / EUR 145 / GBP 125 | Selecting each currency displays and charges its independent amount, with no conversion or overwriting other prices. |
| Missing currency price vs explicit zero | Missing is unavailable; an explicitly valid zero remains distinguishable. |
| Variants/options/sale prices | Product card, selected configuration, cart, and checkout agree; negative option adjustments follow current validation. |
| Coupons/shipping/tax | Fixed amounts/limits/fees use matching currency; percentages remain unchanged; unsupported provider quotes reject cleanly. |
| Enabled manual method does not support selected currency | A PKR-only method disappears after switching to USD; forced/stale selection rejects at placement with no order/stock/coupon effects. |
| Existing provider compatibility | Legacy interfaces load; documented supported-currency behavior remains usable; unknown eligibility never implicitly grants new currency support. |
| Filters/sorting/pagination | Results reflect the displayed currency's prices, including variant/display rules. |
| Currency changes between review and submit | Stale submission rejects, including when numeric amounts match; customer can review/retry. |
| Shipping fee changes after review | With unchanged currency and item prices, a changed selected-option fee rejects the old quote; fresh review displays and authorizes the new total. |
| Coupon rules change after review | Keeping the same coupon code while changing its amount/percentage, minimum, cap, or effective eligibility invalidates the old context; no silent discount/total change commits. |
| Tax terms change after review | With unchanged currency and item prices, a changed tax rate/basis invalidates the old context and requires customer review. |
| Review token tampering, expiry, or owner/context mismatch | Reject safely; legitimate shipping/coupon/customer edits can obtain a fresh server-issued quote; browser totals never establish authority. |
| Quote refresh vs completed checkout replay | Fresh drafts require the latest reviewed terms; a matching committed request replays its original order despite subsequent quote expiry or monetary changes. |
| Committed checkout retried after switch | Same original order/currency returns without duplicate stock, coupon, notification, or payment work. |
| Concurrent switch/price/shipping/coupon/tax edit vs checkout | Consistent reviewed order or stale-quote rejection without mixed terms, duplicate writes, or unresolved lock-order failures. |
| Mixed-currency historical reports | USD and EUR totals remain separate; charts, exports, recent orders, invoices, and queued mail retain correct identity. |
| Plugin disabled/removed | New management requests stop; selected-currency prices and historical records remain usable. |
| Unauthorized/invalid/stale batch | Entire operation rejects and no success audit or partial price update commits. |
| Cache/worker behavior | Currency switch and price edits do not serve stale or relabeled prices. |
| Fresh core without management plugin | Default single-currency setup remains usable and existing providers/themes remain compatible. |

Run `composer test:core`, relevant integration suites, currency contention tests
on a disposable MySQL database, Composer validation, and the admin asset build.
Existing full integration tests may require separately supplied reference
packages; document exactly which suites ran and why any could not run.

## 8. Phase B: additional core foundation for Stripe

Currency correctness alone does not add online payments. Keep Stripe credentials,
SDK calls, webhook verification, Stripe object IDs, and Stripe-specific tables in
the future Stripe plugin. Core must supply the following gateway-neutral seams:

1. An additive online-payment initiation contract/result beside the existing
   manual `PaymentProvider` contract. Pass an immutable order/payment context
   containing amount in minor units, currency, ownership identity, and an operation
   ID. Preserve existing manual providers. Exact names and signatures are proposed.
2. Checkout support for redirect/action results in ordinary and JSON submissions.
   The bundled checkout JavaScript currently shows an order-success state after
   JSON placement; it must handle a payment action before claiming payment success.
3. Initiate external payments after the order/reservation transaction commits.
   Define durable attempt identity, retry/recovery, and reconciliation so an API
   timeout cannot create multiple payable sessions or duplicate orders. A browser
   redirect must never be the only durable trigger for payment recovery.
4. A supported service to apply verified provider payment results with amount,
   currency, provider/attempt identity, and deduplication checks. Core owns order
   and inventory transitions. Plugins must not directly set `payment_status`.
5. Explicit state rules for payment pending/processing, successful payment, retryable
   failure, expiration, cancellation, late success after stock release, refunds,
   and disputes. Keep payment and fulfillment states distinct. A declined attempt
   must not automatically cancel an otherwise retryable checkout. Avoid changing
   existing manual-payment semantics while adding gateway attempt states.
6. A documented route mechanism for signed external callbacks. Current plugin
   storefront routes include web/CSRF and activation middleware. Only the verified
   callback endpoint may bypass CSRF; customer payment-start writes keep CSRF and
   ownership checks. Provide a drain policy so stopping new payments does not
   disable settlement/refund callbacks for historical payments.
7. Permissioned refund operations and historical payment records. A local status
   edit must not masquerade as an external refund. Provider confirmations, refund
   totals, partial refunds, and duplicate/out-of-order events require validation.
8. A supported operational path for durable jobs and scheduled reconciliation.
   Document registration/supervision, retries, failure visibility, and draining;
   the current catalog/display hooks are not a committed order-event delivery API.

The Stripe plugin's acceptance suite will exercise successful/declined/3DS
payments, missing browser returns, API timeouts, duplicate and unordered webhooks,
amount/currency mismatches, expiry/late success, refunds, ownership, and disable/
drain behavior. A documented testable core adapter is required before claiming
the platform is ready for that plugin. Actual Stripe sandbox verification comes
after plugin implementation and private test-credential configuration.

## 9. Delivery and later client verification

Core deliverables:

- Versioned public contracts, host bindings, schema/backfill migrations, and
  working host call sites for Phase A.
- Permission and pricing-management integration documentation, including sample
  plugin calls, errors, money bounds, missing-price rules, source-currency provenance,
  payment eligibility/legacy policy, complete-quote review, and concurrency rules.
- Passing automated acceptance evidence and a compatibility/migration guide.
- Updated bundled default-theme view data/checkout support where needed. Setup
  preserves installed `themes/default`, so explain how existing clients update
  that installed copy without overwriting customizations. Updating only the seed
  does not update an existing installation. RJ Leather theme adaptations remain
  client-owned and are not committed to the core theme seed.
- Updated shared manifests/lockfile only as needed for declared contract versions;
  client private Composer manifests and extension packages remain client-owned.

After the user pulls the core changes into RJ Leather, the verification pass will:

1. Review the pulled diff against Phase A and identify the actual implemented APIs.
2. Preserve the existing application key, admin account, theme folders, private
   client manifests, and store data. Use the upgrade path, not fresh setup that
   regenerates the application key. Run destructive migration/concurrency checks
   only on disposable databases.
3. Prepare the client Composer manifest and resolve only changed/new requirements
   as necessary, preserving client themes/plugins and unrelated dependency versions.
4. Run migrations with backups as appropriate, rebuild catalogs/admin assets, and
   clear relevant caches through the documented update path.
5. Run core and targeted integration tests plus isolated currency fixtures; check
   storefront, historical reporting, theme compatibility, and plugin lifecycle.
6. Report one of: **ready for currency-plugin development**, or specific unmet
   requirements with code/test evidence. Separately report **ready for Stripe-
   plugin development** only if Phase B also has a working, tested public API.

Do not activate a currency switch, populate other currency prices, or rewrite
merchant amounts merely to demonstrate a migration. Apply RJ Leather's requested
initial USD selection through the supported operation during the agreed plugin
setup after verification; preserve any existing legacy-currency price records.

## 10. Copyable task for the core implementation session

> Work in `/home/ali/sites/mavyn`. Implement Phase A of this document: independent
> currency-specific pricing and the public contracts needed by a currency-management
> plugin. Core owns authoritative price persistence, currency-aware catalog/cart/
> checkout behavior, historical snapshots, and currency-grouped reports. The plugin
> will manage prices and the store-wide currency selector through those contracts.
> Include currency-aware manual payment eligibility with an explicit legacy policy,
> source-specific backfill provenance (legacy delivery fees are PKR), and complete
> server-validated checkout review covering shipping, coupon, and tax changes.
> Preserve existing clients with idempotent migrations and compatibility adapters;
> do not convert prices, relabel amounts, rewrite historical orders, or change every
> client to USD. Add and run the acceptance tests, including disposable MySQL
> contention checks. Document the real APIs and upgrade procedure. Do not edit the
> RJ Leather client repository or implement its plugin in this core change. Phase B
> is a separate readiness gate for the later Stripe plugin and may be delivered
> separately after the currency foundation. Report implemented scope and any
> remaining blockers explicitly.
