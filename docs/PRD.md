# Ecommerce Platform — Product Requirements Document

Status: Draft for review  
Date: 2026-10-07  
Source application: the existing Happy Tea Laravel ecommerce application  
Scope: shared ecommerce core and admin, storefront themes, plugins, and repeatable client deployments

## 1. Product overview

Build a reusable ecommerce platform from the existing application so different clients can use the same maintained commerce system and admin implementation. Each client configures its brand, content, theme, and optional functionality without editing core application files.

A defect in the core must be fixed once, released as a version, and distributed to client deployments through a repeatable deployment process. Storefront presentation belongs in themes. Specialized functionality belongs in plugins. Core language and data concepts must work across product lines.

This PRD defines the target product, rather than claiming that the current application already meets these requirements. It authorizes no implementation or production changes by itself.

## 2. Problem statement

The existing workflow copies or customizes an ecommerce project for individual clients. Shared logic then diverges, and the same bug must be corrected separately in multiple projects. Beverage-specific content and assumptions also make reuse for other industries difficult.

The platform must address:

- Repeated development and maintenance of common commerce functionality.
- Client customization mixed into shared controllers, models, and views.
- Updates that require manual edits in individual client projects.
- Industry-specific language and product fields in generic workflows.
- Unclear compatibility between core updates and client extensions.
- Incomplete checkout, authorization, and inventory guarantees that would otherwise propagate to every client.

## 3. Goals and success criteria

### Goals

1. Maintain one versioned source for commerce logic and the shared admin.
2. Launch client stores through configuration, theme selection, and plugin selection.
3. Support different physical product lines with neutral terminology and extensible attributes.
4. Release and deploy shared fixes without modifying individual client source code.
5. Preserve useful existing functionality and migrate the current store without losing business data.
6. Establish stable extension contracts and automated regression checks.

### Acceptance measures

| Measure | Release acceptance target |
|---|---|
| Reuse | Current beverage store and one non-food pilot store operate without client-specific core edits |
| Update delivery | One core fix is released and deployed to both pilot stores without manually changing their source files |
| Customization | Each pilot uses independent branding, content, theme configuration, and enabled plugins |
| Compatibility | Incompatible core/theme/plugin combinations are detected before activation or deployment |
| Isolation | Pilot stores cannot access each other's databases, uploads, sessions, credentials, or cached data |
| Terminology | Core/admin language audit contains no unexplained beverage-specific wording |
| Data integrity | Migration reconciliation matches customer/order counts, order totals, and purchased item snapshots |
| Regression protection | Required commerce, authorization, extension, and upgrade tests pass before a release |

Production performance targets will be finalized from the pilot baseline and expected client traffic. No throughput or latency guarantee is asserted before measurement.

## 4. Users and roles

| User | Needs |
|---|---|
| Platform maintainer | Develop core and extension contracts; release fixes; track deployment versions and failures |
| Client store owner | Manage products, orders, customers, staff permissions, branding, and store configuration |
| Store staff | Access only the operations granted to their role |
| Theme developer | Build storefronts using stable data and action contracts |
| Plugin developer | Add functionality through supported contracts without editing core |
| Customer or guest | Browse, configure products, purchase, and access only their own account/order information |

Platform deployment access and client admin access are separate permissions. Possession of client admin credentials must not automatically grant infrastructure or other-client access.

## 5. Scope and priorities

### Initial release — required

- Generic physical-product catalog, variants, attributes, images, and categories.
- Customer and guest carts, configurable options, coupons, and reliable checkout.
- Order administration, customer accounts, addresses, tracking, and transactional notifications.
- Shared admin with role-based permissions and audit history for sensitive operations.
- Store configuration, neutral terminology, and configurable branding/content.
- Central media library and reusable media picker backed by Spatie Media Library, with polymorphic associations, usage tracking, private collections, and queued image conversions.
- Theme registration, activation, configuration, asset handling, and compatible fallback behavior.
- Trusted plugin registration, activation, configuration, migrations, and compatibility validation.
- Initial plugins for local delivery, food-specific data/operating hours, and existing manual payment workflows.
- Versioned releases, per-client deployment automation, backup/recovery procedures, and migration tooling.
- Migration of the existing store and validation through a non-food pilot.

### Later releases

- Automated online payment providers and courier integrations as plugins.
- Additional themes and richer configurable page sections.
- Additional languages and advanced regional/tax support.
- Returns/refund workflows beyond manual status recording.
- Optional fleet management dashboard and centralized deployment visibility.
- Multi-tenant hosting and a cross-store admin switcher, if business requirements justify them.

