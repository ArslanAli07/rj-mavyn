# Theme Development Guide

Status: Target developer specification; runtime foundation implemented
Date: 2026-10-07
Related documents: [PRD](PRD.md), [architecture](ARCHITECTURE.md), [plugin development](PLUGIN_DEVELOPMENT.md)

## 1. Read this first

The installed runtime now implements Composer manifests, one-level inheritance/default fallback, theme settings/assets, admin preview/activation and initial Happy Tea extraction. Use [Implemented theme runtime](THEME_RUNTIME.md) for the executable 0.1 API and its compatibility boundaries.

The examples below describe the broader target experience. Their manifest schema, immutable page DTOs, slots, scaffold and preview CLI are not all implemented. The installed runtime's actual schema and commands take precedence.

## 2. Theme responsibilities

A theme controls storefront layouts, templates, components, typography, colors, assets, sections, and customer-facing label overrides. It can provide a distinct visual experience for any product line.

Core services own pricing, stock, checkout, order creation, authorization, and historical snapshots. Plugins own specialized capabilities. A theme consumes prepared page data and secure core actions rather than querying models or recreating commerce rules.

The shared admin has its own maintained UI. Store branding may configure that UI, but storefront themes do not replace it.

## 3. Package structure

```text
acme-theme-retail/
  composer.json
  resources/views/
    layouts/storefront.blade.php
    pages/home.blade.php
    catalog/index.blade.php
    catalog/category.blade.php
    catalog/search.blade.php
    catalog/product.blade.php
    cart/index.blade.php
    checkout/index.blade.php
    checkout/success.blade.php
    checkout/payment-instructions.blade.php
    orders/tracking.blade.php
    account/
    auth/
    content/
    errors/
    components/
    overrides/plugins/
  resources/lang/en/
  resources/css/
  resources/js/
  resources/images/
  schemas/settings.json
  dist/manifest.json
  package.json
  package-lock.json
  tests/
  README.md
  CHANGELOG.md
```

The canonical template inventory is an SDK contract. Theme paths must map to that inventory; a theme cannot invent a route merely by adding a file.

## 4. Manifest

Keep identity and compatibility in the Composer package metadata. Build output supplies the asset manifest; package version comes from the installed Composer catalog.

```json
{
  "name": "acme/theme-retail",
  "description": "General retail storefront theme",
  "type": "library",
  "require": {
    "mavyn/contracts": "^1.0",
    "mavyn/core": "^1.0"
  },
  "extra": {
    "mavyn": {
      "kind": "theme",
      "id": "acme.theme-retail",
      "name": "Retail",
      "parent": null,
      "requires_active": [],
      "settings_schema": "schemas/settings.json",
      "asset_manifest": "dist/manifest.json",
      "templates": [
        "layouts.storefront",
        "pages.home",
        "catalog.product",
        "cart.index",
        "checkout.index"
      ]
    }
  }
}
```

This is a proposed schema; fields and supported constraints will be finalized in the SDK. The template list records provided overrides, not a claim that these five templates cover every required journey. Missing templates may use compatible parent/core fallbacks.

Dependencies on essential plugin functionality must be explicit. Merely installing a plugin package does not guarantee that it is enabled. Themes should avoid requiring plugins solely for cosmetic details that can degrade gracefully.

## 5. Template resolution and child themes

Resolution order for each documented template:

1. Active child theme, when present.
2. Its declared parent, or the active standalone theme.
3. Core default storefront fallback.

MVP supports one parent level. The manager rejects parent cycles, missing/incompatible parents, unsafe paths, and unsupported template identifiers.

