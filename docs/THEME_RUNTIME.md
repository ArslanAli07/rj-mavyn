# Implemented theme runtime (0.1)

The reference client installs three Composer packages separately: `mavyn/theme-default`, `mavyn/theme-happy-tea`, and `mavyn/theme-happy-tea-child`. Theme source and package requirements belong to each clone, not the master repository; see the [repository setup](../README.md). The migration initially selects Happy Tea to preserve the existing storefront. Other clients activate their own installed theme. The child is an inactive example with a homepage override. Themes contain presentation files, not controllers, routes, commerce services, migrations, or executable settings. They run as trusted release code, not sandboxed merchant uploads.

## Discovery and templates

`platform:extensions:build` discovers plugin and theme metadata from Composer, verifies PHP/core/contracts constraints, and writes separate catalogs bound to `composer.lock`. Theme eager autoload files and Laravel providers are forbidden. Production ships `packages/`, `plugins/`, `themes/` and the locked dependencies. Catalogs are deployment artifacts, not merchant-managed data.

Theme manifest metadata lives in `composer.json` → `extra.mavyn`:

```json
{
  "kind": "theme",
  "id": "acme.theme-retail",
  "name": "Retail",
  "contract": "^0.1",
  "parent": null,
  "requires_active": {"acme.provider": "^0.1"},
  "settings": {
    "accent_color": {"type": "color", "default": "#2458A6"},
    "welcome_text": {"type": "text", "default": "Welcome", "max": 150}
  },
  "templates": {"pages.home": "resources/views/pages/home.blade.php"},
  "asset_manifest": "dist/manifest.json"
}
```

The canonical inventory is `Mavyn\Contracts\Themes\TemplateInventory` (0.1). It covers storefront layouts, pages, account/auth, and four presentation components. Shared admin, emails, PDFs, honeypot, purchased item configuration, media handling and secure actions remain host-owned. A file cannot add a route. Unknown names, unsafe paths, missing resources, duplicate IDs, incompatible contracts, and invalid defaults reject discovery/activation.

The resolver searches selected child → one declared parent → default. Parent cycles and multiple-level inheritance reject. A child must also require the compatible parent Composer package. The default supplies the entire inventory and has no required plugins. The view finder resolves themes per request instead of caching a preview/selection globally.

Host controllers/composers supply the existing page variables and secure named routes. Product stock/configuration payloads, wishlist IDs, cart drawer data, SEO, and optional food-field availability are prepared in host adapters. Package templates contain no App service/model queries. This increment retains existing model/collection presentation variables, media accessors, settings helpers, and some shared utility styling; it does not claim a complete immutable page DTO SDK or complete host thinning. The broad DTO/slot APIs in the proposed theme guide remain future work.

## Settings and content

`theme_settings` stores values under the theme ID and key. Types are bounded text, boolean, integer and six-digit hex color; arbitrary CSS/scripts and secret fields are unsupported. Inherited definitions must keep the same type. Values resolve from default/parent to child, with each owner's defaults and saved values applied in order; a child override default takes precedence over a parent saved value for that key. Unknown/invalid submitted keys reject the entire batch before persistence. Omitted values remain, including settings removed from a later schema; the Themes screen reports retained unsupported keys. Settings survive switching and package updates.

Store name/contact/media and plain-text about/policy content belong to core settings. The general settings screen supports about, shipping, returns, privacy, terms and city. Both themes use configured policy text when supplied; Happy Tea otherwise preserves its legacy copy. Default content avoids beverage-specific operational promises. Values are escaped. Merchant media usages and historical order snapshots are unchanged by theme changes.

## Assets

`platform:themes:assets` publishes declared resources to `public/themes/{id}/{sha256}/{filename}`. The manifest records checksum-verified `styles`, `scripts`, and optional named `files` (images/fonts). Paths stay inside installed packages. Assets load default → parent → child; named files resolve to the last supplied override. `theme_asset(name)` returns only declared public URLs. No vendor filesystem URL or merchant-provided filename is accepted.