### Out of scope for the initial release

- Public plugin marketplace or execution of untrusted uploaded code.
- Arbitrary browser-based theme/PHP code editing.
- Multi-vendor marketplaces, subscriptions, digital fulfillment, and POS.
- Full ERP, accounting, warehouse, or manufacturing systems.
- A drag-and-drop page builder equivalent to a general website builder.
- Automated financial refunds for manual payment methods.

## 6. Deployment and client model

### Initial decision

Use the same versioned application/core distribution across separate client deployments and databases. Each client has its own domain, environment configuration, storage, session namespace, cache namespace, queues or queue namespace, and credentials.

“Same admin” initially means the same maintained admin software deployed for every client. A single hosted admin that switches between all clients is a different, deferred multi-tenancy capability.

Client customization must live in configuration, content, themes, or plugins. Client repositories or deployment manifests may pin versions and reference extension packages; they must not contain modified copies of core functionality.

### Requirements

- DEP-01: Every deployment records exact core, theme, and plugin versions.
- DEP-02: Platform releases can be deployed to selected clients in stages.
- DEP-03: Client-specific configuration and secrets survive updates and are never bundled into shared releases.
- DEP-04: Shared infrastructure must use distinct namespaces/credentials where needed to prevent cross-client access.
- DEP-05: A failed deployment must not be reported as successful or silently leave an unknown version combination.
- DEP-06: Database migration safety and backups are part of deployment, not optional manual follow-up.
- DEP-07: Third-party plugins execute with application privileges; only reviewed, trusted packages may be deployed. Plugin organization is not a security sandbox.

## 7. Product boundaries

| Layer | Owns | Must not own |
|---|---|---|
| Core | Commerce rules, authoritative pricing, checkout orchestration, orders, customer access, permissions, shared admin, extension contracts | Client branding, industry-specific mandatory fields, hardcoded provider lists |
| Theme | Storefront presentation, templates, styles, assets, section configuration, customer-facing label overrides | Independent pricing, authorization, inventory mutations, order creation |
| Plugin | Specialized business capabilities, provider implementations, namespaced settings, extension screens and fields | Uncontrolled patches to core or other plugins |
| Client configuration/data | Brand, content, locale/currency, active theme, enabled plugins, provider credentials, catalog and transactions | Copies of shared source with local fixes |

Prefer a modular application over independently deployed services for the initial release. Exact package layout and distribution mechanism belong in a subsequent technical design; their acceptance requirement is a single maintained source with reproducible client deployments.

## 8. Functional requirements

### 8.1 Catalog and product configuration

- CAT-01: Admins can create, update, archive, activate, and list products with title, description, slug, SKU, media, category, status, ordering, and SEO fields.
- CAT-02: Products support named variants with independent SKU, price, sale price, availability, and stock tracking configuration.
- CAT-03: A simple product may use an internal default variant without requiring the merchant or customer to understand that implementation detail.
- CAT-04: Variant names, physical weight, dimensions, and industry-specific attributes are separate concepts.
- CAT-05: Configurable option groups support single/multiple selection, required state, minimum/maximum selections, availability, ordering, and price adjustments.
- CAT-06: The server verifies that variants and options belong to the selected product and satisfy current rules.
- CAT-07: Product attributes support generic labels/types and plugin-provided fields. Ingredients, allergens, and servings are optional food-plugin attributes.
- CAT-08: Catalog search, category browsing, pagination, visibility, and product ordering remain available.
- CAT-09: Historical order details remain readable when products, variants, or option labels change or are archived/deleted.
- CAT-10: Invalid or duplicate identifiers receive clear validation errors; identifiers must not include fixed client/industry prefixes.
- CAT-11: Every selected variant has a clearly defined authoritative price. Any product-level displayed price is derived or explicitly defined so two editable sources cannot disagree silently.

### 8.2 Cart, pricing, and discounts

