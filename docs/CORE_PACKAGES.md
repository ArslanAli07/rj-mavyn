# Core package operations

The host now requires `mavyn/contracts` and `mavyn/core` at `^0.1`, locked to the development version `0.1.0`. Composer path repositories resolve the packages in `packages/`; their PHP namespaces are `Mavyn\Contracts` and `Mavyn\Core`. Packages have separate Composer manifests and no dependency on `App` or Laravel.

## Application integration

The application delegates money arithmetic, coupon discount calculation and email/IP identity, configuration signatures, to core. Checkout payment availability now comes from the manual-payment plugin; the old core `PaymentMethods` class remains a compatibility API. `App\Support\Money` and existing service/model APIs remain compatibility adapters. `SettingsStore` is bound to `EloquentSettingsStore` in the host container. Order placement, DB locks, audit events, inventory, outbox jobs, media ownership, and routes retain their existing behavior and identities. No migration is required by this extraction.

The settings endpoint accepts only declared schema keys or the four existing media selection fields. It validates the entire request before saving and persists schema-defined types/groups. Omitted values remain unchanged. Invalid/unknown values reject the request without a partial save or success audit. Explicit booleans are stored as `0`/`1`, including when an old row had a text type. Email, HTTP(S) URL, nonnegative two-decimal amount, and `H:i` time formats are checked. Equal/overnight ordering hours retain the existing scheduling semantics. The same schema supplies defaults for unconfigured form fields. Settings values stay redacted in audit history.

Legacy settings remain readable. Internal `Setting::set` is a compatibility API, not an extension write API; it deliberately remains available to existing seeders/migration tooling. SMTP credentials are environment configuration and cannot be submitted to this endpoint. Media settings continue to use registered ownership and tracked references rather than arbitrary scalar asset IDs. Permissions remain enforced by admin middleware and media policies.

Admin branding uses the configured store name/logo and generic catalog language. The shared auth layout also uses store name/tagline. Storefront presentation and historical identifiers/data remain host theme work.

## Distribution and verification

Run `composer install` from the host with `packages/` present. Only the two local packages were added to the lockfile; third-party versions were not upgraded. Root development repository options assign version `0.1.0`; public release tags/repository publication are separate deployment actions. Ship these directories with the current host lockfile, or replace the path repository with your release repository and regenerate the lockfile before deployment.

Validation includes strict Composer checks on all three manifests, the full Laravel test suite, and a separate Composer installation in `/tmp` with Packagist disabled and mirrored package directories. That isolated consumer exercises money, coupons, identity, configuration, schema loading, and an in-memory settings adapter with neither Laravel nor host classes available. The build, formatting, and whitespace checks also pass. No live delivery or production deployment is part of this milestone.

Plugin discovery/lifecycle, actions/filters, typed provider registries, and namespaced settings are now implemented; see [Plugin runtime](PLUGIN_RUNTIME.md). The next milestone extracts existing business features into plugins. Shared admin/persistence extraction will continue incrementally as those boundaries stabilize. The broad APIs illustrated in the plugin guide remain proposals; `SettingsStore` and the services listed in the package README are the implemented APIs.
