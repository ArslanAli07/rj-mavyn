# Commerce plugins (0.1)

Four Composer path packages now supply the former built-in commerce features. They depend on the core/contracts packages and contain no Laravel or App classes.

| Package | Contribution | Disable behavior |
|---|---|---|
| `mavyn/local-delivery` | Typed zone quote in PKR for Pakistan | New zone checkout/address writes and delivery-zone administration stop; zones and saved addresses remain. |
| `mavyn/manual-payments` | COD, Easypaisa, JazzCash, bank transfer | Methods disappear from new checkout; orders and private receipts remain. |
| `mavyn/food-products` | Ingredients, allergens, variant servings | Storefront/editor fields disappear and submitted field writes reject; stored values remain. |
| `mavyn/store-availability` | Manual pause and inclusive daily/overnight hours | The optional restriction is removed; ordering remains subject to other enabled policies. |

Migration `2026_10_09_000013_extract_commerce_plugins` imports existing settings into namespaced plugin storage, initializes previously installed features as active, and adds nullable order snapshots. Existing plugin states/settings are preserved. All imported settings are validated before schema changes. Existing store pause, fees, account details, method IDs, orders, products, zones, and schema identities are retained. This is feature extraction, not an existing-store data import or historical snapshot backfill.

Deploy the root locked dependencies including `packages/` and `plugins/`, build the installed catalog, migrate, validate extensions, and restart application/queue workers. Use the Plugins admin page or `platform:plugins:activate` / `platform:plugins:disable` to toggle features. Physical package removal requires a separate compatibility plan.

## Authoritative checkout

The payment registry supplies selectable methods, instructions, enabled state, and receipt policy. The fulfillment registry supplies the zone quote; the host locks and revalidates the current available zone. Order placement rechecks availability inside its transaction, validates the active payment provider and receipt requirement, calculates integer-cent totals, and saves payment/food snapshots. Completed idempotent requests still replay after plugins are disabled.

New orders retain the payment recipient/instructions and purchased food attributes after later configuration changes or plugin deactivation. Historical orders without snapshots use the installed manual-payment compatibility adapter. Existing authorization and private receipt delivery remain host responsibilities. These providers do not implement online payment capture, refunds, or general carrier orchestration.

Daily hours evaluate the configured store timezone, include boundary minutes, and support windows spanning midnight. Empty start/end times remove the hourly restriction; manual pause still applies. Multiple ordering policies must all allow ordering.

## Compatibility boundaries

Laravel models, tables, delivery-zone CRUD, shared product CRUD, checkout transactions, and historical route identities remain in the host. `DeliveryZoneStore` and `SettingsStore` supply package interfaces. Food descriptors map to retained product/variant columns; this is not a general extension-field persistence engine. Legacy `Setting::get/set` keys for these four packages forward to namespaced storage, and the general settings form uses plugin definitions. Extracted form sections are disabled when their plugin is inactive, and submitted HTTP writes are rejected. The existing `settings.manage` grant applies to these definitions, in addition to the plugin-page management permission.

The legacy scalar delivery settings are retained configuration; the selected zone fee remains authoritative. No new free-delivery threshold calculation is introduced. Blank payment account information retains the prior toggle-based availability behavior. Review those settings before offering a manual transfer method.

Disabling a feature preserves historical data and snapshots. It does not change an order's payment/status or remove a customer's files. Existing queued notifications continue through the host outbox. New checkout transactions hold the runtime state read lock so lifecycle/settings writes cannot invalidate their provider snapshot halfway through placement; an in-flight checkout may finish before a disable commits.

## Verification

`CommercePluginsTest` covers activation, settings compatibility/permissions, disabled checkout/administration, retained fields, dynamic validation, current zone pricing, direct-service availability, durable replay, and order snapshots. `CommercePluginPackagesTest` runs package policies with plain contracts, without booting Laravel. The existing disposable MySQL checkout/plugin concurrency scripts cover transaction races.

For the rendered checkout component:

```sh
MAVYN_CHECKOUT_UI_CAPTURE=/tmp/mavyn-plugin-checkout.html php artisan test --filter=test_checkout_renders_provider_receipt_choices
node tests/Support/checkout-plugin-ui.cjs /tmp/mavyn-plugin-checkout.html
```

This executes the actual rendered JavaScript with minimal DOM stubs; it is not a browser interaction test. The next milestone is theme runtime and Happy Tea storefront extraction.
