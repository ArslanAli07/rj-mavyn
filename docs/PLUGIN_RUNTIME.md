# Implemented plugin runtime (0.1)

The runtime discovers Composer-installed plugins, stores client activation/settings in the database, and exposes registration contracts from `mavyn/contracts`. Plugin algorithms live in `mavyn/core`; Laravel persistence, routes, commands, and admin screens are host adapters. Plugins execute as trusted application PHP. This is the implemented API; broader examples in the architecture/plugin guide remain future proposals.

## Install and discover

Scaffold with `php artisan platform:plugin:make acme.example`. It creates `plugins/acme.example` with a manifest and an inert entry point; it never requires or activates the package. Add a Composer path repository for `plugins/*` and the actual package development branch/version to the ignored `composer.client.json`, run `php bin/prepare-composer.php`, and resolve the client lockfile with `composer update`. See the [repository setup](../README.md). Plugins require PHP, `mavyn/core`, and `mavyn/contracts` explicitly. Current core/contracts constraints are `^0.1`.

Build `bootstrap/cache/extensions.json` using `php artisan platform:extensions:build`. Composer post-autoload executes this before Laravel discovery. The command uses Composer's installed package graph and reads each plugin's current source manifest, checks compatibility, entry point/interface, identity/schema, namespaced resource paths, duplicate migration names, and rejects plugin-level eager autoload files/Laravel auto-discovery. It atomically writes the catalog bound to the lockfile hash. No plugin business registration or database migration runs during build. Transitive dependency eager behavior still requires package review.

Installed plugin paths must resolve inside the host artifact. Production must ship their files and rebuild catalog/routes when packages change. A missing catalog with installed plugins, stale lock hash, missing resource, invalid enabled graph, or invalid contribution fails closed. The build command remains available to repair a stale catalog. `MAVYN_EXTENSION_CATALOG` can select a controlled deployment/test catalog path; it must never point at merchant uploads.

Manifest metadata is under `composer.json` → `extra.mavyn`:

```json
{
  "kind": "plugin",
  "id": "acme.example",
  "name": "Example",
  "provider": "Acme\\Example\\Plugin",
  "requires_active": {"acme.base": "^1.0"},
  "conflicts_with": [],
  "capabilities": ["payment.provider"],
  "permissions": {"acme.example.manage": "Manage Example"},
  "settings": {
    "notice": {"type": "text", "default": "Example notice", "max": 500},
    "token": {"type": "text", "default": null, "nullable": true, "secret": true}
  },
  "routes": {"admin": "routes/admin.php", "storefront": "routes/storefront.php"},
  "migrations": ["database/migrations/2026_10_09_000001_acme_example_create_records.php"]
}
```

Versions come from Composer, not another version field. Active dependencies are an ID-to-Composer-constraint map; conflicts are symmetric at validation even when declared by one side. No automatic dependency activation occurs. Capabilities support `payment.provider`, `fulfillment.provider`, `product.attributes`, and `ordering.policy`. Permission names are the plugin ID plus one action suffix. Unknown manifest/schema fields are rejected. Migration filenames include the plugin ID with dots/hyphens replaced by underscores. Resource paths stay inside the installed package and cannot traverse directories.

## Local development without repeated Composer updates