- CART-01: Guests and customers can add, update, remove, and review configured cart items.
- CART-02: Identical configurations merge; different variants, options, or item notes remain separate.
- CART-03: Guest carts merge into the correct customer cart after authentication.
- CART-04: The server calculates prices and totals; client-submitted prices are never authoritative.
- CART-05: Cart quantities and stock limits are enforced on combined quantities, not only individual requests.
- CART-06: Checkout revalidates active products, variants, options, current prices, quantities, and availability. Changed prices/configurations must be disclosed and confirmed before purchase.
- CART-07: Coupons support fixed/percentage discounts, dates, minimum spend, maximum discount, total usage limits, and per-customer usage rules.
- CART-08: Coupon validity and usage limits are rechecked atomically at order placement. Guest usage policy must be explicit; guest checkout must not silently bypass intended customer limits.
- CART-09: Monetary calculations use decimal-safe or integer-minor-unit arithmetic and defined rounding rules. Display formatting respects currency and locale.
- CART-10: MVP supports one currency per store. Orders snapshot their currency; changing store currency must not reinterpret historical totals or silently convert existing prices.

### 8.3 Inventory

- INV-01: Merchants choose tracked or untracked inventory per supported item/variant.
- INV-02: Tracked inventory prevents overselling under concurrent checkout.
- INV-03: Define reservation, deduction, expiry, cancellation, and restoration rules for each supported payment flow.
- INV-04: Untracked/made-to-order products use explicit availability controls instead of misleading stock quantities.
- INV-05: Inventory changes are auditable; repeated cancellation or retry events cannot restore/deduct stock twice.

### 8.4 Checkout and payment

- CHECK-01: Guest and authenticated checkout support customer contact details, applicable address fields, fulfillment selection, payment selection, and order notes.
- CHECK-02: Fulfillment requirements come from the selected provider. Generic checkout must not always require a local delivery zone or map link.
- CHECK-03: Checkout orchestration calls registered payment and fulfillment providers through stable contracts.
- CHECK-04: If no valid payment or fulfillment method is available, checkout explains the condition and cannot place an order using a hidden fallback.
- CHECK-05: Order creation, applicable stock changes, coupon usage, and cart completion are transactionally consistent.
- CHECK-06: Checkout uses idempotency controls so double submissions and retries cannot create duplicate orders or charges.
- CHECK-07: Payment status is separate from fulfillment/order status. Delivery completion must not automatically declare every payment paid.
- CHECK-08: COD and manual bank/mobile-wallet payments are supported through initial trusted provider plugins.
- CHECK-09: Manual receipt uploads are validated, stored privately, and served only to authorized customers/staff.
- CHECK-10: Manual receipt submission does not claim automated payment verification. Staff confirmation is explicit and audited.
- CHECK-11: Provider failures leave an understandable, recoverable state. Online provider plugins must later support authenticated callbacks and idempotent processing.
- CHECK-12: Initial tax behavior must be explicit and configurable for supported pilots. A full jurisdictional tax engine is deferred; unsupported tax requirements are a launch constraint, not silently treated as zero.

### 8.5 Orders and fulfillment

- ORD-01: Orders snapshot customer details, currency, purchased product/variant/options, item notes, prices, discounts, taxes, and fulfillment charges.
- ORD-02: Default customer-facing statuses use neutral language such as Pending, Confirmed, Processing, Shipped/Out for delivery where applicable, Completed, and Cancelled.
- ORD-03: Plugins may supply compatible workflow steps and labels. Valid state transitions and side effects are enforced by a shared order service.
- ORD-04: Single and bulk actions apply the same authorization, validation, audit, and business rules.
- ORD-05: Orders have unique identifiers generated safely under concurrent creation without fixed brand prefixes.
- ORD-06: Admins can search/filter orders, view purchased details, update permitted states, and generate invoices.
- ORD-07: Customers see only their orders. Guest success/payment pages require an authorized session or secure order-access token; null customer IDs do not prove ownership.
- ORD-08: Guest tracking tokens are unguessable, expose only intended information, and can be rotated/revoked when necessary.
- ORD-09: Historical orders are retained by default. Archive/delete access is restricted and cannot accidentally remove required transaction history through routine bulk actions.
- ORD-10: Invoices and tracking pages use client branding, neutral defaults, and stored order data. Invoice paper format is configurable rather than fixed to the current receipt printer.
- ORD-11: Recording a refunded status for a manual payment must clearly distinguish bookkeeping from actually returning funds.

### 8.6 Customers and authentication

- ACC-01: Preserve registration, login/logout, password recovery, profile management, address book, order history, and optional wishlist functionality.
- ACC-02: Address updates/deletion and account/order access enforce ownership on the server.
- ACC-03: Sensitive forms have appropriate validation, rate limits, CSRF protection, and abuse prevention.
- ACC-04: Admin authentication has login throttling and permission checks separate from customer authentication.
- ACC-05: Provision administrators through a secure setup process; reusable releases contain no default production password.
- ACC-06: Staff roles grant capabilities such as catalog management, order management, customer access, configuration, and extension activation. Restricted staff cannot bypass permissions through bulk/API endpoints.

