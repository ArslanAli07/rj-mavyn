# Plugin Development Guide

Status: Proposed developer specification  
Date: 2026-10-07  
Related documents: [PRD](PRD.md), [architecture](ARCHITECTURE.md), [theme development](THEME_DEVELOPMENT.md)

## 1. Read this first

The plugin runtime and SDK are planned, not implemented. Platform class names, registry methods, middleware aliases, and commands in this guide are illustrative API proposals. They must be finalized and tested when the contracts package is implemented. Do not paste these examples into the current application expecting them to execute.

`mavyn/` is an illustrative package namespace. PHP version constraints are examples, not a confirmed deployment requirement. This guide describes how extensions should work once the architecture is implemented.

## 2. When to build a plugin

Use a plugin to add business behavior: payment or fulfillment providers, product attributes, checkout validation, loyalty points, integrations, staff tools, account features, or optional content capabilities.

Use a theme for presentation. Use store configuration/content for logos, contact details, policies, and wording. If multiple unrelated plugins need the same missing commerce operation, propose a generic core contract instead of duplicating business logic.

A plugin may extend an existing feature or own a completely new feature with its own services, database tables, endpoints, UI, and jobs. Both integrate through the same platform extension manager.

## 3. Package structure and identity

```text
acme-local-delivery/
  composer.json
  src/
    LocalDeliveryPlugin.php
    Providers/ZoneDeliveryProvider.php
    Services/
    Models/
    Http/Controllers/
    Http/Requests/
    Listeners/
    Jobs/
  database/migrations/
  routes/admin.php
  routes/storefront.php
  resources/views/
  resources/lang/en/
  resources/css/
  resources/js/
  schemas/settings.json
  dist/manifest.json
  tests/
  README.md
  CHANGELOG.md
```

Choose permanent identities:

| Identity | Example |
|---|---|
| Composer package | `acme/local-delivery` |
| Plugin ID | `acme.local-delivery` |
| PHP namespace | `Acme\LocalDelivery` |
| Provider key | `acme.local-delivery.zone` |
| Permission | `acme.local-delivery.manage` |
| Setting | `acme.local-delivery.default_fee` |
| Route name | `acme.local-delivery.admin.zones.index` |
| View/translation namespace | `acme-local-delivery` |
| Table prefix | `acme_local_delivery_` |

Plugin IDs and provider keys appear in persisted records. Renaming them requires an explicit migration and historical compatibility strategy.

## 4. Manifest and dependencies

Keep package metadata in `composer.json`; do not maintain another version number in a separate plugin file.

```json
{
  "name": "acme/local-delivery",
  "description": "Zone-based local delivery for physical products",
  "type": "library",
  "require": {
    "php": "^8.3",
    "mavyn/contracts": "^1.0",
    "mavyn/core": "^1.0"
  },
  "autoload": {
    "psr-4": { "Acme\\LocalDelivery\\": "src/" }
  },
  "extra": {
    "mavyn": {
      "kind": "plugin",
      "id": "acme.local-delivery",
      "name": "Local Delivery",
      "provider": "Acme\\LocalDelivery\\LocalDeliveryPlugin",
      "requires_active": [],
      "conflicts_with": [],
      "capabilities": ["fulfillment.provider"]
    }
  }
}
```

Declare PHP/library/package constraints in `require`. Declare active plugin dependencies in `requires_active`; an installed dependency can still be disabled. Activation checks verify required active dependencies and their declared versions against the installed catalog. The precise constraint representation is part of manifest schema finalization.