Child themes keep client-specific visual overrides separate from parent updates, following the purpose of [WordPress child themes](https://developer.wordpress.org/themes/advanced-topics/child-themes/). Our implementation uses Blade and platform manifests rather than WordPress APIs.

A proposed child theme declares its parent in both package dependencies and platform metadata:

```json
{
  "name": "client/theme-retail-custom",
  "type": "library",
  "require": {
    "mavyn/contracts": "^1.0",
    "mavyn/core": "^1.0",
    "acme/theme-retail": "^1.0"
  },
  "extra": {
    "mavyn": {
      "kind": "theme",
      "id": "client.theme-retail-custom",
      "name": "Client Retail",
      "parent": "acme.theme-retail",
      "requires_active": [],
      "settings_schema": "schemas/settings.json",
      "asset_manifest": "dist/manifest.json",
      "templates": ["pages.home"]
    }
  }
}
```

The parent is available as part of the active theme chain, not a second independently active storefront. Its exact version is pinned by the client lockfile. Override only the required templates/styles; maintain their public data/action contracts.

Child themes do not inherit arbitrary mutable PHP behavior. Parent and child assets are explicitly ordered by the resolver, and settings schemas must merge under documented rules: compatible additions/defaults are allowed, conflicting types are rejected. Final merge rules require SDK tests.

## 6. Data and action contracts

Controllers/application services provide immutable or read-only page DTOs. The intended contracts include:

| Page | Prepared data | Supported actions |
|---|---|---|
| Catalog/search | Product cards, category/filter state, pagination | Browse/search/filter |
| Product | Product details, valid variants/options, display prices, availability | Submit configuration to core cart action |
| Cart | Configured items, totals, validation notices | Update/remove/apply coupon |
| Checkout | Validated cart, address fields, registered provider choices, notices | Request quote/place order through core |
| Account/orders | Authorized customer data and purchased snapshots | Authorized account/address actions |
| Tracking/success | Authorized order presentation | Supported tracking/account links |
| Content | Store content and configured sections | Registered contact/newsletter actions when enabled |

DTO field names and versioning are a design task. Templates must not depend on undocumented Eloquent relationships, access credentials, or trigger database writes while rendering.

An illustrative product template using proposed SDK helpers:

```blade
<h1>{{ $product->title }}</h1>
<p>{{ $product->formattedPrice }}</p>

<form method="POST" action="{{ $actions->addToCart }}">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <x-platform-product-configuration :configuration="$configuration" />
    <button type="submit">{{ __('storefront.add_to_cart') }}</button>
</form>
```

The component, translation key, DTO fields, and action object are illustrative. Final form fields must follow the published cart contract. All prices/selections are verified server-side even if the theme shows a client-side estimate.

Preserve validation errors and previous selections after failed submissions. Render unavailable, changed-price, empty, loading, and successful states. Never suppress a core checkout notice because it does not fit the design.

## 7. Settings, content, and sections

Separate theme styling configuration from merchant content:

- Theme settings: colors, typography choices, spacing presets, section arrangements, visual variants.
- Store content: logos, contact details, policies, hero copy, images, category selections.
- Commerce settings: currency, payment providers, delivery methods, stock rules; managed by core/plugins.

Store persisted values in the client database, outside theme source. Schema-driven forms validate submitted values and preserve them on updates. A theme switch retains prior configuration for future reuse and reports unsupported values.

Illustrative settings-schema fragment:

```json
{
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "accent_color": {
      "type": "string",
      "pattern": "^#[0-9A-Fa-f]{6}$",
      "default": "#2458A6"
    },
    "catalog_columns": {
      "type": "integer",
      "enum": [2, 3, 4],
      "default": 3
    }
  }
}
```

The supported schema subset and form-renderer metadata must be documented by the SDK. Do not accept arbitrary executable templates, JavaScript, or CSS expressions through settings.

Sections declare stable IDs, permitted content fields, layout options, and data sources supplied by core/plugins. A configurable homepage is not an unrestricted code editor. Render rich content only through the platform's defined sanitization policy.

## 8. Plugin integration and override slots

Themes render named slots for product details/configuration, cart notices, checkout provider fields, account navigation/content, and optional content sections. Exact slot IDs and input DTOs belong to the public SDK.

Illustrative slot syntax:

```blade
<x-platform-slot name="catalog.product.additional-details" :context="$productContext" />
```

A plugin can supply a component for the slot. A theme may override a documented plugin component using an explicit manifest mapping to a namespaced template under `overrides/plugins/`. There is no arbitrary override search across writable directories.

Resolution for a supported plugin component is child override → parent/standalone override → plugin default component. Validate the override's required fields/actions. Missing optional plugin functionality disappears gracefully; missing required capabilities blocks activation with a clear reason.

Themes cannot add a field that the server never validates/persists and call it functional. New business fields/actions require the corresponding plugin contract.

## 9. Language and industry wording

Use translation keys throughout templates, accessibility labels, validation presentation, and notifications. Default wording is Products, Catalog, Variants, Options, Add-ons, Item notes, and Processing.

A beverage theme may override customer-facing labels such as Catalog → Menu. It must not change persisted workflow IDs, interpret physical weight as variant name, or force food fields into every product.

Read brand names, location, currency, and contact details from store data. Use core-provided formatted prices/dates and stored order currency. Themes must not hardcode `Rs.`, client names, or country-specific checkout rules.

Supported email/invoice customization uses documented override templates and historical snapshots, consistent with the PRD. The initial template inventory must define those supported overrides and fallback behavior. They cannot redefine payment verification or purchased totals. Theme changes must preserve required fallback email/PDF rendering.

## 10. Assets and frontend dependencies

### Merchant media

Use the central media picker in theme/content configuration and render core Media DTOs for product images, logos, hero images, and galleries. References are persisted through tracked core media usages, not bare unindexed file paths. DTOs provide permitted URLs, dimensions, alt text, responsive sources, and conversion readiness.

Select registered conversion profiles rather than resizing/optimizing in Blade or storefront requests. Render defined placeholders/fallbacks while jobs process. Themes may request namespaced profiles through the core registry, but cannot access private receipts or broaden an asset's visibility. Theme switching retains merchant uploads and usages.

See [Central media management](MEDIA_MANAGEMENT.md). Bundled icons/fonts/illustrations remain immutable theme assets; import editable merchant content into the library explicitly when needed.

### Packaged assets

Each theme can own a frontend package manifest/lockfile and build tooling. Ship prebuilt production assets plus a validated manifest so client servers need not resolve/build a theme's dependencies during a storefront request.

- Asset paths include theme identity/version or content hashes.
- Use the platform asset resolver, not filesystem paths into `vendor/`.
- Keep shared Alpine/platform behavior on the host's supported runtime contract; do not load a second conflicting global runtime.
- Scope plugin/theme CSS and avoid resetting unrelated components.
- Parent assets load in the documented order before child overrides.
- Keep JavaScript optional for supported basic forms where practical; expose accessible loading/error states for asynchronous actions.
- Do not embed secrets, customer records, or provider credentials in bundles or public manifests.
- Audit/declare externally loaded fonts/scripts; pin versions and account for failures if they are used.

Laravel package views/resources can underpin the implementation; see [Laravel package development](https://laravel.com/framework/docs/13.x/packages). The theme resolver adds the platform-specific activation and inheritance policy.

## 11. Local workflow, preview, and activation

1. Create a theme package in the development workspace.
2. Add it to the integration host through a root Composer path repository.
3. Implement the required template overrides and settings schema.
4. Build assets and validate the manifest/template inventory.
5. Preview with test products, cart configurations, accounts, and orders.
6. Test fallback pages and plugin combinations.
7. Publish a version and deploy through a tested client artifact.

Planned commands, not yet available:

```text
php artisan platform:theme:make acme.theme-retail
php artisan platform:extensions:validate
php artisan platform:themes:preview acme.theme-retail
php artisan platform:themes:activate acme.theme-retail
```

Preview requires an authorized, short-lived context. Draft settings do not become global configuration and preview context never grants extra customer/order access. Preview does not mutate commerce state.

Activation checks compatibility, required plugins, templates/fallbacks, settings, and asset availability. Theme changes record the prior compatible selection and invalidate only relevant caches. Preview output must not contaminate public page caches.

## 12. Testing and release checklist

Test these journeys on desktop/mobile and with keyboard navigation:

- Home/catalog/category/search, pagination, missing images, and empty results.
- Simple product and variants with required/multiple options, unavailable choices, and item notes.
- Cart merging/update/removal, coupon errors, and changed price/availability notices.
- Guest/customer checkout with every supported payment/fulfillment plugin.
- Payment unavailable, failed validation, duplicate submit, and successful order states.
- Account/address ownership boundaries, tracking, historical snapshots, and order access errors.
- Missing optional plugins and supported plugin-component overrides.
- Parent/child/core fallback, settings preservation, and theme update/rollback.
- Labels, contrast, focus order, error announcement, reduced motion, and image alternatives.

Security and commerce invariants are enforced by core/plugin tests; theme tests verify that the UI preserves and communicates those behaviors.

Before release:

1. Confirm core/contracts/parent/plugin compatibility constraints.
2. Validate required template/data/action contracts and settings schema.
3. Build versioned assets and check package contents/dependency licenses.
4. Review neutral defaults, configurable branding, accessibility, and external asset behavior.
5. Update changelog with template/configuration migrations and compatibility changes.
6. Test against the selected client lockfile/artifact in staging before deployment.

Template/data/action or settings changes that break consumers require a major version or an explicit compatible migration path. Preserve old settings/content; do not ship updates by overwriting client-edited theme files.

## 13. Migrating the current storefront

1. Move reusable storefront templates/assets into the Happy Tea theme package.
2. Replace hardcoded brand/contact/policy content with store data.
3. Replace embedded model queries and helper assumptions with prepared DTOs/actions.
4. Keep beverage language as scoped theme translations, with generic core defaults.
5. Move food attributes, local delivery, ordering hours, and manual payment behavior into plugins.
6. Preserve current public URLs and verify product configuration, cart, checkout, tracking, and accounts.
7. Add default/core fallback templates and prove a small child theme changes the homepage without touching parent/core source.

## 14. Common mistakes

| Mistake | Supported approach |
|---|---|
| Copying core checkout into a theme | Render published checkout data/actions |
| Querying database models in Blade | Consume prepared page DTOs |
| Hardcoding store name/currency | Use store content and formatted values |
| Editing the parent for one client | Use a child theme or settings |
| Hiding required validation/provider states | Render all documented states |
| Adding unsupported functional fields | Develop/use a plugin contract |
| Loading duplicate global JS frameworks | Use the shared runtime contract and scoped bundles |
| Storing merchant content in source files | Persist validated client content/configuration |
| Relying on enabled plugins implicitly | Declare essential active dependencies or degrade gracefully |