### 8.7 Shared admin and settings

- ADM-01: Reuse existing catalog, order, customer, coupon, filtering, pagination, and bulk-control screens where suitable.
- ADM-02: The admin uses configurable client branding and general ecommerce wording.
- ADM-03: Core and plugins register navigation entries, pages, permissions, and validated settings schemas through supported interfaces.
- ADM-04: Settings have namespaces, types, defaults, validation, and access rules. Arbitrary request keys cannot create/change application settings.
- ADM-05: Sensitive provider credentials are protected and masked; logs and exports must not disclose them.
- ADM-06: Cache invalidation targets relevant entries and the current client namespace; a settings save must not flush unrelated clients' caches.
- ADM-07: Audit records identify actor, time, entity, action, and relevant changes for permissions, settings, inventory, order/payment states, and extension activation.
- ADM-08: Dashboard figures have defined semantics; order totals, paid revenue, refunds, and cancellations are not presented as interchangeable metrics.

### 8.8 Content, SEO, and notifications

- CONTENT-01: Store name, contact details, social links, logos, favicons, metadata defaults, and policies are configurable client content.
- CONTENT-02: Preserve SEO fields, sitemap functionality, and metadata rendering behind generic contracts.
- CONTENT-03: Slides, FAQs, testimonials, contact messages, newsletters, and wishlist may be optional modules/plugins. Disabling one removes its active navigation/routes without breaking core pages.
- CONTENT-04: Themes declare supported configurable sections; content is stored outside theme source so updates preserve it.
- CONTENT-05: Transactional emails use client branding and order snapshots. Plugin/theme template customization uses supported overrides with a safe fallback.
- CONTENT-06: Transactional notifications are queued after committed business operations, retried safely, and monitored. Email failure must not cause duplicate or lost orders.
- CONTENT-07: Newsletter subscription/export does not imply an existing campaign delivery system; campaign functionality is deferred unless separately implemented.

### 8.9 Central media management

- MEDIA-01: Core provides a central media library per client store using `spatie/laravel-medialibrary`; themes/plugins use supported media contracts rather than separate upload systems.
- MEDIA-02: Use Spatie's polymorphic `media` table with stable core-owned assets and a polymorphic usage table for reuse across products, content, branding, orders, and plugin models without duplicate originals.
- MEDIA-03: Shared admin browsing/picker supports upload, search/type filters, metadata, usage inspection, single/multiple selection, sorting, public Copy URL, and permitted archival/deletion. External/manual URL reuse is supported but cannot be fully tracked; private assets never expose permanent public URLs.
- MEDIA-04: Typed policies validate files, size/count/dimensions, permissions, and public/private delivery. Private receipts and their derivatives never appear in public-content pickers.
- MEDIA-05: Central queued conversion/optimization profiles provide thumbnails, display variants, and responsive images with observable failures and safe pending-state fallback. File storage does not imply universal video/audio/document optimization.
- MEDIA-06: Detaching a usage, changing a theme, or disabling a plugin does not delete shared originals. Delete/replace operations protect all usages and historical order retention.
- MEDIA-07: Migration imports existing image/file paths idempotently and reconciles associations, ordering, historical images, privacy, and storage backups.
- MEDIA-08: Core owns the package version and shared picker; the base package is required, while Media Library Pro is optional. Runtime compatibility and optimizer tooling are verified before deployment.

Detailed data model, contracts, migration, and acceptance criteria: [Central media management](MEDIA_MANAGEMENT.md).

## 9. Theme requirements

- THEME-01: A theme manifest identifies its name, identifier, version, supported core versions, templates, asset entry points, configuration schema, and declared plugin dependencies.
- THEME-02: One storefront theme is active per client store; the shared admin is maintained independently.
- THEME-03: Themes cover catalog browsing, product configuration, cart, checkout, account, tracking, content, errors, and empty states through templates or documented fallback templates.
- THEME-04: Theme data/action contracts provide prepared commerce data and secure actions. Themes must not query/mutate commerce records independently to implement business rules.
- THEME-05: Theme assets are isolated and versioned to avoid collisions and stale bundles after activation/update.
- THEME-06: Admins can preview supported theme pages/configuration before activation; activation validates required templates and compatibility.
- THEME-07: Store content/configuration survives theme updates. Switching themes reports unsupported settings and preserves data for possible reuse.
- THEME-08: Theme failure has a documented recovery path to the previous compatible theme or generic fallback.
- THEME-09: Themes can override customer-facing translation labels, while domain values and shared admin defaults remain stable.
- THEME-10: Extract the current storefront into the initial Happy Tea theme after its business logic dependencies have been moved into core/plugins.