Install the plugin once using a Composer path repository with `symlink: true`. With `APP_ENV=local`, the host reads installed plugins' current `extra.mavyn` metadata from their own `composer.json` on each application bootstrap/catalog load. Local editing of PHP classes, settings, capabilities, permissions, route declarations and migration declarations does not require `composer update` or a catalog rebuild. New PHP classes under an existing PSR-4 namespace load normally; avoid Composer's authoritative classmap mode during development. Composer documents [path repositories](https://getcomposer.org/doc/05-repositories.md#path) and [development autoload constraints](https://getcomposer.org/doc/articles/autoloader-optimization.md).

```dotenv
APP_ENV=local
# Enabled by default for local environments:
MAVYN_PLUGIN_DEV=true
```

Refresh the page to use edited code/metadata. Keep local routes uncached; if they were cached, run `php artisan route:clear` before editing routes. Clear compiled views when diagnosing stale presentation. Restart long-running PHP/queue workers to load changed classes. Editing files does not rewrite Composer metadata, change the lockfile, automatically activate a plugin or alter the database.

For a new migration, add its package-relative file to `extra.mavyn.migrations`, then run `php artisan migrate`. The next CLI bootstrap discovers that declaration directly. Migration filenames still include the plugin ID with dots/hyphens replaced by underscores. Activating/using providers with pending migrations remains blocked until those migrations are applied. Deactivation preserves the schema/data. Never edit a migration that has already run; add a new migration.

Verification: seven development regression cases pass, including unchanged Composer files, live route/settings edits, explicit migration gating, production snapshots and dependency/autoload mismatch rejection. Full suite: 375 tests / 8,142 assertions; core-only suite: 20 tests / 90 assertions.

First installation, changed PHP dependencies, autoload mappings, replacement/provided/conflicting packages, package type or package identity still require Composer resolution. The host checks those installed dependency/autoload declarations against the edited source and rejects mismatches rather than pretending missing dependencies are available. Changes to `require-dev` inside a library do not add development tools to the host; install those in the plugin's own development workspace when needed.

Production/staging uses the built catalog; source metadata edits do not automatically change it. `MAVYN_PLUGIN_DEV=true` cannot enable live metadata outside `APP_ENV=local`. Set `MAVYN_PLUGIN_DEV=false` locally to reproduce release behavior. `platform:extensions:build` reads source plugin manifests in any environment and freezes validated metadata without resolving/upgrading packages, provided the installed dependency/autoload graph is unchanged. Thus metadata-only changes can be deployed with a catalog rebuild followed by migration/validation and cache/worker coordination. Version and distribute changed plugin code through its own repository.

## Deploy and lifecycle

1. Resolve/install packages and build the catalog.
2. Run `php artisan migrate --force` through the deployment plan. All installed plugins' declared migration files are registered, including disabled plugins. Activation never creates schema. Migration records must exist before activation/enabled registration.
3. Run `php artisan platform:extensions:validate` and rebuild installed routes/assets during deployment. The host Composer deploy script validates after migrations.
4. Activate with `php artisan platform:plugins:activate acme.example`, or use the Plugins admin page. Disable with `platform:plugins:disable`.
5. Restart long-running application/queue processes after deploying code or catalog changes.

`Plugin` exposes `register(PluginContext $context): void`. Optional `PluginLifecycle` exposes `activate(PluginContext): void` and `deactivate(PluginContext): void`. Registration only declares contributions; provider construction is deferred. Lifecycle hooks validate readiness/open work and perform repeatable database setup in the host transaction. They must not create schema, perform nontransactional external operations, or delete historical data.

A shared runtime row serializes activation, deactivation, and settings changes. The proposed enabled set is validated for installed/version-compatible active dependencies, cycles, and conflicts. Successful state/revision changes and audit share one transaction. Invalid registration, lifecycle failure, or audit failure rolls back. Repeating the same state is a no-op. Dependency disable is blocked while active dependents require it. A plugin can throw from deactivation while its operations need draining; failure remains visible and its active state is preserved.

Deactivation retains settings, migrations, historical data, and media. Physical removal is a separate Composer/deployment operation: disable dependents, drain jobs/provider operations, and keep compatibility code until queued work no longer needs it. There is no HTTP Composer installer, automatic uninstall/purge, or general queue-draining engine. Validation rejects orphaned active states after removal. Plugin lifecycle code owns feature-specific pending-operation checks.

## Hooks and provider registries

`PluginContext` offers:

```php
$context->id();
$context->setting('notice');
$context->filter('core.catalog.product.display', 'notice', function (ProductDisplay $value) use ($context) {
    return new ProductDisplay($value->productId, [...$value->notices, $context->setting('notice')]);
}, priority: 10);
$context->action('core.catalog.product.displayed', 'observe', $callback, priority: 10);
$context->provider('payment', 'acme.example.manual', ExamplePaymentProvider::class);
$context->defineAction('acme.example.event', ExampleReadonlyPayload::class);
$context->defineFilter('acme.example.display', ExampleReadonlyValue::class);
```

Import contracts from `Mavyn\Contracts\Plugins`. Hooks require readonly DTO classes; input and filter-result types are checked. Equal-priority callbacks sort by dependency registration rank, plugin ID, and contribution ID. Duplicate contribution IDs fail registration. Registration seals hooks/provider registries; late contributions are rejected. Custom hook IDs must be owned by the defining plugin.

Implemented core hooks, contract version 0.1:

| Hook | Type | Timing/failure behavior |
|---|---|---|
| `core.catalog.product.display` | filter `ProductDisplay` | Per product-detail request after catalog load; returns notices only, product identity must remain unchanged; exception/invalid result fails request. |
| `core.catalog.product.displayed` | action `ProductDisplay` | After validated display filtering, before view render; synchronous, exception fails request. |

`ProductDisplay` holds product ID and a bounded list of plain-text notices. Blade escapes notices; filters cannot change prices, stock, order totals, or ownership. Filters run outside the catalog cache so activation and settings changes take effect on new requests.

Actions here are synchronous hooks, **not durable committed order events**. There is no public `OrderPlaced` event/outbox subscription SDK yet. The existing order-email outbox continues its own delivery. Optional external notification work must not run in these display callbacks; a future domain-event delivery API needs event IDs, versioned payloads, deduplication, and failure policies.

Typed registry kinds are `payment` (`PaymentProvider::instructions(): PaymentInstructions`) and `fulfillment` (`ShippingProvider::quote(ShippingRequest): ?ShippingQuote`). Keys must be namespaced and declared capabilities match registration. Duplicate keys, wrong classes, and invalid resolved implementations fail. Providers resolve lazily through the host container. DTOs use integer minor units/currency; quote fees cannot be negative. Checkout consumes selectable payment providers and `SelectableShippingProvider` fulfillment options (with a retained local-zone adapter) and validates eligibility, currency, and receipt requirements. Additional typed kinds are `product.attributes` (`ProductAttributesProvider`) and `ordering.policy` (`OrderingPolicy`). See [Commerce plugins](COMMERCE_PLUGINS.md) for host persistence and historical adapters.

Runtime services are scoped to the request/job. Registration reads a consistent, locked state snapshot, and refreshed revision removes disabled contributions and observes settings changes. Guards query persisted activation state. In-flight requests may finish after a toggle; feature-specific draining and deployment worker restart remain required. Long-lived web-worker support is not certified by this milestone.

## Routes, permissions, and admin

Installed route files register regardless of activation. Use class controllers and relative route names. Admin files are prefixed `admin/extensions/{plugin-id}`, named `plugin.{id}.admin.*`, and wrapped in web/CSRF, activation, admin authentication, and the declared `{id}.manage` permission. Storefront files are prefixed `extensions/{id}`, named `plugin.{id}.storefront.*`, and wrapped in web/CSRF and activation. Customer ownership/private delivery/webhook signature checks remain plugin responsibilities. Constructors and route declarations must be side-effect-free.

Disabled routes return 404 before endpoint business execution, including cached routes. Admin permission checks enforce refreshed/revoked grants; plugin mutations receive the existing audit context, a DB transaction, and a credential-free operation audit. Validation/error responses roll back, including web redirect exceptions. Hiding controls is never authorization.

The shared Plugins page uses `extensions.view`; lifecycle/settings writes use `extensions.manage`. Plugin permissions are added to the staff grant registry; no existing role receives automatic new grants. Super admins can manage installed capabilities. Per-setting permissions can additionally require a declared plugin permission or the shared `settings.manage` grant. Installation remains a deployment operation.

## Settings

Settings are stored under plugin ID plus local key, independently of legacy core settings. Supported types: text, boolean, integer, two-decimal amount, email, HTTP(S) URL, and `HH:mm` time. Define a typed default, optional `nullable`, `min`, `max`, `secret`, and `permission`. Decimal bounds are in **minor units**; text bounds are character counts. Unknown keys and malformed/oversized values reject the whole save. Omitted values stay unchanged. Only active plugins accept writes.

Secrets have empty/null defaults, are encrypted with the application key, never returned to admin forms/public values, stripped from redirect old input, and never copied into audits. Blank form/password values preserve saved credentials; JSON null explicitly clears a nullable secret. Audit records contain changed key names only. Losing/changing APP_KEY requires the same key migration/recovery plan as other encrypted application data.

## Verification and remaining work

Tests cover namespaces/schema, dependency ordering/version/conflict/cycles, readonly typed hooks, priority/exclusivity/sealing/lazy providers, activation/deactivation retry and rollback, deployment migration gating, encrypted/namespaced settings, permission denial, live revision refresh, escaped storefront notices, scaffolding, stale catalogs, and actual cached-route activation guards. `tests/Support/plugin-concurrency.php` uses a disposable MySQL database for duplicate activation, dependent activation vs dependency disable, and settings save vs disable.

Local delivery, manual payment methods, food fields, and store availability are implemented in four [commerce packages](COMMERCE_PLUGINS.md). Theme runtime and Happy Tea storefront extraction are implemented. Durable domain-event subscriptions, general jobs/draining, product/admin UI slots and automatic update/purge tooling remain future milestones.

For selectable shipping options, namespaced attribute persistence, supported financial settings and historical snapshots, see [current contracts and acceptance](FIX_VALIDATION.md).