Each plugin owns its dependencies and can have a local development `vendor/`. Production uses one resolved root `vendor/` and one client lockfile. Composer installs transitive dependencies and generates the shared autoloader; see [Composer package/dependency basics](https://getcomposer.org/doc/01-basic-usage.md).

Separate production vendor folders do not isolate conflicting PHP class names. Prefer compatible constraints; reject incompatible combinations at build time. Namespace prefixing or an external process is an explicitly reviewed exception. A disabled package still participates in dependency resolution.

Do not register platform plugins through `extra.laravel.providers`. Their enabled behavior is controlled by the platform manager. Review third-party auto-discovered providers and eager `autoload.files` code: installed dependency code may execute even when the feature is disabled.

## 5. Local development

Develop in the platform integration host or a standalone test host. Configure a path repository in the host, with the path relative to that host's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../plugins/local-delivery",
      "options": { "symlink": true }
    }
  ]
}
```

Require a development version matching the package's actual branch/version metadata. Repository declarations belong to the root host; see [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path).

Planned convenience commands:

```text
php artisan platform:plugin:make acme.local-delivery
php artisan platform:extensions:validate
php artisan platform:plugins:activate acme.local-delivery
php artisan platform:plugins:disable acme.local-delivery
```

These commands do not exist yet. Database migrations are applied through a controlled local/deployment migration plan before activation; activation never performs request-time schema installation.

## 6. Entry point and registration

A platform plugin entry point is adapted to Laravel by the core extension manager. Its proposed `register(PluginContext)` signature is not Laravel's native `ServiceProvider::register()` signature. Do not subclass Laravel's provider and change that method signature.

Illustrative registration body:

```php
public function register(PluginContext $plugin): void
{
    $plugin->fulfillment()->add(
        'acme.local-delivery.zone',
        ZoneDeliveryProvider::class
    );

    $plugin->admin()->page('delivery-zones', DeliveryZonesPage::class)
        ->requiresPermission('acme.local-delivery.manage');

    $plugin->actions()->listen(OrderPlaced::class, NotifyDispatcher::class);
    $plugin->filters()->add(
        'catalog.product.display',
        AddDeliveryNotice::class,
        priority: 20
    );
}
```

Registration declares contributions; it does not charge payments, modify stock, contact external services, or create orders. Container factories defer service construction until needed. Avoid mutable customer/cart state in global singletons.

Separate installed-resource registration from enabled behavior. Installed resources include inert route definitions and migration inventory. Enabled behavior includes provider implementations, menus, listeners, settings forms, and scheduled work. Exact interfaces for these phases will be defined in the SDK.

## 7. Actions, filters, registries, and slots

| Mechanism | Example | Required behavior |
|---|---|---|
| Action | Send an integration notification after `OrderPlaced` | Consume immutable committed data; deduplicate retries |
| Filter | Add a product display notice | Return the documented type; preserve unrelated fields |
| Registry | Register a shipping provider | Implement the typed provider contract |
| Validation pipeline | Restrict orders outside a service area | Return structured validation failures |
| Pricing contribution | Redeem a discount | Return a typed adjustment; core validates arithmetic and eligibility |
| UI slot | Add food attributes to product editing | Provide permission, validation, and transaction-aware persistence |

Actions react to events; filters transform values. Familiar WordPress usage follows this distinction; see [WordPress hooks](https://developer.wordpress.org/plugins/hooks/).

Every hook has a documented ID, payload/result type, version, priority rules, transaction timing, and failure policy. Equal-priority callbacks use dependency order, plugin ID, and contribution ID for deterministic results. Duplicate exclusive registry IDs and invalid return values fail validation.

Expose custom hooks under the plugin's namespace when other plugins need to extend your feature. Document them as public contracts with compatibility commitments.

Do not overwrite final order totals through an unrestricted filter or bypass ownership/stock checks. Core services own authoritative pricing, authorization, inventory, order state transitions, and idempotency. Internal core classes/models are not automatically stable plugin APIs.

## 8. Routes, admin pages, and permissions

- Use class controllers and namespaced route names/URL segments.
- Place admin endpoints behind admin authentication, activation guard, and capability checks.
- Customer endpoints enforce ownership; web mutations require CSRF protection.
- Validate uploads and serve sensitive files through authorized private-storage access.
- Protect webhook routes with provider authentication/signature verification and idempotency.
- Hiding a navigation item does not authorize or secure its endpoint.

Cached routes describe installed packages. Activation guards make disabled endpoints unavailable before business execution. Keep controller constructors free of side effects; framework middleware discovery must not trigger plugin operations.

An illustrative protected route group may use proposed aliases `plugin.active:acme.local-delivery` and `permission:acme.local-delivery.manage`. These aliases are not present in the current application.

Register settings and pages through the shared admin; reuse its forms, validation errors, pagination, and layout. Do not fork the admin layout to add a menu item.

## 9. Database, settings, and product fields

Own namespaced tables and migrations. Use extension tables or typed product attributes instead of adding arbitrary columns to core tables. Core schema changes require a supported core migration/contract.

Migrations run during deployment, are recorded by package, and remain immutable after release. Updates must preserve historical records. Deactivation does not roll back tables.

Settings schemas declare types, defaults, validation, permissions, and secret flags. Use minor units plus currency for money. Reject unknown keys and invalid values. Credentials must be protected/masked and omitted from logs and public configuration.

Product field contributions need a complete lifecycle: load → render → validate → save. Required extension writes participate in the enclosing operation's transaction; rendering a field alone is insufficient. Archive/deletion behavior must preserve purchased snapshots.

## 10. Lifecycle, jobs, and failure handling

### Central media integration

Use the core media service and shared picker for merchant uploads; do not install a separate Media Library version or create plugin-specific upload tables/storage flows. Core owns the `spatie/laravel-medialibrary` dependency.

Register namespaced media slots with allowed types, counts, visibility, permissions, and optional conversion profiles. Attach core asset IDs to plugin records through polymorphic usage references; use stable registered morph aliases. The same upload may be reused across core/plugin content without copying its original.

Private uploads require authorized delivery for originals and derivatives. Detach removes a usage, not the file. Deactivation/removal retains referenced media and historical holds; direct Spatie/storage deletion bypassing the core lifecycle is unsupported. See [Central media management](MEDIA_MANAGEMENT.md) for policies, proposed APIs, optimization, and tests.

### Lifecycle behavior

| Operation | Plugin responsibility |
|---|---|
| Install/update | Supply manifests/migrations/assets; pass build compatibility checks |
| Activate | Validate configuration; perform repeatable setup; expose enabled contributions |
| Deactivate | Stop new work, identify open transactions/dependents, drain safely, retain data |
| Remove package | Ensure no active dependency or queued job requires its code |
| Purge data | Separate explicit authorized cleanup with backup/recovery plan |

Lifecycle hooks must tolerate retries and interrupted transitions. Never rely on activation to create schema. Deactivation and data deletion are distinct, as in [WordPress lifecycle guidance](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/).

Committed events are delivered through the planned transactional outbox and queues at least once. Deduplicate by event ID and stable business keys; do not promise exactly-once delivery. Jobs carry plugin/payload version and must remain compatible until drained.

Required checkout validation/provider failures block the purchase. Optional notification failures retry without undoing a committed order. Do not catch an exception and report success for required work. Log plugin ID/version, operation/correlation ID, and safe diagnostics.

Plugins run as trusted PHP application code, not sandboxed tenants. Use reviewed packages and isolate client deployments.

## 11. Example: a new loyalty feature

1. Declare plugin identity, core constraints, permissions, and settings.
2. Add a namespaced points ledger and immutable migrations.
3. Register admin/account pages with permission and ownership checks.
4. Award eligible points from a documented committed order event, deduplicating its ID.
5. Supply redemption through a typed core pricing adjustment and atomic points reservation/debit.
6. Handle cancellation/refund compensation through documented events.
7. Block or drain pending redemptions before deactivation; keep the ledger readable.

This feature owns new functionality while extending existing checkout. It does not require client-specific conditionals in core.

## 12. Testing and release checklist

Test the plugin independently and with the platform integration host:

- Registration, duplicate IDs, dependencies/conflicts, activation/deactivation, and interrupted retries.
- Disabled endpoints with cached routes; unauthorized/direct/bulk requests.
- Typed provider/filter behavior and invalid payloads.
- Transaction rollback, concurrent operations, duplicate events, and job retries where applicable.
- Migration from the prior supported version and reading historical orders with the plugin disabled.
- Two client contexts for settings/storage/cache separation.
- Theme integration, missing slots, and frontend namespace collisions.

Before release:

1. Document compatibility, public hooks/settings, permissions, and pending-operation constraints.
2. Pass independent and selected client-combination tests.
3. Build assets; validate namespaces/manifests and package contents.
4. Update changelog and publish an immutable versioned package.
5. Resolve client dependencies in CI; test the resulting lockfile/artifact.
6. Deploy through staging/canary with migration, worker, health, and recovery checks.

Major versions change public contracts incompatibly; minor versions add compatible functionality; patches fix compatible behavior. Runtime upgrades use approved deployment jobs rather than Composer in an HTTP request.

## 13. Common mistakes

| Mistake | Supported approach |
|---|---|
| Editing core to add a plugin branch | Register a generic extension contribution |
| Bundling a conflicting production vendor tree | Declare dependencies and resolve the root graph |
| Running side effects during registration | Defer to an authorized action/service or committed event |
| Creating tables during activation | Use deployment migrations |
| Deleting data on disable | Preserve data; explicit purge separately |
| Reading mutable catalog values for old orders | Use purchased snapshots |
| Using floats/rewriting totals | Typed money and validated pricing contributions |
| Removing code before jobs complete | Drain jobs or provide compatible handling before removal |
| Registering a hidden page without authorization | Check capabilities on every endpoint |