## 10. Plugin requirements

- PLUG-01: A plugin manifest defines identifier, version, supported core versions, dependencies/conflicts, permissions, settings schema, migrations, and supplied capabilities.
- PLUG-02: Plugin lifecycle supports registered, enabled, disabled, updated, and removed states with validation and audit records.
- PLUG-03: Plugins register through application/container/provider interfaces, not runtime edits to core files.
- PLUG-04: Supported extension points include payment providers, fulfillment providers, validated product attributes/options, admin navigation/screens, settings, workflow extensions, and committed domain events.
- PLUG-05: Define hooks/events with documented payloads, timing, failure behavior, and versioning. Pre-operation validation is distinct from post-commit notifications.
- PLUG-06: Dependency checks reject missing/incompatible/cyclic dependencies and declared conflicts before activation.
- PLUG-07: Disabled plugins cannot leave active checkout methods, routes, listeners, or scheduled tasks. Historical order data remains readable through stored snapshots/fallback labels.
- PLUG-08: Disabling a provider with open transactions requires a documented operational plan; outstanding orders cannot become inaccessible.
- PLUG-09: Data is retained when disabling/removing a plugin by default. Destructive cleanup is a separate explicit action with backup requirements.
- PLUG-10: Namespaces prevent settings, route, translation, table, and asset collisions. Plugin migrations are tracked and tested through supported upgrades.
- PLUG-11: Plugin failures are logged with store/plugin context and cannot be silently ignored. Optional post-commit work can retry; required checkout-provider failures block the affected operation safely.
- PLUG-12: Only reviewed, trusted packages are deployed. Installing executable plugin packages is a deployment operation; the initial admin does not accept arbitrary uploaded archives.

### Initial extension mapping

| Existing capability | Target ownership |
|---|---|
| Ingredients, allergens, servings, food labels | Food product plugin |
| Operating hours and temporary ordering pause | Store availability plugin; operating rules optional |
| Delivery zones and zone charges | Local delivery plugin |
| COD | Payment provider plugin |
| Easypaisa/JazzCash/bank receipt workflows | Manual payment plugin/providers; no claim of gateway integration |
| Occasion-based merchandising | Optional merchandising plugin |
| Homepage/FAQ/testimonials | Optional content modules or plugin |

Keep generic configuration selection and authoritative pricing in core even when plugins supply specialized choices.

## 11. General terminology and localization

Core language must describe generic commerce. Translation keys must be centralized across admin, storefront, emails, PDFs, validation, notifications, and tracking.

| Existing wording/concept | Core default or disposition |
|---|---|
| Menu | Products or Catalog |
| Drink/Beverage | Product |
| Size/Weight as a variant name | Variant; separate physical weight |
| Toppings/Extras | Add-ons or Options |
| Preparing | Processing |
| Special request | Item notes |
| Servings/Ingredients/Allergens | Optional food-plugin attributes |
| Café | Store |
| Beverage creations | Products |
| Rs. formatting | Currency-aware price formatting |
| Happy Tea/Sialkot defaults | Client configuration/content |
| TAS-prefixed identifiers | Generic configurable identifier policy |

- LANG-01: English is the initial supported language; strings use localization infrastructure so additional languages can be introduced without editing templates.
- LANG-02: A food theme may display “Menu” or “Preparing” through label overrides while core identifiers remain stable.
- LANG-03: Plugin translation namespaces cannot override unrelated core text accidentally.
- LANG-04: Locale, currency, timezone, address labels, and number/date formatting are configurable per store. Initial regional support must be documented explicitly.
- LANG-05: Renaming a UI label must not change historical data or persisted workflow identifiers implicitly.

## 12. Main user journeys

### New client store

