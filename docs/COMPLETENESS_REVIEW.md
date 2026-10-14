# System completeness review — 2026-10-14

> This is the original pre-fix review. The nine implementation findings have since been addressed; see [fixes and acceptance](FIX_VALIDATION.md) for current behavior, verification and remaining release scope.

The system is **not complete against its initial platform requirements**. The
reference client has a substantial, tested commerce implementation, but new-client
bootstrap, extension independence, generic branding and financial configuration,
and real staging/migration acceptance still have gaps.

This review covers the current working tree, including the staged separation of
client themes/plugins and Composer files. It evaluates the Laravel host, shared
packages/contracts, locally installed reference extensions, storefront and admin
templates, migrations, configuration, dependency files, tests, and release tools.
It is a code and automated behavior review; production readiness also requires
the deployment acceptance listed below. No implementation fixes were made during
this review.

## Verification performed

| Check | Result |
| --- | --- |
| Full PHPUnit suite | 346 tests, 7,927 assertions passed |
| PHP syntax | 363 PHP files checked; no failures |
| Shared and current clone Composer manifests | Both validated |
| Frontend production build | Passed; output isolated in `/tmp` |
| Checkout/cart/outbox contention | 12 disposable MySQL scenarios passed |
| Inventory contention | Shared and variant stock, plus cancellation scenarios passed |
| Plugin/theme contention | Six disposable MySQL scenarios passed |
| Synthetic two-client release and recovery | Passed for beverage and stationery clients |
| Composer dependency audit | No reported advisories or abandoned packages |
| npm dependency audit | 15 affected packages: 2 critical, 9 high, 4 moderate |
| Plugin-free migration probe | Reproduced failure at the commerce extraction migration |
| Core-only legacy payment probe | Reproduced missing manual-payments class |
| Non-tea email subject probe | Reproduced Happy Tea branding despite non-tea configuration |
| Patch whitespace | Passed for staged and unstaged changes |

Tests used isolated SQLite databases or randomly named disposable MySQL databases.
The release rehearsal used synthetic clients and data. No merchant data was
seeded, migrated, or restored, and no live email was sent. Dependency audits queried
current registries; their absence of findings is not a guarantee of safety.

## Findings requiring implementation

### 1. High: a fresh clone still requires all four reference plugins to migrate

[`2026_10_09_000013_extract_commerce_plugins.php`](../database/migrations/2026_10_09_000013_extract_commerce_plugins.php)
unconditionally resolves local delivery, manual payments, food products, and
store availability, then marks each active. With a valid empty installed-plugin
catalog, `migrate` fails with `Plugin is not installed: mavyn.local-delivery`.
The last successful migration is `2026_10_09_000012_create_plugin_runtime`.

The earlier dependency separation allows installing core without extensions; it
does not yet allow completing database setup without those extensions. A client
that needs only a subset of the reference plugins is also affected. Split generic
schema initialization from optional legacy-client configuration import, and add
fresh-database tests with no plugins and selected subsets. Preserve the existing
store's settings and historical identifiers during that change.

### 2. High: fulfillment selection remains tied to one specific plugin

[`LocalDeliveryService.php`](../app/Services/LocalDeliveryService.php) accepts only
the provider key `mavyn.local-delivery.zone`, Pakistan addresses, and PKR quotes.
[`OrderPlacementService.php`](../app/Services/OrderPlacementService.php) always
uses that service and a delivery-zone ID. Address and delivery-zone routes also
name that plugin explicitly.

A different plugin can register a valid fulfillment provider, but checkout will
not select it. This is an implemented regional reference adapter, rather than a
general fulfillment extension point. Make host selection operate on registered
providers and configured client policy while retaining this adapter for the
reference store. Additional countries and courier integrations remain later
scope; selecting a different supported provider should not require core edits.

### 3. High: shared customer emails retain Happy Tea identity

All four mailables contain Happy Tea subjects. The shared email layout also
contains the fixed business name, beverage/Sialkot tagline, and footer. The
welcome body describes coffee, tea, and frozen treats. See
[`app/Mail`](../app/Mail) and [`emails/layout.blade.php`](../resources/views/emails/layout.blade.php).

Setting the application/site name to Paper Trail still produces
`Order Confirmation — REVIEW-ONLY - Happy Tea` and `Welcome to Happy Tea!`.
Custom logos alone do not solve this. Use client branding and generic language in
shared notifications. Audit invoice fallbacks, configuration, demo seeders, and
legacy shared assets as part of the same extraction.

### 4. Medium: a core historical-payment path calls an optional package directly

[`PaymentMethodService::historical`](../app/Services/PaymentMethodService.php)
calls `Mavyn\ManualPayments\Methods::historical`. A core-only Composer installation
has no such class. Calling this path with `cod` reproduces a class-not-found error.
[`CheckoutController`](../app/Http/Controllers/CheckoutController.php) uses it for
orders without a payment-instruction snapshot.

New snapshot-backed orders avoid this fallback. Legacy orders require either
retained compatibility code or an explicit supported migration/removal policy.
Move the necessary historical adapter into a guaranteed compatibility boundary,
or resolve it safely through installed providers. Test physical package absence,
not only deactivation.

### 5. Medium: generic product attribute persistence is unfinished

[`ProductAttributesService.php`](../app/Services/ProductAttributesService.php)
allows only product `ingredients`/`allergens` and variant `servings`. A plugin
declaring another field, such as material, is rejected as unsupported storage.
Generic variants and configurable purchase options do work, including the
stationery pilot; arbitrary industry attributes do not have a general persistence
contract yet.