Happy Tea's bundled logos/illustrations are package assets; original public legacy paths remain for historical compatibility. Standalone storefront effects are extracted from the shared CSS into the default package. Host Vite still provides shared utility styles and one Alpine runtime. Theme CSS/scripts load only on storefront layouts and cannot replace admin templates. Packages must ship production-ready assets; there is no request-time frontend dependency build. Existing external font/Swiper/GSAP resources remain in the extracted legacy layouts; offline delivery and full stylesheet independence are not certified here.

## Preview and activation

The admin Themes page uses `themes.view`; activation, settings and preview use `themes.manage`. Existing ordinary roles receive no automatic grants. Super admins retain access. Mutations and credential-free audit records commit together. CLI activation calls the same service.

Preview draft settings through the Themes form. A 15-minute encrypted token is bound to the initiating administrator. Every preview request checks the current account, active state, permission, expiry and theme compatibility. Another administrator, a customer, a revoked account or a tampered token cannot use it. Output is private/no-store/noindex. Draft values never change selection or saved settings and request-local preview cannot leak into a subsequent public request.

Preview supports GET home/catalog/product/category/search and content pages. Links and GET search forms retain the token; changing forms and fetch mutations are disabled. Requests carrying preview context to unsafe methods, checkout, customer/order routes or admin endpoints are rejected. Customer/order ownership is never granted by preview. This is presentation preview, not a sandbox for placing test purchases. Actual checkout/account flows use normal authorization and are covered separately by regression tests.

Activation validates the full resolution chain, assets, stored settings, contract versions and required active plugin versions. A shared `extension_runtime` row serializes theme activation/settings with plugin lifecycle changes. A required plugin cannot be disabled while its theme remains selected. Current locking reads preserve this invariant even under pre-existing MySQL transaction snapshots. Repeated activation is a no-op. Selection retains the prior theme; no commerce/media data is changed.

## Deployment and recovery

```sh
composer install
php artisan platform:extensions:build
php artisan platform:themes:assets
php artisan migrate --force
php artisan platform:extensions:validate
php artisan platform:themes:activate mavyn.theme-happy-tea
```

Composer post-autoload builds catalogs/publishes assets before package discovery. Build shared frontend assets, cache routes/views, and restart workers as part of the normal deployment. The host view cache does not precompile every theme override; package compilation is covered by the theme tests. Keep hashes/old public artifacts while old requests or rollback releases may reference them; no destructive asset cleanup is automatic.

A missing/incompatible selected theme, required plugin, template or asset falls back to the valid default for rendering, logs a reason, and preserves selection for diagnosis. A stale/missing installed catalog fails closed and requires rebuild. A render-time PHP/Blade failure has an explicit recovery path rather than hiding an exception: deploy repaired code or select default through the shared admin/CLI. Recover with:

```sh
php artisan platform:themes:activate mavyn.theme-default
php artisan platform:extensions:validate
```

To return to the prior selection, choose its recorded ID after validating that release. Catalog/package updates require worker restart; long-lived web-worker support remains uncertified. Back up configuration/database and release artifacts before rollback. Rolling back the schema migration drops theme configuration tables; switching themes is the non-destructive recovery operation.

## Validation

Theme tests cover discovery rules, one-level inheritance, fallback, initial selection, retained settings/content, atomic invalid batches, escaping, permissions, preview isolation/expiry/revocation, rejected preview mutations/order access, required plugins, no-op activation/audit rollback, ordered hashed URLs, and PHP compilation of every installed template. Plain core tests run the catalog/settings algorithms without Laravel. Disposable MySQL checks duplicate activation, activation versus dependency disable and settings versus activation with pre-existing snapshots. Existing commerce tests and the rendered checkout JavaScript checks run through Happy Tea.

Next: non-food pilot and deployment/recovery validation, together with the outstanding existing-store records/files backfill. Browser/accessibility and live pilot validation remain to be performed before release.