1. Maintainer provisions a client deployment/database/storage with isolated credentials.
2. The approved core/theme/plugin versions are installed and migrations applied.
3. Owner account is securely provisioned; branding, currency, timezone, contact details, and policies are configured.
4. Owner selects/configures a compatible theme and applicable payment/fulfillment plugins.
5. Owner adds/imports products and verifies checkout, notifications, and invoices in staging.
6. Store launches after operational validation; its installed versions are recorded.

### Customer purchase

1. Customer browses products and selects a valid variant/options.
2. Core calculates the cart price and preserves distinct configurations.
3. Customer chooses applicable fulfillment/payment methods.
4. Core revalidates catalog, pricing, coupon, stock, and provider availability.
5. Changes are explained for confirmation; successful submission creates one consistent order.
6. Customer receives secure confirmation/tracking; staff fulfill the order through authorized transitions.

### Shared bug fix

1. Maintainer reproduces the issue and adds a regression check.
2. Maintainer fixes core once and publishes a compatible version with release notes.
3. Deployment checks each targeted client's extension versions, configuration, and migration requirements.
4. Release is tested on staging/canary before the remaining selected clients.
5. Each deployment reports its version and validation results; failures follow the recovery plan.

### Plugin/theme change

1. Authorized operator selects an already approved package/version.
2. Compatibility, dependencies, migrations, and open-order implications are checked.
3. Configuration is validated; themes are previewed where applicable.
4. Activation occurs through the supported lifecycle and is audited.
5. Existing content and historical orders remain available.

## 13. Updates, compatibility, and recovery

- REL-01: Version core, themes, and plugins independently using documented semantic-version rules.
- REL-02: Release notes identify fixes, behavior changes, breaking changes, migrations, required actions, and supported extension versions.
- REL-03: Builds are reproducible from pinned dependencies and exact package versions; deployment must not resolve uncontrolled dependency updates.
- REL-04: Support staged rollout, per-client version pins, and an explicit supported-version policy.
- REL-05: Preflight checks validate runtime requirements, configuration, package compatibility, storage availability, and backup readiness.
- REL-06: Deployments run appropriate migrations, build/publish assets, refresh namespaced caches, restart workers, and perform health and commerce smoke checks.
- REL-07: Prefer backward-compatible additive schema changes. Destructive changes require a separate migration/recovery plan and tested data preservation.
- REL-08: Code rollback is allowed only when compatible with the migrated schema. Restoring a database backup requires an explicit plan for orders/writes received after the backup; recovery must not silently discard them.
- REL-09: Deployment logs record client, versions, migration status, checks, and failure/recovery outcome without secrets.
- REL-10: A release must not overwrite client uploads, content, configuration, or extension settings.

## 14. Nonfunctional requirements

### Security and privacy

- Enforce server-side ownership and permissions on every relevant action, including bulk actions and uploads.
- Apply CSRF protection, login/form rate limits, input validation, safe rendering, and secure secret handling.
- Keep customer data, receipts, and credentials out of reusable seed data, sample exports, release artifacts, and logs.
- Define retention/export/deletion policies for customer data and audit records before production launch.
- Keep dependencies maintained and check releases for known vulnerabilities through the release pipeline.

### Reliability and correctness

- Concurrent checkout, provider retries, notification retries, and admin bulk operations must preserve order/inventory/payment invariants.
- Caches are an optimization, not the authority for access control, financial state, or stock commitments.
- Request-specific cart/customer state must not leak through long-lived application workers.
- Backup restoration and supported upgrade paths must be exercised in staging.

### Performance and accessibility

- Paginate large admin/catalog lists and avoid unbounded queries and per-item query amplification.
- Keep theme assets scoped; benchmark catalog, checkout, and admin with realistic pilot datasets and concurrent requests.
- Measure notification polling/background workloads and choose suitable intervals or alternatives before fleet growth.
- Target WCAG 2.2 AA for primary storefront/admin flows, including keyboard access, labels, focus, contrast, errors, and mobile layouts. Validate the target rather than asserting compliance without an audit.

### Observability and maintainability

- Record operational errors with correlation identifiers and core/plugin versions without exposing personal data unnecessarily.
- Monitor failed jobs, checkout/provider failures, deployment health, and order consistency.
- Document theme/plugin contracts, setup, supported runtimes, release procedures, and recovery steps.
- Keep tests around domain behavior and extension contracts; avoid tying business tests to a particular theme's markup.

## 15. Existing application reuse and remediation

These findings come from static inspection. They require executable validation during implementation; current tests have not been run for this PRD.