Implement namespaced typed attribute storage, validation, administration, and
historical snapshots, or narrow the documented supported attribute contract.

### 6. Medium: currency snapshots and configurable financial behavior are missing

Orders have no currency column or equivalent currency snapshot. The notification
outbox snapshot omits currency too. Shared price helpers use the default `Rs.`
symbol, local fulfillment fixes PKR, and order placement writes `tax_amount = 0`
without using a configurable tax policy. The settings schema does not expose
currency, locale, timezone, or a tax policy.

The tested pilots support PKR and zero tax. That scope is narrower than PRD
`CART-10`, `ORD-01`, `CHECK-12`, and `LANG-04`. Snapshot order currency, define the
supported financial configuration, and prevent configuration changes from
reinterpreting existing catalog prices or historical orders. A full international
tax engine is deferred and is not required to resolve these boundaries.

### 7. Medium: account emails can fail silently without durable recovery

Order confirmations and status updates have an outbox with retries. Welcome and
password-reset mail are sent synchronously in
[`RegisteredUserController.php`](../app/Http/Controllers/Auth/RegisteredUserController.php)
and [`User.php`](../app/Models/User.php). Transport exceptions are swallowed and
logged as raw messages; password-reset delivery can fail while the broker reports
that the link was sent. There is no durable retry record for these sends.

Define recovery appropriate to reset-token expiry and welcome delivery. Keep
transport errors and credentials out of logs, and test failure behavior rather
than relying only on fake successful mail.

### 8. High for dependency maintenance: build dependencies have current advisories

The npm audit flags 15 affected dependency entries, including transitive effects.
Every flagged installed node is marked as a development dependency in the lockfile.
The two critical entries are `shell-quote` and its dependent `concurrently`;
they are not two independently demonstrated application exploits.

Examples are the reviewed
[shell-quote advisory](https://github.com/advisories/GHSA-w7jw-789q-3m8p) and
[PostCSS advisory](https://github.com/advisories/GHSA-fxqj-rqcc-2cmp).
The review did not establish a public storefront exploit. Upgrade affected build
dependencies, assess their actual input paths, and rerun builds and rendered-UI
tests. Some npm suggestions involve major version changes; do not apply forced
dependency upgrades without compatibility review.

### 9. Medium: test and release assumptions still depend on the reference client

The passing full suite uses the locally installed reference themes and plugins.
Tests such as [`CommercePluginPackagesTest.php`](../tests/Unit/CommercePluginPackagesTest.php)
directly import their classes. The standalone rehearsal mirrors already installed
dependencies; it does not obtain extension source from an empty Git checkout.
No independently provisioned reference-extension test setup accompanies the new
repository separation.

[`ReleaseManifest.php`](../app/Services/Deployment/ReleaseManifest.php) also requires
both `plugins/` and `themes/` directories, even if a client has no plugin packages.
Prepare explicit release directories or make optional source-tree handling match
the installed package graph. Establish reproducible core-only checks and a
separately provisioned reference-client integration suite. Define where approved
extension source and pinned versions are obtained before deploying fresh clones.

## Remaining delivery and operational acceptance

- The existing store's actual data/media/receipt migration and reconciliation are
  unfinished. Project documentation also records three missing slide originals;
  their recovery was not verified in this review.
- Real staging deployment, web-server configuration, worker coordination, live
  SMTP, complete MySQL backup restoration, and browser/accessibility acceptance
  remain unverified. The passing synthetic rehearsal is useful evidence for
  application isolation and compatible code recovery, but does not cover these.
- Release manifests and preflight checks exist. A complete per-client deployment
  automation and extension-distribution workflow remains an operator responsibility.
- Abandoned checkout drafts/receipt files have no automatic sweeper. Legacy file
  cleanup after commit lacks the media library's durable cleanup journal. Define
  retention and recovery without deleting committed idempotency records or active
  cart locks.
- Durable plugin order-event subscriptions, general job draining, UI slots, and
  automatic extension upgrades are not implemented SDK capabilities. Their
  documentation describes proposals. Online payments, courier integrations,
  advanced taxes/languages, and automated refunds are later-release scope and
  should not be counted as unfinished initial features merely for being absent.
- Several documentation sections still describe already implemented themes and
  pilots as future work. Update the support matrix and examples after fixing the
  confirmed implementation gaps.

## What is already supported

The code and verification provide substantial evidence for catalog/variant/option
validation; distinct cart configurations and guest-cart merging; server-authoritative
decimal-safe pricing; coupon limits; idempotent checkout; shared/variant inventory
reservation and settlement; protected order/receipt access; granular staff grants
and transactional auditing; durable order notifications; shared media usage,
retention, processing and cleanup; plugin discovery, compatibility, activation and
settings; theme inheritance, preview, activation and assets; and synthetic client
isolation/release recovery. These capabilities form a working reference platform.

## Recommended completion order

1. Make fresh database setup independent of optional reference packages and prove
   selected-package/core-only bootstraps from an empty checkout.
2. Remove optional-package assumptions from fulfillment and historical adapters;
   make emails and shared defaults use client branding.
3. Finish the required attribute and financial contracts, with migration and
   historical-order regression coverage.
4. Resolve dependency advisories, auth-email failure recovery, and reproducible
   extension/test/release provisioning.
5. Reconcile real existing-store data and run both clients through actual staging
   deployment, browser, SMTP, backup/restore, and worker recovery acceptance.

The definition of done in [PRD section 21](PRD.md#21-definition-of-done) is not met
yet. Passing the current suite demonstrates the tested reference configuration;
it does not establish that every independently configured clone is complete.