| Existing area | Reuse decision | Required work |
|---|---|---|
| `app/Services/ProductConfigurationService.php` | Preserve configuration validation/pricing concepts | Generalize naming and monetary representation; expose stable contracts |
| `app/Services/CartService.php` | Preserve guest/customer cart and configuration merging | Revalidate at checkout, enforce combined limits, concurrency/idempotency, and request-safe caching |
| Catalog models/admin screens | Preserve useful CRUD and media flows | Generic attributes, independent variant SKU/stock, debranding, permissions |
| Orders/order items | Preserve snapshot approach and tracking foundation | Secure guest access, workflow rules, generic identifiers, currency snapshots, audit history |
| `app/Http/Controllers/CheckoutController.php` | Preserve useful orchestration behavior selectively | Extract providers/domain services; correct null-owner access checks, stale prices/coupons, public receipts, and hidden COD fallback |
| Customer/auth/address functionality | Preserve | Audit ownership, permissions, rate limits, and reset flows |
| Coupons | Preserve model concepts | Checkout-time validity/limits and atomic usage enforcement |
| Admin middleware and role field | Extend | Actual granular permission enforcement; role presence alone is insufficient |
| Settings/SEO/sitemap | Preserve concepts | Typed schemas, namespaces, safe invalidation, generic defaults |
| Beverage views/assets | First theme | Remove embedded business assumptions and client content from shared core/admin |
| Local delivery/manual payment/hours | Initial plugins | Stable provider/lifecycle contracts and historical-data behavior |
| Existing feature tests | Preserve/adapt | Keep configuration, tampering, cart merge, snapshots, auth, SEO, bulk actions, and availability coverage |
| Brand seeders and fixed admin credentials | Do not ship as production defaults | Generic safe setup and explicitly optional demo data |
| `happytea_updated.sql` | Not starter data | Treat as client-specific material; use only in a controlled migration if needed |
| Static `homepage/` and alternate invoice files | Evaluate before reuse | Establish authoritative assets/templates; do not promote duplicates automatically |

Inventory is currently represented by product stock fields but not reserved/deducted during checkout. The target inventory behavior must be implemented and tested before claiming general retail support.

## 16. Migration plan

1. Inventory existing schema, uploads, settings, content, routes, and integrations; record a baseline and take a verified backup.
2. Correct critical access/checkout defects and establish regression tests before extraction.
3. Extract generic domain services and shared admin without unnecessarily replacing functioning screens.
4. Introduce generic attributes and variant fields; map legacy `weight` used as a label to variant name separately from physical weight.
5. Move branding and policy content into client configuration/content and beverage presentation into the first theme.
6. Extract specialized delivery/payment/food/availability capabilities into approved plugins.
7. Map existing workflow and provider identifiers explicitly. Preserve order identifiers, historical payment labels, item snapshots, tracking links, and uploaded references.
8. Rehearse migration in staging; compare counts, totals, relationships, images, tracking, invoices, and representative customer journeys.
9. Build the non-food pilot through supported configuration/extensions, recording any pressure to edit core.
10. Rehearse a shared release deployment and recovery across both pilots.
11. Cut over the existing store with a documented maintenance/data-write strategy, verification checklist, and recovery plan.

Do not run generic seeders against an existing store as an upgrade strategy. Production data migrations must be deliberate, repeatable, and independently tracked.

## 17. Delivery phases and exit criteria

| Phase | Deliverables | Exit criteria |
|---|---|---|
| 1. Baseline and correctness | Data inventory, regression coverage, access/checkout remediation | Critical invariants pass; migration baseline captured |
| 2. Generic core/admin | Domain services, generic schema/language, settings and permissions | Shared admin works without brand/industry source edits |
| 3. Plugin system | Lifecycle, contracts, compatibility, initial specialized plugins | Enable/disable/upgrade behavior validated, including historical orders |
| 4. Theme system | Manifest, contracts, asset isolation, preview/activation, first theme | Theme change preserves commerce/content and passes required flows |
| 5. Client delivery | Reproducible builds, isolated deployments, release/preflight/recovery tooling | Same release successfully reaches two pilot stores |
| 6. Migration and launch | Existing-store migration, non-food pilot, operational documentation | Data reconciliation, security, smoke, and recovery checks pass |

Implement and validate the plugin runtime and initial specialized plugins before starting the theme runtime, consistent with the architecture implementation sequence.

No dates or staffing estimates are committed until the technical design and baseline tests establish effort.

## 18. Required acceptance scenarios

1. A non-food merchant creates products with generic variants/attributes without seeing required ingredients, servings, or beverage language.
2. A beverage merchant enables food/local delivery functionality and uses industry-specific storefront labels without core modifications.
3. Selecting another product's variant/option is rejected on the server.
4. Identical cart configurations merge, distinct configurations remain separate, and login merges only the correct guest cart.
5. Price, availability, option rules, or coupon eligibility changing after add-to-cart is detected before purchase.
6. Two concurrent purchases of the final tracked unit produce at most one successful inventory commitment.
7. Repeated order submission or provider callback does not create duplicate orders, charges, coupon usage, or stock changes.
8. With every payment method disabled, checkout blocks purchase and does not fall back to COD.
9. An anonymous visitor cannot read another guest's success/payment page by guessing an order ID.
10. Private receipt access is denied to unrelated customers and unauthorized staff.
11. Restricted staff cannot change settings, payment status, extension state, or catalog data through hidden/direct/bulk endpoints.
12. Plugin disable/update preserves historical order details and handles open transactions explicitly.
13. A theme update preserves configured content; incompatible activation is blocked and a failed theme has a recovery path.
14. A settings update invalidates relevant cache entries without affecting another client.
15. A shared core patch is deployed to both pilots without manually editing client source, preserving their themes/plugins/settings.
16. Upgrade/recovery rehearsals preserve or explicitly reconcile new transactions and identify the actual installed version afterward.
17. Emails/PDFs/tracking retain correct historical prices, currency, configuration labels, and client branding after catalog/theme changes.

## 19. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Extraction becomes a complete rewrite | Preserve working flows; extract boundaries incrementally and verify after each phase |
| Client requirements lead back to core forks | Test against a different product line; extend contracts instead of accepting copied fixes |
| Plugin updates break checkout/admin | Compatibility checks, version pins, contract tests, staging and canary rollout |
| A shared release distributes the same defect everywhere | Regression checks, staged rollout, health validation, documented recovery |
| Database rollback loses new orders | Prefer compatible migrations; reconcile post-backup writes before restore |
| Theme embeds financial logic | Enforce prepared data/actions and server-authoritative commerce services |
| Generic wording hides specialized data assumptions | Audit schema, validation, workflows, invoices, and field semantics alongside UI text |
| Trusted plugin compromises application data | Review provenance/code, limit installation authority, and isolate client deployments |
| Migration changes historical order meaning | Snapshot preservation, explicit mappings, reconciliation and representative fixtures |
| Requirements exceed MVP regional capabilities | Document supported pilots/currencies/taxes and reject unsupported launch assumptions |

Admin access decision (2026-10-09): catalog, fulfillment, support, and custom staff presets use granular registered grants; legacy ordinary admins receive no implicit grants. Super admins retain all registered grants. Sensitive payment, inventory, private-file, staff-management, export, and audit capabilities are separate. See [Admin operations](ADMIN_OPERATIONS.md).

Guest coupon identity decision (2026-10-09): per-customer limits use normalized checkout email plus client IP as a pair. Signed-in customers additionally retain account-ID limits. Cart application before email entry is provisional; order placement revalidates and records usage atomically. Historical guest usages without IP are not assigned an inferred identity. See [Checkout operations](CHECKOUT_OPERATIONS.md).

## 20. Decisions pending technical design

The following decisions do not block this PRD but must be resolved before their dependent implementation:

- Core distribution/package boundaries and hosting-compatible deployment tooling.
- Supported PHP/database/runtime versions, tested against the existing locked dependencies and target hosting.
- Who may approve/install versus activate extensions once the extension runtime exists.
- Non-food pilot product line, realistic catalog size, concurrency, and performance budget.
- Inventory reservation duration and deduction policy for COD/manual payment orders.
- Tracking-link information exposure/expiry policy.
- Supported initial tax calculation, address formats, currency changes, and financial retention rules.
- Optional content features packaged as modules versus independent plugins.
- Supported release window and cadence for client deployments.
- Backup retention, recovery time objectives, and recovery point objectives.

## 21. Definition of done

The initial platform is complete when both pilots use one maintained core/admin without client-specific source edits; themes/plugins provide their required differences; correctness, isolation, permissions, terminology, and migration acceptance scenarios pass; and a shared release plus recovery has been demonstrated with documented operational procedures.

Creating directories named `themes` and `plugins`, or copying this project into another client repository, does not satisfy these outcomes on its own.
